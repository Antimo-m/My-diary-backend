<?php

namespace App\Http\Resources;

use App\Models\KanbanTask;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KanbanTask */
class KanbanTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $this->user?->timezone ?? config('app.timezone');

        return [
            'id' => $this->id,
            'task_date' => $this->task_date?->toDateString(),
            'kanban_column_id' => $this->kanban_column_id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),
            'due_time' => $this->due_time,
            'reminder_option' => $this->reminder_option,
            'custom_reminder_at' => self::rawDateTimeForUser($this->resource, 'custom_reminder_at', $timezone),
            'reminder_at' => self::rawDateTimeForUser($this->resource, 'reminder_at', $timezone),
            'reminder_sent_at' => self::rawDateTimeForUser($this->resource, 'reminder_sent_at', $timezone),
            'color' => $this->color,
            'status' => $this->status,
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'position' => $this->position,
            'labels' => KanbanLabelResource::collection($this->labels),
        ];
    }

    /**
     * Present a UTC-stored datetime column in the user's timezone.
     */
    public static function rawDateTimeForUser(KanbanTask $task, string $field, string $timezone): ?string
    {
        $value = $task->getRawOriginal($field);

        return $value ? CarbonImmutable::parse($value, 'UTC')->timezone($timezone)->format('Y-m-d\TH:i') : null;
    }
}
