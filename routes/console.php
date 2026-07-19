<?php

use App\Services\TaskReminderService;
use App\Support\LocalScheduleWorker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kanban:send-reminders {--task= : Invia solo il promemoria del task indicato} {--send-now : Invia subito senza accodare, utile per test SMTP}', function (TaskReminderService $reminders): void {
    $taskId = $this->option('task');
    $sent = $reminders->sendDueReminders($taskId ? (int) $taskId : null, (bool) $this->option('send-now'));

    $this->info("Promemoria inviati: {$sent}");
})->purpose('Invia i promemoria email delle attivita Kanban in scadenza.');

Schedule::command(app()->environment('local') ? 'kanban:send-reminders --send-now' : 'kanban:send-reminders')
    ->everyMinute()
    ->withoutOverlapping(2);

// Ritenzione dei report di errore frontend: vedi FrontendError::prunable().
Schedule::command('model:prune')->daily();

Artisan::command('local:schedule-worker {--parent= : PID del processo php artisan serve}', function (): void {
    $parentPid = (int) $this->option('parent');

    while (true) {
        if ($parentPid > 0 && ! LocalScheduleWorker::processIsRunning($parentPid)) {
            $this->info('Backend server stopped; local schedule worker exiting.');

            return;
        }

        $this->call('schedule:run');

        sleep(max(1, 60 - (int) now()->format('s')));
    }
})->purpose('Esegue lo schedule locale finche il backend di sviluppo resta in ascolto.');
