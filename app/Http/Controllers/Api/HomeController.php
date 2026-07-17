<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user() ?? $request->user();
        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay();

        $recentProjects = $user
            ? ProjectResource::collection(
                $user->projects()
                    ->withCount('tasks')
                    ->latest('updated_at')
                    ->limit(4)
                    ->get()
            )
            : collect();

        return response()->json([
            'stats' => [
                'notes' => $user?->diaryNotes()->count() ?? 0,
                'today_tasks' => $user?->kanbanTasks()
                    ->whereNull('project_id')
                    ->where('task_date', '>=', $today->toDateString())
                    ->where('task_date', '<', $tomorrow->toDateString())
                    ->count() ?? 0,
                'projects' => $user?->projects()->count() ?? 0,
            ],
            'recent_projects' => $recentProjects,
        ]);
    }
}
