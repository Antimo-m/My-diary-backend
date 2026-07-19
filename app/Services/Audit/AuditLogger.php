<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro delle operazioni sensibili (append-only). Da chiamare nei punti
 * amministrativi o irreversibili: cancellazioni, cambi ruolo, modifiche di
 * configurazione, lavorazione segnalazioni. Le azioni usano il formato
 * "soggetto.verbo" (es. account.deleted, role.changed, report.dismissed).
 */
class AuditLogger
{
    public function record(string $action, ?User $actor = null, ?Model $subject = null, array $meta = [], ?string $ip = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }
}
