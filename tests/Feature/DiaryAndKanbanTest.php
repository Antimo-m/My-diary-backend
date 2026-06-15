<?php

namespace Tests\Feature;

use App\Jobs\SendTaskReminder;
use App\Mail\TaskReminderMail;
use App\Models\KanbanTask;
use App\Models\User;
use App\Services\TaskReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiaryAndKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_blade_diary_and_kanban_pages_are_not_served(): void
    {
        $this->get('/diary')->assertNotFound();
        $this->get('/kanban')->assertNotFound();
    }

    public function test_guest_cannot_access_private_diary_and_kanban_apis(): void
    {
        $this->getJson('/api/diary-notes')->assertUnauthorized();
        $this->get('/api/diary-notes/1/cover')->assertUnauthorized();
        $this->getJson('/api/kanban/board')->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_list_update_and_delete_a_diary_note(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create();
        $cover = UploadedFile::fake()->image('cover.jpg', 1200, 800);

        $createResponse = $this->actingAs($user)->postJson('/api/diary-notes', [
            'entry_date' => '2026-06-01',
            'title' => 'Prima nota',
            'body' => 'Una giornata da ricordare.',
            'cover_image' => $cover,
        ])->assertCreated();

        $noteId = $createResponse->json('data.id');
        $note = $user->diaryNotes()->firstOrFail();

        $this->assertSame($note->id, $noteId);
        Storage::disk('local')->assertExists($note->cover_image);
        Storage::disk('public')->assertMissing($note->cover_image);

        $this->actingAs($user)
            ->getJson('/api/diary-notes')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Prima nota')
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($user)->putJson("/api/diary-notes/{$noteId}", [
            'entry_date' => '2026-06-02',
            'title' => 'Titolo aggiornato',
            'body' => 'Testo aggiornato',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Titolo aggiornato');

        $this->actingAs($user)
            ->deleteJson("/api/diary-notes/{$noteId}")
            ->assertOk();

        $this->assertDatabaseMissing('diary_notes', ['id' => $noteId]);
    }

    public function test_users_cannot_read_notes_owned_by_other_users(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $note = $owner->diaryNotes()->create([
            'entry_date' => '2026-06-01',
            'title' => 'Privata',
            'body' => 'Contenuto riservato.',
        ]);

        $this->actingAs($otherUser)
            ->getJson("/api/diary-notes/{$note->id}")
            ->assertNotFound();
    }

    public function test_diary_notes_are_paginated(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $index) {
            $user->diaryNotes()->create([
                'entry_date' => today()->subDays($index)->toDateString(),
                'title' => "Nota {$index}",
                'body' => 'Contenuto diario.',
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/diary-notes?per_page=4&page=2')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 10);
    }

    public function test_diary_notes_receive_unique_slugs_and_remain_accessible_by_legacy_id(): void
    {
        $user = User::factory()->create();
        $payload = [
            'entry_date' => '2026-06-07',
            'title' => 'La mia giornata',
            'body' => str_repeat('Una riflessione utile per organizzare meglio il lavoro quotidiano. ', 40),
        ];

        $first = $this->actingAs($user)
            ->postJson('/api/diary-notes', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'la-mia-giornata');

        $this->actingAs($user)
            ->postJson('/api/diary-notes', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'la-mia-giornata-2');

        $slugResponse = $this->actingAs($user)
            ->getJson('/api/diary-notes/la-mia-giornata')
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.route_identifier', 'la-mia-giornata');

        $this->assertGreaterThan(1, $slugResponse->json('data.page_count'));

        $this->actingAs($user)
            ->getJson('/api/diary-notes/'.$first->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'la-mia-giornata');
    }

    public function test_diary_covers_are_private_and_require_the_owner(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $response = $this->actingAs($owner)->post('/api/diary-notes', [
            'entry_date' => '2026-06-01',
            'title' => 'Con cover privata',
            'body' => 'Contenuto con immagine privata.',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertCreated();

        $note = $owner->diaryNotes()->firstOrFail();
        Storage::disk('local')->assertExists($note->cover_image);
        Storage::disk('public')->assertMissing($note->cover_image);

        $coverUrl = parse_url($response->json('data.cover_image_url'), PHP_URL_PATH);

        $coverResponse = $this->actingAs($owner)
            ->get($coverUrl)
            ->assertOk();

        $this->assertStringContainsString('private', (string) $coverResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $coverResponse->headers->get('Cache-Control'));
        $this->actingAs($otherUser)->get($coverUrl)->assertNotFound();
    }

    public function test_authenticated_user_can_manage_kanban_board_tasks_columns_and_labels(): void
    {
        $user = User::factory()->create();

        $board = $this->actingAs($user)
            ->getJson('/api/kanban/board?date=2026-06-01')
            ->assertOk()
            ->assertJsonCount(3, 'columns')
            ->assertJsonCount(4, 'labels');

        $columnId = $board->json('columns.0.id');
        $labelId = $board->json('labels.0.id');

        $taskResponse = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'kanban_column_id' => $columnId,
            'title' => 'Scrivere retrospettiva',
            'description' => 'Dieci minuti a fine giornata.',
            'status' => KanbanTask::STATUS_TODO,
            'label_ids' => [$labelId],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Scrivere retrospettiva');

        $taskId = $taskResponse->json('data.id');

        $this->assertDatabaseHas('kanban_label_kanban_task', [
            'kanban_task_id' => $taskId,
            'kanban_label_id' => $labelId,
        ]);

        $newColumnResponse = $this->actingAs($user)->postJson('/api/kanban/columns', [
            'title' => 'In revisione',
            'color' => '#14b8a6',
        ])->assertCreated();

        $newColumnId = $newColumnResponse->json('data.id');

        $this->actingAs($user)->patchJson("/api/kanban/tasks/{$taskId}/move", [
            'kanban_column_id' => $newColumnId,
            'position' => 0,
            'status' => KanbanTask::STATUS_DOING,
        ])->assertOk()
            ->assertJsonPath('data.kanban_column_id', $newColumnId);

        $this->actingAs($user)->putJson("/api/kanban/tasks/{$taskId}", [
            'title' => 'Retrospettiva aggiornata',
            'label_ids' => [],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Retrospettiva aggiornata');

        $this->assertDatabaseMissing('kanban_label_kanban_task', [
            'kanban_task_id' => $taskId,
            'kanban_label_id' => $labelId,
        ]);

        $createdLabel = $this->actingAs($user)->postJson('/api/kanban/labels', [
            'name' => 'Focus',
            'color' => '#8b5cf6',
        ])->assertCreated();

        $this->actingAs($user)->putJson('/api/kanban/labels/'.$createdLabel->json('data.id'), [
            'name' => 'Deep work',
            'color' => '#8b5cf6',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Deep work');

        $this->actingAs($user)
            ->deleteJson("/api/kanban/tasks/{$taskId}")
            ->assertOk();

        $this->assertDatabaseMissing('kanban_tasks', ['id' => $taskId]);
    }

    public function test_users_cannot_update_kanban_tasks_owned_by_other_users(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = $owner->kanbanTasks()->create([
            'task_date' => '2026-06-01',
            'title' => 'Task privato',
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $this->actingAs($otherUser)
            ->putJson("/api/kanban/tasks/{$task->id}", [
                'status' => KanbanTask::STATUS_DONE,
            ])
            ->assertNotFound();
    }

    public function test_deleting_a_kanban_column_moves_tasks_to_first_available_owned_column(): void
    {
        $user = User::factory()->create();

        $board = $this->actingAs($user)
            ->getJson('/api/kanban/board?date=2026-06-01')
            ->assertOk();

        $deletedColumnId = $board->json('columns.0.id');
        $fallbackColumnId = $board->json('columns.1.id');

        $taskResponse = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'kanban_column_id' => $deletedColumnId,
            'title' => 'Task da salvare',
            'status' => KanbanTask::STATUS_TODO,
        ])->assertCreated();

        $this->actingAs($user)
            ->deleteJson("/api/kanban/columns/{$deletedColumnId}")
            ->assertOk();

        $this->assertDatabaseMissing('kanban_columns', ['id' => $deletedColumnId]);
        $this->assertDatabaseHas('kanban_tasks', [
            'id' => $taskResponse->json('data.id'),
            'user_id' => $user->id,
            'kanban_column_id' => $fallbackColumnId,
        ]);
    }

    public function test_authenticated_user_can_manage_daily_and_project_kanban_views(): void
    {
        $user = User::factory()->create();

        $dailyBoard = $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-01')
            ->assertOk();

        $projectResponse = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Universita',
            'icon' => 'book',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Universita')
            ->assertJsonPath('data.slug', 'universita');

        $projectId = $projectResponse->json('data.id');
        $projectSlug = $projectResponse->json('data.slug');

        $projectBoard = $this->actingAs($user)
            ->getJson("/api/kanban/project/{$projectSlug}")
            ->assertOk()
            ->assertJsonCount(3, 'columns');

        $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'project_id' => $projectId,
            'kanban_column_id' => $projectBoard->json('columns.0.id'),
            'title' => 'Preparare esame',
            'status' => KanbanTask::STATUS_TODO,
        ])->assertCreated()
            ->assertJsonPath('data.project_id', $projectId);

        $this->actingAs($user)
            ->getJson('/api/kanban/projects')
            ->assertOk()
            ->assertJsonPath('data.0.tasks_count', 1);

        $this->actingAs($user)
            ->getJson("/api/kanban/project/{$projectSlug}")
            ->assertOk()
            ->assertJsonPath('project.name', 'Universita')
            ->assertJsonPath('project.route_identifier', 'universita')
            ->assertJsonPath('columns.0.tasks.0.title', 'Preparare esame');

        $this->actingAs($user)
            ->getJson("/api/kanban/project/{$projectId}")
            ->assertOk()
            ->assertJsonPath('project.slug', 'universita');

        $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('columns.0.tasks', []);
    }

    public function test_daily_and_project_kanban_columns_are_isolated_but_labels_are_global(): void
    {
        $user = User::factory()->create();

        $dailyBoard = $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-01')
            ->assertOk();

        $dailyColumn = $this->actingAs($user)->postJson('/api/kanban/columns', [
            'title' => 'In revisione',
            'color' => '#14b8a6',
        ])->assertCreated();

        $label = $this->actingAs($user)->postJson('/api/kanban/labels', [
            'name' => 'Urgente davvero',
            'color' => '#ef4444',
        ])->assertCreated();

        $projectId = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Progetto Finale',
            'icon' => 'briefcase',
        ])->assertCreated()->json('data.id');

        $projectBoard = $this->actingAs($user)
            ->getJson("/api/kanban/project/{$projectId}")
            ->assertOk()
            ->assertJsonMissing(['title' => 'In revisione'])
            ->assertJsonPath('labels.4.id', $label->json('data.id'));

        $projectColumn = $this->actingAs($user)->postJson('/api/kanban/columns', [
            'project_id' => $projectId,
            'title' => 'Pronto per review',
            'color' => '#6366f1',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'project_id' => $projectId,
            'kanban_column_id' => $dailyBoard->json('columns.0.id'),
            'title' => 'Non deve passare',
        ])->assertNotFound();

        $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'kanban_column_id' => $projectBoard->json('columns.0.id'),
            'title' => 'Daily in colonna progetto',
        ])->assertNotFound();

        $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('columns.3.id', $dailyColumn->json('data.id'))
            ->assertJsonMissing(['title' => 'Pronto per review']);

        $this->actingAs($user)
            ->getJson("/api/kanban/project/{$projectId}")
            ->assertOk()
            ->assertJsonPath('columns.3.id', $projectColumn->json('data.id'))
            ->assertJsonPath('labels.4.name', 'Urgente davvero');
    }

    public function test_authenticated_user_can_update_and_delete_custom_kanban_projects(): void
    {
        $user = User::factory()->create();

        $projectId = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Progetto iniziale',
            'icon' => 'briefcase',
        ])->assertCreated()->json('data.id');

        $projectBoard = $this->actingAs($user)
            ->getJson("/api/kanban/project/{$projectId}")
            ->assertOk();

        $taskId = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'project_id' => $projectId,
            'kanban_column_id' => $projectBoard->json('columns.0.id'),
            'title' => 'Task progetto',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user)->putJson("/api/projects/{$projectId}", [
            'name' => 'Progetto rinominato',
            'icon' => 'star',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Progetto rinominato');

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'Progetto rinominato',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$projectId}")
            ->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $projectId]);
        $this->assertDatabaseMissing('kanban_tasks', ['id' => $taskId]);
        $this->assertDatabaseMissing('kanban_columns', ['project_id' => $projectId]);
    }

    public function test_custom_reminder_round_trips_in_user_timezone(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Europe/Rome',
        ]);

        $board = $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-01')
            ->assertOk();

        $taskResponse = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'kanban_column_id' => $board->json('columns.0.id'),
            'title' => 'Promemoria locale',
            'due_date' => '2026-06-01',
            'due_time' => '22:00',
            'reminder_option' => 'custom',
            'custom_reminder_at' => '2026-06-01T21:00',
        ])->assertCreated()
            ->assertJsonPath('data.custom_reminder_at', '2026-06-01T21:00');

        $this->assertDatabaseHas('kanban_tasks', [
            'id' => $taskResponse->json('data.id'),
            'reminder_option' => 'custom',
            'custom_reminder_at' => '2026-06-01 19:00:00',
            'reminder_at' => '2026-06-01 19:00:00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('columns.0.tasks.0.custom_reminder_at', '2026-06-01T21:00');
    }

    public function test_diary_upload_failure_explains_the_server_limit(): void
    {
        $user = User::factory()->create(['locale' => 'it']);
        $failedUpload = new UploadedFile(
            __FILE__,
            'cover.png',
            'image/png',
            UPLOAD_ERR_INI_SIZE,
            true,
        );

        $this->actingAs($user)->post('/api/diary-notes', [
            'entry_date' => '2026-06-08',
            'title' => 'Upload non riuscito',
            'body' => 'Contenuto della pagina.',
            'cover_image' => $failedUpload,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_image'])
            ->assertJsonPath(
                'errors.cover_image.0',
                'Non e stato possibile caricare immagine. Il file potrebbe superare il limite consentito dal server.',
            );
    }

    public function test_activity_completion_toggle_updates_profile_stats(): void
    {
        $user = User::factory()->create();

        $firstTask = $user->kanbanTasks()->create([
            'task_date' => '2026-06-01',
            'title' => 'Prima attivita',
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $user->kanbanTasks()->create([
            'task_date' => '2026-06-01',
            'title' => 'Seconda attivita',
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $this->actingAs($user)
            ->postJson("/api/activities/{$firstTask->id}/toggle-complete")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->assertDatabaseHas('kanban_tasks', [
            'id' => $firstTask->id,
            'is_completed' => true,
            'status' => KanbanTask::STATUS_DONE,
        ]);

        $this->actingAs($user)
            ->getJson('/api/stats/profile')
            ->assertOk()
            ->assertJsonPath('kanban.completion_rate', 50)
            ->assertJsonPath('kanban.completed_activities', 1)
            ->assertJsonPath('kanban.total_activities', 2);
    }

    public function test_profile_stats_are_isolated_and_use_consolidated_aggregate_queries(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');

        try {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $user->kanbanTasks()->create([
                'task_date' => '2026-06-15',
                'title' => 'Da fare',
                'status' => KanbanTask::STATUS_TODO,
                'is_completed' => false,
            ]);
            $user->kanbanTasks()->create([
                'task_date' => '2026-06-15',
                'title' => 'Completata',
                'status' => KanbanTask::STATUS_DONE,
                'is_completed' => true,
            ]);
            $user->diaryNotes()->create([
                'entry_date' => '2026-06-15',
                'title' => 'Pagina pubblica',
                'body' => 'Contenuto.',
            ]);
            $user->secretDiaryNotes()->create([
                'entry_date' => '2026-06-15',
                'title' => 'Pagina segreta',
                'body' => 'Contenuto.',
            ]);

            $otherUser->kanbanTasks()->create([
                'task_date' => '2026-06-15',
                'title' => 'Task altro utente',
                'status' => KanbanTask::STATUS_DONE,
                'is_completed' => true,
            ]);
            $otherUser->diaryNotes()->create([
                'entry_date' => '2026-06-15',
                'title' => 'Pagina altro utente',
                'body' => 'Contenuto.',
            ]);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($user)
                ->getJson('/api/stats/profile?period=week&board=all')
                ->assertOk()
                ->assertJsonPath('kanban.total_activities', 2)
                ->assertJsonPath('kanban.completed_activities', 1)
                ->assertJsonPath('kanban.status_breakdown.todo', 1)
                ->assertJsonPath('kanban.status_breakdown.doing', 0)
                ->assertJsonPath('kanban.status_breakdown.done', 1)
                ->assertJsonPath('diary.public_notes', 1)
                ->assertJsonPath('diary.secret_notes', 1)
                ->assertJsonPath('diary.interactions', 2)
                ->assertJsonPath('diary.writing_days', 1)
                ->assertJsonPath('diary.trend.0.total', 2);

            $statsQueries = collect(DB::getQueryLog())
                ->filter(fn (array $query): bool => str_contains($query['query'], 'kanban_tasks')
                    || str_contains($query['query'], 'diary_notes'));

            $this->assertCount(3, $statsQueries);
        } finally {
            DB::disableQueryLog();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_profile_stats_require_an_owned_project_for_project_filtering(): void
    {
        $user = User::factory()->create();
        $otherProject = User::factory()->create()->projects()->create([
            'name' => 'Progetto riservato',
            'slug' => 'progetto-riservato',
            'icon' => 'folder',
        ]);

        $this->actingAs($user)
            ->getJson('/api/stats/profile?board=project')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($user)
            ->getJson('/api/stats/profile?board=all&project_id='.$otherProject->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->actingAs($user)
            ->getJson('/api/stats/profile?board=project&project_id='.$otherProject->id)
            ->assertNotFound();
    }

    public function test_task_reminder_cannot_be_after_due_date(): void
    {
        $user = User::factory()->create();

        $board = $this->actingAs($user)
            ->getJson('/api/kanban/board?date=2026-06-01')
            ->assertOk();

        $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'kanban_column_id' => $board->json('columns.0.id'),
            'title' => 'Task con reminder non valido',
            'due_date' => '2026-06-01',
            'due_time' => '10:00',
            'reminder_option' => 'custom',
            'custom_reminder_at' => '2026-06-01T10:30',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_reminder_at']);

        $this->assertDatabaseMissing('kanban_tasks', [
            'title' => 'Task con reminder non valido',
        ]);
    }

    public function test_custom_reminder_does_not_require_or_set_due_date(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Europe/Rome',
        ]);

        $board = $this->actingAs($user)
            ->getJson('/api/kanban/board?date=2026-06-01')
            ->assertOk();

        $taskResponse = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'kanban_column_id' => $board->json('columns.0.id'),
            'title' => 'Solo promemoria',
            'reminder_option' => 'custom',
            'custom_reminder_at' => '2026-06-05T09:15',
        ])->assertCreated()
            ->assertJsonPath('data.due_date', null)
            ->assertJsonPath('data.custom_reminder_at', '2026-06-05T09:15');

        $this->assertDatabaseHas('kanban_tasks', [
            'id' => $taskResponse->json('data.id'),
            'due_date' => null,
            'reminder_option' => 'custom',
        ]);
    }

    public function test_due_task_reminder_is_sent_with_laravel_mailer(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_notifications_enabled' => true,
        ]);

        $task = $user->kanbanTasks()->create([
            'task_date' => today()->toDateString(),
            'title' => 'Promemoria Laravel',
            'due_date' => today()->toDateString(),
            'due_time' => '18:00',
            'reminder_option' => 'custom',
            'custom_reminder_at' => now('UTC')->subMinute(),
            'reminder_at' => now('UTC')->subMinute(),
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $sent = app(TaskReminderService::class)->sendDueReminders();

        $this->assertSame(1, $sent);
        Queue::assertPushed(SendTaskReminder::class, fn (SendTaskReminder $job): bool => $job->taskId === $task->id);
        $this->assertNull($task->fresh()->reminder_sent_at);
    }

    public function test_task_reminder_selection_is_not_blocked_by_legacy_profile_preference(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_notifications_enabled' => false,
        ]);

        $task = $user->kanbanTasks()->create([
            'task_date' => today()->toDateString(),
            'title' => 'Promemoria scelto nel task',
            'reminder_option' => 'custom',
            'custom_reminder_at' => now('UTC')->subMinute(),
            'reminder_at' => now('UTC')->subMinute(),
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $this->assertSame(1, app(TaskReminderService::class)->sendDueReminders());
        Queue::assertPushed(SendTaskReminder::class, fn (SendTaskReminder $job): bool => $job->taskId === $task->id);
    }

    public function test_due_task_reminder_can_be_sent_immediately_for_smtp_diagnostics(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email_notifications_enabled' => true,
        ]);

        $task = $user->kanbanTasks()->create([
            'task_date' => today()->toDateString(),
            'title' => 'Promemoria immediato',
            'due_date' => today()->toDateString(),
            'due_time' => '18:00',
            'reminder_option' => 'custom',
            'custom_reminder_at' => now('UTC')->subMinute(),
            'reminder_at' => now('UTC')->subMinute(),
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $sent = app(TaskReminderService::class)->sendDueReminders($task->id, true);

        $this->assertSame(1, $sent);
        Mail::assertSent(TaskReminderMail::class, fn (TaskReminderMail $mail): bool => $mail->task->is($task));
        $this->assertNotNull($task->fresh()->reminder_sent_at);
    }
}
