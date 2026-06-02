<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KanbanTask extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_DOING = 'doing';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_DOING,
        self::STATUS_DONE,
    ];

    protected $fillable = [
        'task_date',
        'kanban_column_id',
        'title',
        'description',
        'due_date',
        'due_time',
        'reminder_option',
        'custom_reminder_at',
        'reminder_at',
        'reminder_sent_at',
        'color',
        'status',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'due_date' => 'date',
            'custom_reminder_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'kanban_column_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(KanbanLabel::class);
    }
}
