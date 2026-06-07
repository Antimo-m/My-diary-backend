<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class LocalScheduleWorker
{
    public static function ensureRunning(): void
    {
        if (! self::shouldStart()) {
            return;
        }

        $pidFile = storage_path('framework/schedule-worker.pid');

        if (self::pidFileIsRunning($pidFile)) {
            return;
        }

        self::start($pidFile);
    }

    private static function shouldStart(): bool
    {
        return app()->environment('local')
            && (self::isArtisanServeCommand() || (! app()->runningInConsole() && PHP_SAPI === 'cli-server'))
            && (bool) env('AUTO_START_SCHEDULE_WORKER', true);
    }

    private static function isArtisanServeCommand(): bool
    {
        return app()->runningInConsole()
            && ($_SERVER['argv'][1] ?? null) === 'serve';
    }

    private static function pidFileIsRunning(string $pidFile): bool
    {
        if (! is_file($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid <= 0) {
            return false;
        }

        return self::processIsRunning($pid);
    }

    public static function processIsRunning(int $pid): bool
    {
        return $pid > 0
            && function_exists('posix_kill')
            && posix_kill($pid, 0);
    }

    private static function start(string $pidFile): void
    {
        $logFile = storage_path('logs/schedule-worker.log');
        $command = sprintf(
            'cd %s && %s %s local:schedule-worker --parent=%d >> %s 2>&1 & echo $!',
            escapeshellarg(base_path()),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            getmypid(),
            escapeshellarg($logFile),
        );

        $pid = trim((string) shell_exec($command));

        if ($pid === '' || ! ctype_digit($pid)) {
            Log::warning('Unable to auto-start local schedule worker.');

            return;
        }

        file_put_contents($pidFile, $pid);

        Log::info('Local schedule worker auto-started.', [
            'pid' => (int) $pid,
        ]);
    }
}
