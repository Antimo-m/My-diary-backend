<?php

namespace App\Services\FrontendMonitoring;

use App\Models\FrontendError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregazioni per la dashboard "Monitoraggio Errori". Non sostituisce
 * Sentry: fornisce le statistiche aziendali interne (trend, pagine, browser,
 * versioni, utenti coinvolti) sul periodo richiesto.
 */
class FrontendErrorStats
{
    public function forPeriod(int $days): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();
        $base = fn () => FrontendError::where('occurred_at', '>=', $from);

        return [
            'period_days' => $days,
            'totals' => [
                'errors' => $base()->count(),
                'groups' => $base()->distinct('fingerprint')->count('fingerprint'),
                'affected_users' => $base()->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            ],
            'trend' => $base()
                ->select(DB::raw('DATE(occurred_at) as date'), DB::raw('COUNT(*) as total'))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'top_groups' => $base()
                ->select('fingerprint', DB::raw('MAX(message) as message'), DB::raw('MAX(source) as source'), DB::raw('COUNT(*) as total'), DB::raw('MAX(occurred_at) as last_seen'))
                ->groupBy('fingerprint')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'by_browser' => $base()
                ->select(DB::raw("COALESCE(browser, 'Altro') as browser"), DB::raw('COUNT(*) as total'))
                ->groupBy('browser')
                ->orderByDesc('total')
                ->get(),
            'by_page' => $base()
                ->select('page', DB::raw('COUNT(*) as total'))
                ->groupBy('page')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'by_version' => $base()
                ->select(DB::raw("COALESCE(app_version, 'n/d') as app_version"), DB::raw('COUNT(*) as total'))
                ->groupBy('app_version')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }
}
