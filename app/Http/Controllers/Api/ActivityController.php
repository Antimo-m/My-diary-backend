<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KanbanTaskResource;
use App\Models\KanbanTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function toggleComplete(Request $request, string $id): JsonResponse
    {
        $task = $request->user()
            ->kanbanTasks()
            ->with(['labels', 'user'])
            ->whereKey($id)
            ->firstOrFail();

        $completed = ! $task->is_completed;

        $task->forceFill([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'status' => $completed ? KanbanTask::STATUS_DONE : KanbanTask::STATUS_TODO,
        ])->save();

        return response()->json([
            'message' => $completed ? __('bacheca.task_completed') : __('bacheca.task_reopened'),
            'data' => KanbanTaskResource::make($task->fresh(['labels', 'user'])),
        ]);
    }
}
