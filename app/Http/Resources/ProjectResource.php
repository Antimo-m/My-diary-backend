<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'route_identifier' => $this->slug ?: (string) $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'tasks_count' => $this->tasks_count ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
