<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KanbanLabel;
use App\Models\KanbanTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function toggleComplete(Request $request, string $id): JsonResponse
    {
        $task = $request->user()
            ->kanbanTasks()
            ->with('labels')
            ->whereKey($id)
            ->firstOrFail();

        $completed = ! $task->is_completed;

        $task->forceFill([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'status' => $completed ? KanbanTask::STATUS_DONE : KanbanTask::STATUS_TODO,
        ])->save();

        return response()->json([
            'message' => $completed ? 'Attivita completata.' : 'Attivita riaperta.',
            'data' => $this->serializeTask($task->fresh('labels')),
        ]);
    }

    private function serializeTask(KanbanTask $task): array
    {
        return [
            'id' => $task->id,
            'task_date' => $task->task_date?->toDateString(),
            'kanban_column_id' => $task->kanban_column_id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toDateString(),
            'due_time' => $task->due_time,
            'color' => $task->color,
            'status' => $task->status,
            'is_completed' => $task->is_completed,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'position' => $task->position,
            'labels' => $task->labels->map(fn (KanbanLabel $label): array => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ])->values(),
        ];
    }
}
