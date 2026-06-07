<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user() ?? $request->user();

        $recentNotes = $user
            ? $user->diaryNotes()
                ->latest('entry_date')
                ->latest('id')
                ->limit(4)
                ->get()
                ->map(fn ($note): array => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'excerpt' => Str::limit($note->body ?: 'Pagina ancora vuota, pronta per essere riempita.', 145),
                    'entry_date' => $note->entry_date?->toDateString(),
                    'formatted_date' => $note->entry_date?->translatedFormat('d F Y'),
                    'cover_image_url' => $note->coverImageUrl(),
                ])
            : collect();

        $todayTasks = $user
            ? $user->kanbanTasks()
                ->whereNull('project_id')
                ->whereDate('task_date', today())
                ->latest('updated_at')
                ->limit(6)
                ->get()
                ->map(fn ($task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'color' => $task->color,
                    'due_date' => $task->due_date?->toDateString(),
                    'due_time' => $task->due_time,
                    'status' => $task->status,
                    'kanban_column_id' => $task->kanban_column_id,
                ])
            : collect();

        $todayColumns = $user
            ? $user->kanbanColumns()
                ->whereNull('project_id')
                ->with(['tasks' => fn ($query) => $query
                    ->whereNull('project_id')
                    ->whereDate('task_date', today())
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(6)])
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn ($column): array => [
                    'id' => $column->id,
                    'title' => $column->title,
                    'color' => $column->color,
                    'tasks' => $column->tasks->map(fn ($task): array => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'color' => $task->color,
                        'due_date' => $task->due_date?->toDateString(),
                        'due_time' => $task->due_time,
                        'status' => $task->status,
                    ]),
                ])
            : collect();

        $recentProjects = $user
            ? $user->projects()
                ->withCount('tasks')
                ->latest('updated_at')
                ->limit(4)
                ->get()
                ->map(fn ($project): array => [
                    'id' => $project->id,
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
                'today' => today()->toDateString(),
                'formatted_today' => today()->format('d/m/Y'),
            ],
            'stats' => [
                'notes' => $user?->diaryNotes()->count() ?? 0,
                'today_tasks' => $user?->kanbanTasks()->whereNull('project_id')->whereDate('task_date', today())->count() ?? 0,
                'projects' => $user?->projects()->count() ?? 0,
            ],
            'recent_notes' => $recentNotes,
            'recent_projects' => $recentProjects,
            'today_tasks' => $todayTasks,
            'today_columns' => $todayColumns,
            'preview_columns' => [
                ['title' => 'Da fare', 'state' => 'todo'],
                ['title' => 'In corso', 'state' => 'active'],
                ['title' => 'Completato', 'state' => 'done'],
            ],
        ]);
    }
}
