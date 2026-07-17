<?php

namespace App\Http\Resources;

use App\Models\KanbanColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KanbanColumn */
class KanbanColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'color' => $this->color,
            'position' => $this->position,
            'tasks' => $this->relationLoaded('tasks')
                ? KanbanTaskResource::collection($this->tasks)
                : [],
        ];
    }
}
