<?php

namespace App\Support;

use App\Models\KanbanTask;
use Carbon\CarbonImmutable;

class TaskReminderDatePresenter
{
    public static function timezone(KanbanTask $task): string
    {
        return $task->user?->timezone ?: config('app.default_user_timezone');
    }

    public static function reminderLabel(KanbanTask $task): ?string
    {
        $rawReminderAt = $task->getRawOriginal('reminder_at');

        if (! $rawReminderAt) {
            return null;
        }

        return CarbonImmutable::parse($rawReminderAt, 'UTC')
            ->timezone(self::timezone($task))
            ->format('d/m/Y H:i T');
    }

    public static function dueLabel(KanbanTask $task): ?string
    {
        if (! $task->due_date) {
            return null;
        }

        $date = $task->due_date->format('Y-m-d');

        if (! $task->due_time) {
            return CarbonImmutable::parse($date, self::timezone($task))->format('d/m/Y');
        }

        return CarbonImmutable::parse("{$date} {$task->due_time}", self::timezone($task))
            ->format('d/m/Y H:i T');
    }
}
