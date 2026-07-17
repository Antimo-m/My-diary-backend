<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KanbanColumnResource;
use App\Http\Resources\KanbanLabelResource;
use App\Http\Resources\ProjectResource;
use App\Models\KanbanColumn;
use App\Services\BachecaBoardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BachecaBoardController extends Controller
{
    public function __construct(private readonly BachecaBoardService $boardService) {}

    public function board(Request $request): JsonResponse
    {
        return $this->daily($request);
    }

    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $selectedDate = $validated['date'] ?? today()->toDateString();
        $nextDate = CarbonImmutable::parse($selectedDate)->addDay()->toDateString();
        $columns = $this->boardService->ensureColumns($user, null, $selectedDate);
        $labels = $this->boardService->ensureLabels($user);
        $this->boardService->attachLegacyTasks($user, $columns, $selectedDate, $nextDate);

        $tasks = $user
            ->kanbanTasks()
            ->with(['labels', 'user'])
            ->whereNull('project_id')
            ->where('task_date', '>=', $selectedDate)
            ->where('task_date', '<', $nextDate)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('kanban_column_id');

        return response()->json([
            'date' => $selectedDate,
            'columns' => KanbanColumnResource::collection(
                $columns->each(fn (KanbanColumn $column) => $column->setRelation('tasks', $tasks->get($column->id, collect())))
            ),
            'labels' => KanbanLabelResource::collection($labels),
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $projects = $request->user()
            ->projects()
            ->withCount('tasks')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ProjectResource::collection($projects),
        ]);
    }

    public function project(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $project = $user
            ->projects()
            ->where(function ($query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->firstOrFail();

        $columns = $this->boardService->ensureColumns($user, $project->id);
        $labels = $this->boardService->ensureLabels($user);

        $tasks = $user
            ->kanbanTasks()
            ->with(['labels', 'user'])
            ->where('project_id', $project->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('kanban_column_id');

        return response()->json([
            'project' => ProjectResource::make($project),
            'columns' => KanbanColumnResource::collection(
                $columns->each(fn (KanbanColumn $column) => $column->setRelation('tasks', $tasks->get($column->id, collect())))
            ),
            'labels' => KanbanLabelResource::collection($labels),
        ]);
    }
}
