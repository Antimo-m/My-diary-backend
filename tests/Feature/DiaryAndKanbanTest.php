<?php

namespace Tests\Feature;

use App\Mail\TaskReminderMail;
use App\Models\KanbanTask;
use App\Models\User;
use App\Services\TaskReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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
        $this->getJson('/api/kanban/board')->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_list_update_and_delete_a_diary_note(): void
    {
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
        Storage::disk('public')->assertExists($note->cover_image);

        $this->actingAs($user)
            ->getJson('/api/diary-notes')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Prima nota');

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

    public function test_diary_cover_url_uses_public_storage_relative_path(): void
    {
        $user = User::factory()->create();

        $note = $user->diaryNotes()->create([
            'entry_date' => '2026-06-01',
            'title' => 'Con cover',
            'body' => 'Contenuto con immagine.',
            'cover_image' => 'diary-covers/example.jpg',
        ]);

        $this->assertSame('/storage/diary-covers/example.jpg', $note->coverImageUrl());
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

    public function test_due_task_reminder_is_sent_with_laravel_mailer(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email_notifications_enabled' => true,
        ]);

        $task = $user->kanbanTasks()->create([
            'task_date' => today()->toDateString(),
            'title' => 'Promemoria Laravel',
            'due_date' => today()->toDateString(),
            'due_time' => '18:00',
            'reminder_option' => 'custom',
            'custom_reminder_at' => now()->subMinute(),
            'reminder_at' => now()->subMinute(),
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $sent = app(TaskReminderService::class)->sendDueReminders();

        $this->assertSame(1, $sent);
        Mail::assertSent(TaskReminderMail::class, fn (TaskReminderMail $mail): bool => $mail->task->is($task));
        $this->assertNotNull($task->fresh()->reminder_sent_at);
    }
}
