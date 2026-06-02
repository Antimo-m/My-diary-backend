<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\TaskReminderService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kanban:send-reminders', function (TaskReminderService $reminders): void {
    $sent = $reminders->sendDueReminders();

    $this->info("Promemoria inviati: {$sent}");
})->purpose('Invia i promemoria email delle attivita Kanban in scadenza.');

Schedule::command('kanban:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();
