<?php

namespace App\Services;

use App\Models\KanbanColumn;
use App\Models\KanbanLabel;
use App\Models\KanbanTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BachecaBoardService
{
    /**
     * Base query for the columns of a board: either a project board or the
     * daily board for the given date.
     */
    public function columnsQuery(User $user, ?int $projectId, ?string $date = null): HasMany
    {
        return $user
            ->kanbanColumns()
            ->when(
                $projectId,
                fn ($query, $id) => $query->where('project_id', $id),
                fn ($query) => $query->whereNull('project_id')->whereDate('date', $date ?? today()->toDateString()),
            );
    }

    /**
     * Return the board columns, seeding the localized defaults on first open.
     */
    public function ensureColumns(User $user, ?int $projectId = null, ?string $date = null): Collection
    {
        $date = $projectId ? null : ($date ?? today()->toDateString());
        $columns = $this->columnsQuery($user, $projectId, $date)
            ->orderBy('position')
            ->get();

        if ($columns->isNotEmpty()) {
            return $columns;
        }

        foreach ([
            ['title' => 'Cose da fare', 'color' => '#1DB874', 'position' => 0, 'project_id' => $projectId, 'date' => $date],
            ['title' => 'In corso', 'color' => '#f97316', 'position' => 1, 'project_id' => $projectId, 'date' => $date],
            ['title' => 'Completate', 'color' => '#22c55e', 'position' => 2, 'project_id' => $projectId, 'date' => $date],
        ] as $default) {
            $user->kanbanColumns()->create($default);
        }

        return $this->columnsQuery($user, $projectId, $date)
            ->orderBy('position')
            ->get();
    }

    /**
     * Return the user's labels, seeding the defaults on first use.
     */
    public function ensureLabels(User $user): Collection
    {
        $labels = $user->kanbanLabels()->orderBy('name')->get();

        if ($labels->isNotEmpty()) {
            return $labels;
        }

        foreach ([
            ['name' => 'Lavoro', 'color' => '#2563eb'],
            ['name' => 'Personale', 'color' => '#ec4899'],
            ['name' => 'Urgente', 'color' => '#ef4444'],
            ['name' => 'Studio', 'color' => '#8b5cf6'],
        ] as $default) {
            $user->kanbanLabels()->create($default);
        }

        return $user->kanbanLabels()->orderBy('name')->get();
    }

    /**
     * Attach legacy status-based tasks (created before columns existed) to the
     * matching column of the daily board.
     */
    public function attachLegacyTasks(User $user, Collection $columns, string $selectedDate, string $nextDate): void
    {
        $hasOrphanTasks = $user
            ->kanbanTasks()
            ->whereNull('kanban_column_id')
            ->whereNull('project_id')
            ->where('task_date', '>=', $selectedDate)
            ->where('task_date', '<', $nextDate)
            ->exists();

        if (! $hasOrphanTasks) {
            return;
        }

        $statusMap = [
            KanbanTask::STATUS_TODO => $columns->get(0)?->id,
            KanbanTask::STATUS_DOING => $columns->get(1)?->id,
            KanbanTask::STATUS_DONE => $columns->get(2)?->id,
        ];

        foreach ($statusMap as $status => $columnId) {
            if (! $columnId) {
                continue;
            }

            $user->kanbanTasks()
                ->whereNull('kanban_column_id')
                ->whereNull('project_id')
                ->where('status', $status)
                ->where('task_date', '>=', $selectedDate)
                ->where('task_date', '<', $nextDate)
                ->update(['kanban_column_id' => $columnId]);
        }
    }

    public function findOwnedColumn(User $user, string $id): KanbanColumn
    {
        return $user->kanbanColumns()->whereKey($id)->firstOrFail();
    }

    public function findOwnedColumnInBoard(User $user, string $id, ?int $projectId, ?string $date = null): KanbanColumn
    {
        return $this->columnsQuery($user, $projectId, $date)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function findOwnedTask(User $user, string $id): KanbanTask
    {
        return $user
            ->kanbanTasks()
            ->with(['labels', 'user'])
            ->whereKey($id)
            ->firstOrFail();
    }

    public function findOwnedLabel(User $user, string $id): KanbanLabel
    {
        return $user->kanbanLabels()->whereKey($id)->firstOrFail();
    }

    public function resolveProjectId(User $user, array $validated): ?int
    {
        if (! array_key_exists('project_id', $validated) || $validated['project_id'] === null) {
            return null;
        }

        return (int) $user
            ->projects()
            ->whereKey($validated['project_id'])
            ->firstOrFail()
            ->id;
    }

    public function resolveColumnId(User $user, array $validated): int
    {
        $projectId = $validated['project_id'] ?? null;
        $date = $projectId ? null : ($validated['task_date'] ?? today()->toDateString());

        if (! empty($validated['kanban_column_id'])) {
            return $this->findOwnedColumnInBoard($user, (string) $validated['kanban_column_id'], $projectId, $date)->id;
        }

        $status = $validated['status'] ?? KanbanTask::STATUS_TODO;
        $index = match ($status) {
            KanbanTask::STATUS_DOING => 1,
            KanbanTask::STATUS_DONE => 2,
            default => 0,
        };
        $columns = $this->ensureColumns($user, $projectId, $date);

        return (int) $columns->get($index)?->id
            ?: (int) $columns->first()->id;
    }

    public function nextTaskPosition(User $user, array $validated): int
    {
        return (int) $user
            ->kanbanTasks()
            ->where('kanban_column_id', $validated['kanban_column_id'])
            ->when(
                $validated['project_id'] ?? null,
                fn ($query, $projectId) => $query->where('project_id', $projectId),
                fn ($query) => $query
                    ->whereNull('project_id')
                    ->where('task_date', '>=', $validated['task_date'])
                    ->where('task_date', '<', CarbonImmutable::parse($validated['task_date'])->addDay()->toDateString()),
            )
            ->max('position') + 1;
    }

    public function ownedLabelIds(User $user, array $labelIds): array
    {
        return $user
            ->kanbanLabels()
            ->whereIn('id', $labelIds)
            ->pluck('id')
            ->all();
    }
}
