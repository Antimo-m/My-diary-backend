<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            ? $user->projects()
                ->withCount('tasks')
                ->latest('updated_at')
                ->limit(4)
                ->get()
                ->map(fn ($project): array => [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'route_identifier' => $project->slug ?: (string) $project->id,
                    'name' => $project->name,
                    'icon' => $project->icon,
                    'tasks_count' => $project->tasks_count,
                ])
            : collect();

        return response()->json([
            'app' => [
                'name' => 'My Diary',
                'tagline' => 'Scrivi la giornata, organizza le attivita, ritrova il filo.',
                'description' => 'My Diary unisce note private, pagine visive e una bacheca Kanban fluida per dare forma alla tua giornata.',
                'today' => $today->toDateString(),
                'formatted_today' => $today->format('d/m/Y'),
            ],
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
