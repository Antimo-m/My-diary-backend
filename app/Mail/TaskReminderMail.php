<?php

namespace App\Mail;

use App\Models\KanbanTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly KanbanTask $task)
    {
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
        );
    }
}
