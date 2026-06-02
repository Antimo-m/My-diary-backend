<?php

namespace App\Services;

use App\Mail\TaskReminderMail;
use App\Models\KanbanTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskReminderService
{
    public const OPTIONS = [
        'none',
        'custom',
    ];

    public function calculateReminderAt(array $attributes): ?CarbonImmutable
    {
        $option = $attributes['reminder_option'] ?? null;

        if (! $option || $option === 'none') {
            return null;
        }

        if ($option === 'custom') {
            $reminderAt = empty($attributes['custom_reminder_at'])
                ? null
                : CarbonImmutable::parse($attributes['custom_reminder_at']);

            return $reminderAt;
        }

        return null;
    }

    public function dueAt(array $attributes): ?CarbonImmutable
    {
        if (empty($attributes['due_date'])) {
            return null;
        }

        return CarbonImmutable::parse($attributes['due_date'].' '.($attributes['due_time'] ?? '23:59'));
    }

    public function reminderIsAfterDueDate(array $attributes): bool
    {
        $reminderAt = $this->calculateReminderAt($attributes);
        $dueAt = $this->dueAt($attributes);

        return $reminderAt !== null && $dueAt !== null && $reminderAt->greaterThan($dueAt);
    }

    public function prepareTaskAttributes(array $attributes): array
    {
        if (! array_key_exists('reminder_option', $attributes)) {
            return $attributes;
        }

        $attributes['reminder_at'] = $this->calculateReminderAt($attributes);

        if (($attributes['reminder_option'] ?? null) === 'none') {
            $attributes['custom_reminder_at'] = null;
            $attributes['reminder_sent_at'] = null;
        }

        if (($attributes['reminder_option'] ?? null) === 'custom') {
            $attributes['reminder_sent_at'] = null;
        }

        return $attributes;
    }

    public function sendDueReminders(): int
    {
        $sent = 0;

        Log::info('Task reminder scan started', [
            'now' => now()->toIso8601String(),
        ]);

        $tasks = KanbanTask::query()
            ->with(['column', 'user'])
            ->whereNull('reminder_sent_at')
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereHas('user', fn ($query) => $query->where('email_notifications_enabled', true))
            ->limit(100)
            ->get();

        foreach ($tasks as $task) {
            try {
                $this->sendReminder($task);
                $task->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            } catch (\Throwable $exception) {
                Log::error('Task reminder delivery skipped after failure', [
                    'task_id' => $task->id,
                    'user_id' => $task->user_id,
                    'reminder_at' => $task->reminder_at?->toIso8601String(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function sendReminder(KanbanTask $task): void
    {
        $task->loadMissing(['column', 'user']);

        Log::info('Task reminder email queued for delivery', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'mailer' => config('mail.default'),
            'mail_host_configured' => filled(config('mail.mailers.smtp.host')),
            'mail_username_configured' => filled(config('mail.mailers.smtp.username')),
            'reminder_at' => $task->reminder_at?->toIso8601String(),
        ]);

        Mail::to($task->user->email)->send(new TaskReminderMail($task));
    }
}
