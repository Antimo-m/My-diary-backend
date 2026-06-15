<?php

namespace Tests\Feature;

use App\Mail\TaskReminderMail;
use App\Jobs\SendTaskReminder;
use App\Models\KanbanTask;
use App\Models\User;
use App\Services\TaskReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class TaskReminderTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_summer_reminder_is_stored_in_utc_and_rendered_in_rome_time(): void
    {
        [$user, $columnId] = $this->userAndColumn('Europe/Rome');

        $response = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-08',
            'kanban_column_id' => $columnId,
            'title' => 'Promemoria estivo',
            'reminder_option' => 'custom',
            'custom_reminder_at' => '2026-06-08T14:30',
        ])->assertCreated()
            ->assertJsonPath('data.custom_reminder_at', '2026-06-08T14:30');

        $task = KanbanTask::with(['column', 'user'])->findOrFail($response->json('data.id'));

        $this->assertSame('2026-06-08 12:30:00', $task->getRawOriginal('reminder_at'));

        $mail = new TaskReminderMail($task);
        $mail->assertSeeInHtml('08/06/2026 14:30 CEST');
        $mail->assertSeeInText('Promemoria: 08/06/2026 14:30 CEST');
    }

    public function test_winter_reminder_uses_the_one_hour_rome_offset(): void
    {
        [$user, $columnId] = $this->userAndColumn('Europe/Rome');

        $response = $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-01-15',
            'kanban_column_id' => $columnId,
            'title' => 'Promemoria invernale',
            'reminder_option' => 'custom',
            'custom_reminder_at' => '2026-01-15T14:30',
        ])->assertCreated();

        $task = KanbanTask::with(['column', 'user'])->findOrFail($response->json('data.id'));

        $this->assertSame('2026-01-15 13:30:00', $task->getRawOriginal('reminder_at'));
        (new TaskReminderMail($task))
            ->assertSeeInHtml('15/01/2026 14:30 CET')
            ->assertSeeInText('Promemoria: 15/01/2026 14:30 CET');
    }

    public function test_utc_reminder_can_render_on_the_next_day_without_date_drift(): void
    {
        [$user] = $this->userAndColumn('Europe/Rome');
        $task = $user->kanbanTasks()->create([
            'task_date' => '2026-06-08',
            'title' => 'Cambio giorno',
            'reminder_option' => 'custom',
            'custom_reminder_at' => CarbonImmutable::parse('2026-06-08 22:30:00', 'UTC'),
            'reminder_at' => CarbonImmutable::parse('2026-06-08 22:30:00', 'UTC'),
            'status' => KanbanTask::STATUS_TODO,
        ])->load('user');

        (new TaskReminderMail($task))
            ->assertSeeInHtml('09/06/2026 00:30 CEST')
            ->assertSeeInText('Promemoria: 09/06/2026 00:30 CEST');
    }

    public function test_queued_mailable_keeps_the_user_timezone_presentation(): void
    {
        [$user] = $this->userAndColumn('Europe/Rome');
        $task = $user->kanbanTasks()->create([
            'task_date' => '2026-06-08',
            'title' => 'Promemoria in coda',
            'reminder_option' => 'custom',
            'custom_reminder_at' => CarbonImmutable::parse('2026-06-08 12:30:00', 'UTC'),
            'reminder_at' => CarbonImmutable::parse('2026-06-08 12:30:00', 'UTC'),
            'status' => KanbanTask::STATUS_TODO,
        ])->load('user');

        $restoredMail = unserialize(serialize(new TaskReminderMail($task)));

        $restoredMail
            ->assertSeeInHtml('08/06/2026 14:30 CEST')
            ->assertSeeInText('Promemoria: 08/06/2026 14:30 CEST');
    }

    public function test_due_scan_compares_utc_instants(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-08 12:30:00', 'UTC'));
        [$user] = $this->userAndColumn('Europe/Rome');
        $task = $user->kanbanTasks()->create([
            'task_date' => '2026-06-08',
            'title' => 'Scansione UTC',
            'reminder_option' => 'custom',
            'custom_reminder_at' => CarbonImmutable::parse('2026-06-08 12:30:00', 'UTC'),
            'reminder_at' => CarbonImmutable::parse('2026-06-08 12:30:00', 'UTC'),
            'status' => KanbanTask::STATUS_TODO,
        ]);

        $this->assertSame(1, app(TaskReminderService::class)->sendDueReminders($task->id));
        Queue::assertPushed(SendTaskReminder::class, fn (SendTaskReminder $job): bool => $job->taskId === $task->id);
        $this->assertNull($task->fresh()->reminder_sent_at);

        CarbonImmutable::setTestNow();
    }

    public function test_queue_job_marks_the_reminder_only_after_mail_delivery(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-08 12:30:00', 'UTC'));
        [$user] = $this->userAndColumn('Europe/Rome');
        $task = $user->kanbanTasks()->create([
            'task_date' => '2026-06-08',
            'title' => 'Consegna completata',
            'reminder_option' => 'custom',
            'custom_reminder_at' => now('UTC'),
            'reminder_at' => now('UTC'),
            'status' => KanbanTask::STATUS_TODO,
        ]);

        (new SendTaskReminder($task->id))->handle(app(TaskReminderService::class));

        Mail::assertSent(TaskReminderMail::class, fn (TaskReminderMail $mail): bool => $mail->task->is($task));
        $this->assertSame('2026-06-08 12:30:00', $task->fresh()->getRawOriginal('reminder_sent_at'));

        CarbonImmutable::setTestNow();
    }

    public function test_queue_job_does_not_mark_a_failed_delivery_as_sent(): void
    {
        [$user] = $this->userAndColumn('Europe/Rome');
        $task = $user->kanbanTasks()->create([
            'task_date' => '2026-06-08',
            'title' => 'Consegna fallita',
            'reminder_option' => 'custom',
            'custom_reminder_at' => now('UTC'),
            'reminder_at' => now('UTC'),
            'status' => KanbanTask::STATUS_TODO,
        ]);
        $reminders = $this->mock(TaskReminderService::class);
        $reminders->shouldReceive('sendReminder')
            ->once()
            ->andThrow(new RuntimeException('SMTP non disponibile'));

        try {
            (new SendTaskReminder($task->id))->handle($reminders);
            $this->fail('Il job avrebbe dovuto propagare il fallimento SMTP.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP non disponibile', $exception->getMessage());
        }

        $this->assertNull($task->fresh()->reminder_sent_at);
    }

    public function test_application_and_mysql_connection_are_configured_for_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Europe/Rome', config('app.default_user_timezone'));
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
    }

    private function userAndColumn(string $timezone): array
    {
        $user = User::factory()->create([
            'locale' => 'it',
            'timezone' => $timezone,
        ]);

        $board = $this->actingAs($user)
            ->getJson('/api/kanban/daily?date=2026-06-08')
            ->assertOk();

        return [$user, $board->json('columns.0.id')];
    }
}
