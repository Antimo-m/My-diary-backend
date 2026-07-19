<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontendError extends Model
{
    use Prunable;

    protected $fillable = [
        'user_id',
        'kind',
        'fingerprint',
        'message',
        'stack',
        'component_stack',
        'source',
        'url',
        'page',
        'route',
        'user_agent',
        'browser',
        'os',
        'viewport',
        'language',
        'app_version',
        'commit_sha',
        'environment',
        'data',
        'ip',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * I report sono dati diagnostici, non storici: la ritenzione e definita
     * in config/monitoring.php (model:prune schedulato in routes/console.php).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('monitoring.retention_days')));
    }
}
