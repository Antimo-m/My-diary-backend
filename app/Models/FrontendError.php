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
        'fingerprint',
        'message',
        'stack',
        'component_stack',
        'source',
        'url',
        'page',
        'user_agent',
        'browser',
        'app_version',
        'ip',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * I report sono dati diagnostici, non storici: dopo 90 giorni non servono
     * piu (model:prune va schedulato in routes/console.php).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
