<?php

namespace App\Jobs;

use App\Models\KanbanTask;
use App\Services\TaskReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTaskReminder implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $taskId) {}

    public function handle(TaskReminderService $reminders): void
    {
        $task = KanbanTask::with(['column', 'user'])->find($this->taskId);

        if (! $task || $task->reminder_sent_at) {
            return;
        }

        $reminders->sendReminder($task);
        $task->forceFill(['reminder_sent_at' => now('UTC')])->save();
    }

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }
}
