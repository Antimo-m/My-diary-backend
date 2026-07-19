<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReport extends Model
{
    public const TYPES = ['bug', 'suggestion', 'request', 'problem', 'feedback'];

    public const STATUSES = ['open', 'in_progress', 'resolved', 'dismissed'];

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'subject',
        'message',
        'fingerprint',
        'context',
        'user_agent',
        'ip',
        'assigned_to',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
