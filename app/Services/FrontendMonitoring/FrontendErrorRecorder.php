<?php

namespace App\Services\FrontendMonitoring;

use App\Models\FrontendError;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Registrazione dei report di errore inviati dal frontend: sanifica i campi
 * a rischio, arricchisce con i dati noti solo al server e persiste.
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

        $error = FrontendError::create([
            'user_id' => $user?->id,
            'fingerprint' => sha1($report['source'].'|'.mb_substr($message, 0, 300)),
            'message' => $message,
            'stack' => $report['stack'] ?? null,
            'component_stack' => $report['component_stack'] ?? null,
            'source' => $report['source'],
            'url' => $url,
            'page' => $this->pageFromUrl($url),
            'user_agent' => $report['user_agent'],
            'browser' => $report['browser'] ?? null,
            'app_version' => $report['app_version'] ?? null,
            'ip' => $ip,
            'occurred_at' => $report['occurred_at'],
        ]);

        Log::channel('frontend')->error($message, [
            'id' => $error->id,
            'fingerprint' => $error->fingerprint,
            'source' => $error->source,
            'page' => $error->page,
            'user_id' => $error->user_id,
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
