<?php

namespace App\Mail;

use App\Models\KanbanTask;
use App\Support\TaskReminderDatePresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly ?string $dueAtLabel;

    public readonly ?string $reminderAtLabel;

    public readonly string $userTimezone;

    public function __construct(public readonly KanbanTask $task)
    {
        $task->loadMissing(['column', 'user']);
        $this->userTimezone = TaskReminderDatePresenter::timezone($task);
        $this->reminderAtLabel = TaskReminderDatePresenter::reminderLabel($task);
        $this->dueAtLabel = TaskReminderDatePresenter::dueLabel($task);
    }

    public function envelope(): Envelope
    {
        $subject = ($this->task->user?->locale === 'en' ? 'Reminder' : 'Promemoria').": {$this->task->title}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.task-reminder',
            text: 'emails.task-reminder-text',
            with: [
                'dueAtLabel' => $this->dueAtLabel,
                'reminderAtLabel' => $this->reminderAtLabel,
                'userTimezone' => $this->userTimezone,
            ],
        );
    }
}
