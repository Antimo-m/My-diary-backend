<?php

namespace App\Services\FrontendMonitoring;

use App\Models\FrontendError;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Registrazione dei report inviati dal frontend: sanifica i campi a rischio,
 * arricchisce con i dati noti solo al server e persiste.
 *
 * Volutamente sincrono: l'insert e economico e la coda dell'app e sync.
 * Se in futuro servissero alert o inoltro a terzi, questo e il punto in cui
 * dispatchare un Job o un Event senza toccare il controller.
 */
class FrontendErrorRecorder
{
    public function record(array $report, ?User $user, ?string $ip): FrontendError
    {
        $url = $this->sanitizeUrl($report['url']);
        $message = $report['message'];

        // Il fingerprint del client (calcolato dal modulo React su messaggio
        // normalizzato + frame dello stack) e piu preciso del fallback
        // server-side; il formato e comunque validato dalla FormRequest.
        $fingerprint = $report['fingerprint']
            ?? sha1(($report['source']).'|'.mb_substr($message, 0, 300));

        $error = FrontendError::create([
            'user_id' => $user?->id,
            'kind' => $report['kind'] ?? 'error',
            'fingerprint' => $fingerprint,
            'message' => $message,
            'stack' => $report['stack'] ?? null,
            'component_stack' => $report['component_stack'] ?? null,
            'source' => $report['source'],
            'url' => $url,
            'page' => $this->pageFromUrl($url),
            'route' => $report['route'] ?? null,
            'user_agent' => $report['user_agent'],
            'browser' => $report['browser'] ?? null,
            'os' => $report['os'] ?? null,
            'viewport' => $report['viewport'] ?? null,
            'language' => $report['language'] ?? null,
            'app_version' => $report['app_version'] ?? null,
            'commit_sha' => $report['commit_sha'] ?? null,
            'environment' => $report['environment'] ?? null,
            'data' => $report['data'] ?? null,
            'ip' => $ip,
            'occurred_at' => $report['occurred_at'],
        ]);

        Log::channel('frontend')->error($message, [
            'id' => $error->id,
            'kind' => $error->kind,
            'fingerprint' => $error->fingerprint,
            'source' => $error->source,
            'page' => $error->page,
            'user_id' => $error->user_id,
            'app_version' => $error->app_version,
        ]);

        return $error;
    }

    /**
     * Il fragment non deve mai essere salvato: e il canale con cui l'app
     * riceve i token di reset password.
     */
    private function sanitizeUrl(string $url): string
    {
        return explode('#', $url, 2)[0];
    }

    private function pageFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return mb_substr($path, 0, 255);
    }
}
