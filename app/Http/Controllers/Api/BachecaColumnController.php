<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bacheca\MoveColumnsRequest;
use App\Http\Requests\Bacheca\StoreColumnRequest;
use App\Http\Requests\Bacheca\UpdateColumnRequest;
use App\Http\Resources\KanbanColumnResource;
use App\Services\BachecaBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BachecaColumnController extends Controller
{
    public function __construct(private readonly BachecaBoardService $boardService) {}

    public function store(StoreColumnRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['project_id'] = $this->boardService->resolveProjectId($user, $validated);
        $validated['date'] = $validated['project_id'] ? null : ($validated['date'] ?? today()->toDateString());
        $validated['position'] = (int) $this->boardService->columnsQuery($user, $validated['project_id'], $validated['date'])->max('position') + 1;
        $validated['color'] = $validated['color'] ?? '#1DB874';

        $column = $user->kanbanColumns()->create($validated);

        return response()->json([
            'message' => __('bacheca.column_created'),
            'data' => KanbanColumnResource::make($column),
        ], 201);
    }

    public function update(UpdateColumnRequest $request, string $column): JsonResponse
    {
        $kanbanColumn = $this->boardService->findOwnedColumn($request->user(), $column);
        $kanbanColumn->update($request->validated());

        return response()->json([
            'message' => __('bacheca.column_updated'),
            'data' => KanbanColumnResource::make($kanbanColumn->fresh()),
        ]);
    }

    public function move(MoveColumnsRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $projectId = $this->boardService->resolveProjectId($user, $validated);
        $date = $projectId ? null : ($validated['date'] ?? today()->toDateString());

        foreach ($validated['ordered_ids'] as $position => $columnId) {
            $this->boardService->columnsQuery($user, $projectId, $date)
                ->whereKey($columnId)
                ->update(['position' => $position]);
        }

        return response()->json(['message' => __('bacheca.columns_reordered')]);
    }

    public function destroy(Request $request, string $column): JsonResponse
    {
        $kanbanColumn = $this->boardService->findOwnedColumn($request->user(), $column);

        DB::transaction(function () use ($kanbanColumn): void {
            $kanbanColumn->tasks()
                ->where('user_id', $kanbanColumn->user_id)
                ->when(
                    $kanbanColumn->project_id,
                    fn ($query, $projectId) => $query->where('project_id', $projectId),
                    fn ($query) => $query->whereNull('project_id'),
                )
                ->delete();

            $kanbanColumn->delete();
        });

        return response()->json(['message' => __('bacheca.column_deleted')]);
    }
}
