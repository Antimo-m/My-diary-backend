<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\UserReport;
use App\Services\Audit\AuditLogger;

/**
 * Segnalazioni degli utenti (bug, suggerimenti, richieste, problemi,
 * feedback). Modulo separato dal monitoraggio automatico: qui parla l'utente,
 * la' parla il codice. Il ponte tra i due mondi e il fingerprint.
 */
class UserReportService
{
    // Solo queste chiavi del contesto tecnico client sopravvivono: mai
    // passthrough del JSON arbitrario inviato dal browser.
    private const CONTEXT_KEYS = ['url', 'route', 'browser', 'os', 'viewport', 'language', 'app_version', 'environment'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function submit(array $data, User $user, ?string $userAgent, ?string $ip): UserReport
    {
        $context = collect($data['context'] ?? [])
            ->only(self::CONTEXT_KEYS)
            ->map(fn ($value) => mb_substr((string) $value, 0, 500))
            ->all();

        // Il fragment di un URL non va mai persistito (token di reset).
        if (isset($context['url'])) {
            $context['url'] = explode('#', $context['url'], 2)[0];
        }

        return UserReport::create([
            'user_id' => $user->id,
            'status' => 'open',
            'type' => $data['type'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'fingerprint' => $data['fingerprint'] ?? null,
            'context' => $context ?: null,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'ip' => $ip,
        ]);
    }

    /**
     * Aggiornamento amministrativo (stato, assegnazione, nota interna):
     * ogni modifica lascia traccia nel registro audit.
     */
    public function update(UserReport $report, array $changes, User $actor, ?string $ip): UserReport
    {
        $original = $report->only(['status', 'assigned_to', 'admin_note']);

        $report->fill($changes)->save();

        $this->audit->record('report.updated', $actor, $report, [
            'from' => $original,
            'to' => $report->only(['status', 'assigned_to', 'admin_note']),
        ], $ip);

        return $report;
    }
}
