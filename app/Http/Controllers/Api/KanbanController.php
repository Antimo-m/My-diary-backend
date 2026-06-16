<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KanbanColumn;
use App\Models\KanbanLabel;
use App\Models\KanbanTask;
use App\Models\Project;
use App\Services\TaskReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KanbanController extends Controller
{
    public function __construct(private readonly TaskReminderService $taskReminderService) {}

    public function board(Request $request): JsonResponse
    {
        return $this->daily($request);
    }

    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $selectedDate = $validated['date'] ?? today()->toDateString();
        $nextDate = CarbonImmutable::parse($selectedDate)->addDay()->toDateString();
        $columns = $this->ensureBoardColumns($request, null);
        $labels = $this->ensureBoardLabels($request);
        $this->attachLegacyTasksToColumns($request, $columns);

        $tasks = $request->user()
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
            'columns' => $columns->map(fn (KanbanColumn $column): array => $this->serializeColumn($column, $tasks->get($column->id, collect()))),
            'labels' => $labels->map(fn (KanbanLabel $label): array => $this->serializeLabel($label)),
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
            'data' => $projects->map(fn (Project $project): array => $this->serializeProject($project)),
        ]);
    }

    public function project(Request $request, string $identifier): JsonResponse
    {
        $project = $request->user()
            ->projects()
            ->where(function ($query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->firstOrFail();

        $columns = $this->ensureBoardColumns($request, $project->id);
        $labels = $this->ensureBoardLabels($request);

        $tasks = $request->user()
            ->kanbanTasks()
            ->with(['labels', 'user'])
            ->where('project_id', $project->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('kanban_column_id');

        return response()->json([
            'project' => $this->serializeProject($project),
            'columns' => $columns->map(fn (KanbanColumn $column): array => $this->serializeColumn($column, $tasks->get($column->id, collect()))),
            'labels' => $labels->map(fn (KanbanLabel $label): array => $this->serializeLabel($label)),
        ]);
    }

    public function storeColumn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $validated['project_id'] = $this->resolveProjectId($request, $validated);
        $validated['position'] = (int) $this->boardColumnsQuery($request, $validated['project_id'])->max('position') + 1;
        $validated['color'] = $validated['color'] ?? '#06b6d4';

        $column = $request->user()->kanbanColumns()->create($validated);

        return response()->json([
            'message' => 'Colonna creata.',
            'data' => $this->serializeColumn($column),
        ], 201);
    }

    public function updateColumn(Request $request, string $column): JsonResponse
    {
        $kanbanColumn = $this->findOwnedColumn($request, $column);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $kanbanColumn->update($validated);

        return response()->json([
            'message' => 'Colonna aggiornata.',
            'data' => $this->serializeColumn($kanbanColumn->fresh()),
        ]);
    }

    public function moveColumns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
            'project_id' => ['nullable', 'integer'],
        ]);
        $projectId = $this->resolveProjectId($request, $validated);

        foreach ($validated['ordered_ids'] as $position => $columnId) {
            $this->boardColumnsQuery($request, $projectId)
                ->whereKey($columnId)
                ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Colonne riordinate.']);
    }

    public function destroyColumn(Request $request, string $column): JsonResponse
    {
        $kanbanColumn = $this->findOwnedColumn($request, $column);

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

        return response()->json(['message' => 'Colonna eliminata.']);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $validated = $this->validateTask($request, true);
        $validated['project_id'] = $this->resolveProjectId($request, $validated);
        $validated['task_date'] = $validated['task_date'] ?? today()->toDateString();
        $this->validateReminderTime($validated);
        $validated['kanban_column_id'] = $this->resolveColumnId($request, $validated);
        $validated['status'] = $validated['status'] ?? KanbanTask::STATUS_TODO;
        $validated['position'] = $validated['position'] ?? $this->nextTaskPosition($request, $validated);
        $validated['_timezone'] = $request->user()->timezone ?? config('app.timezone');
        $validated = $this->taskReminderService->prepareTaskAttributes($validated);
        $labelIds = $this->ownedLabelIds($request, $validated['label_ids'] ?? []);
        unset($validated['label_ids']);

        $task = $request->user()->kanbanTasks()->create($validated);
        $task->labels()->sync($labelIds);
        $task->load(['labels', 'user']);

        return response()->json([
            'message' => 'Attivita creata.',
            'data' => $this->serializeTask($task),
        ], 201);
    }

    public function updateTask(Request $request, string $task): JsonResponse
    {
        $kanbanTask = $this->findOwnedTask($request, $task);
        $validated = $this->validateTask($request, false);
        $this->validateReminderTime($this->taskValidationContext($kanbanTask, $validated));

        if (array_key_exists('project_id', $validated)) {
            $validated['project_id'] = $this->resolveProjectId($request, $validated);
        }

        if (array_key_exists('kanban_column_id', $validated)) {
            $validated['kanban_column_id'] = $this->resolveColumnId($request, [
                ...$validated,
                'project_id' => $validated['project_id'] ?? $kanbanTask->project_id,
            ]);
        }

        $validated['_timezone'] = $request->user()->timezone ?? config('app.timezone');
        $validated = $this->taskReminderService->prepareTaskAttributes($validated);

        $labelsWereSubmitted = array_key_exists('label_ids', $validated);
        $labelIds = $labelsWereSubmitted ? $this->ownedLabelIds($request, $validated['label_ids'] ?? []) : [];
        unset($validated['label_ids']);

        $kanbanTask->update($validated);

        if ($labelsWereSubmitted) {
            $kanbanTask->labels()->sync($labelIds);
        }

        $kanbanTask->load(['labels', 'user']);

        return response()->json([
            'message' => 'Attivita aggiornata.',
            'data' => $this->serializeTask($kanbanTask->fresh(['labels', 'user'])),
        ]);
    }

    public function moveTask(Request $request, string $task): JsonResponse
    {
        $kanbanTask = $this->findOwnedTask($request, $task);
        $validated = $request->validate([
            'kanban_column_id' => ['required', 'integer'],
            'status' => ['nullable', Rule::in(KanbanTask::STATUSES)],
            'position' => ['required', 'integer', 'min:0'],
            'ordered_ids' => ['nullable', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);
        $column = $this->findOwnedColumnInProject($request, (string) $validated['kanban_column_id'], $kanbanTask->project_id);

        $kanbanTask->update([
            'kanban_column_id' => $column->id,
            'status' => $validated['status'] ?? $kanbanTask->status,
            'position' => $validated['position'],
        ]);

        foreach ($validated['ordered_ids'] ?? [] as $position => $taskId) {
            $request->user()
                ->kanbanTasks()
                ->whereKey($taskId)
                ->where('kanban_column_id', $column->id)
                ->when(
                    $kanbanTask->project_id,
                    fn ($query, $projectId) => $query->where('project_id', $projectId),
                    fn ($query) => $query
                        ->whereNull('project_id')
                        ->where('task_date', '>=', $kanbanTask->task_date?->toDateString())
                        ->where('task_date', '<', $kanbanTask->task_date?->addDay()->toDateString()),
                )
                ->update(['position' => $position]);
        }

        return response()->json([
            'message' => 'Attivita spostata.',
            'data' => $this->serializeTask($kanbanTask->fresh(['labels', 'user'])),
        ]);
    }

    public function destroyTask(Request $request, string $task): JsonResponse
    {
        $this->findOwnedTask($request, $task)->delete();

        return response()->json(['message' => 'Attivita eliminata.']);
    }

    public function storeLabel(Request $request): JsonResponse
    {
        $validated = $this->validateLabel($request);
        $label = $request->user()->kanbanLabels()->create($validated);

        return response()->json([
            'message' => 'Etichetta creata.',
            'data' => $this->serializeLabel($label),
        ], 201);
    }

    public function updateLabel(Request $request, string $label): JsonResponse
    {
        $kanbanLabel = $this->findOwnedLabel($request, $label);
        $validated = $this->validateLabel($request, $kanbanLabel->id);
        $kanbanLabel->update($validated);

        return response()->json([
            'message' => 'Etichetta aggiornata.',
            'data' => $this->serializeLabel($kanbanLabel->fresh()),
        ]);
    }

    public function destroyLabel(Request $request, string $label): JsonResponse
    {
        $this->findOwnedLabel($request, $label)->delete();

        return response()->json(['message' => 'Etichetta eliminata.']);
    }

    private function validateTask(Request $request, bool $creating): array
    {
        return $request->validate([
            'task_date' => [$creating ? 'required_without:project_id' : 'sometimes', 'date'],
            'project_id' => ['nullable', 'integer'],
            'kanban_column_id' => ['nullable', 'integer'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'reminder_option' => ['nullable', Rule::in(TaskReminderService::OPTIONS)],
            'custom_reminder_at' => ['nullable', 'required_if:reminder_option,custom', 'date'],
            'color' => ['nullable', 'string', 'max:32'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
            'status' => ['nullable', Rule::in(KanbanTask::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function taskValidationContext(KanbanTask $task, array $validated): array
    {
        $task->loadMissing('user');
        $timezone = $task->user?->timezone ?? config('app.timezone');

        return array_merge([
            'due_date' => $task->due_date?->toDateString(),
            'due_time' => $task->due_time,
            'reminder_option' => $task->reminder_option,
            'custom_reminder_at' => $this->formatRawTaskDateTimeForUser($task, 'custom_reminder_at', $timezone),
            '_timezone' => $timezone,
        ], $validated);
    }

    private function validateReminderTime(array $attributes): void
    {
        if (($attributes['reminder_option'] ?? null) !== 'custom') {
            return;
        }

        if ($this->taskReminderService->reminderIsAfterDueDate($attributes)) {
            throw ValidationException::withMessages([
                'custom_reminder_at' => 'Non puoi impostare un promemoria dopo la scadenza dell attivita.',
            ]);
        }
    }

    private function validateLabel(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('kanban_labels', 'name')
                    ->where('user_id', $request->user()->id)
                    ->ignore($ignoreId),
            ],
            'color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);
    }

    private function findOwnedColumn(Request $request, string $id): KanbanColumn
    {
        return $request->user()
            ->kanbanColumns()
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findOwnedColumnInProject(Request $request, string $id, ?int $projectId): KanbanColumn
    {
        return $this->boardColumnsQuery($request, $projectId)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findOwnedTask(Request $request, string $id): KanbanTask
    {
        return $request->user()
            ->kanbanTasks()
            ->with(['labels', 'user'])
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findOwnedLabel(Request $request, string $id): KanbanLabel
    {
        return $request->user()
            ->kanbanLabels()
            ->whereKey($id)
            ->firstOrFail();
    }

    private function resolveProjectId(Request $request, array $validated): ?int
    {
        if (! array_key_exists('project_id', $validated) || $validated['project_id'] === null) {
            return null;
        }

        return (int) $request->user()
            ->projects()
            ->whereKey($validated['project_id'])
            ->firstOrFail()
            ->id;
    }

    private function resolveColumnId(Request $request, array $validated): int
    {
        $projectId = $validated['project_id'] ?? null;

        if (! empty($validated['kanban_column_id'])) {
            return $this->findOwnedColumnInProject($request, (string) $validated['kanban_column_id'], $projectId)->id;
        }

        $status = $validated['status'] ?? KanbanTask::STATUS_TODO;
        $index = match ($status) {
            KanbanTask::STATUS_DOING => 1,
            KanbanTask::STATUS_DONE => 2,
            default => 0,
        };
        $columns = $this->ensureBoardColumns($request, $projectId);

        return (int) $columns->get($index)?->id
            ?: (int) $columns->first()->id;
    }

    private function nextTaskPosition(Request $request, array $validated): int
    {
        return (int) $request->user()
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

    private function boardColumnsQuery(Request $request, ?int $projectId)
    {
        return $request->user()
            ->kanbanColumns()
            ->when(
                $projectId,
                fn ($query, $id) => $query->where('project_id', $id),
                fn ($query) => $query->whereNull('project_id'),
            );
    }

    private function ensureBoardColumns(Request $request, ?int $projectId = null)
    {
        $columns = $this->boardColumnsQuery($request, $projectId)
            ->orderBy('position')
            ->get();

        if ($columns->isNotEmpty()) {
            return $columns;
        }

        foreach ([
            ['title' => 'Da fare', 'color' => '#06b6d4', 'position' => 0, 'project_id' => $projectId],
            ['title' => 'In corso', 'color' => '#f97316', 'position' => 1, 'project_id' => $projectId],
            ['title' => 'Completato', 'color' => '#22c55e', 'position' => 2, 'project_id' => $projectId],
        ] as $default) {
            $request->user()->kanbanColumns()->create($default);
        }

        return $this->boardColumnsQuery($request, $projectId)
            ->orderBy('position')
            ->get();
    }

    private function ensureBoardLabels(Request $request)
    {
        $labels = $request->user()
            ->kanbanLabels()
            ->orderBy('name')
            ->get();

        if ($labels->isNotEmpty()) {
            return $labels;
        }

        foreach ([
            ['name' => 'Lavoro', 'color' => '#2563eb'],
            ['name' => 'Personale', 'color' => '#ec4899'],
            ['name' => 'Urgente', 'color' => '#ef4444'],
            ['name' => 'Studio', 'color' => '#8b5cf6'],
        ] as $default) {
            $request->user()->kanbanLabels()->create($default);
        }

        return $request->user()
            ->kanbanLabels()
            ->orderBy('name')
            ->get();
    }

    private function attachLegacyTasksToColumns(Request $request, $columns): void
    {
        $statusMap = [
            KanbanTask::STATUS_TODO => $columns->get(0)?->id,
            KanbanTask::STATUS_DOING => $columns->get(1)?->id,
            KanbanTask::STATUS_DONE => $columns->get(2)?->id,
        ];

        foreach ($statusMap as $status => $columnId) {
            if (! $columnId) {
                continue;
            }

            $request->user()
                ->kanbanTasks()
                ->whereNull('kanban_column_id')
                ->whereNull('project_id')
                ->where('status', $status)
                ->update(['kanban_column_id' => $columnId]);
        }
    }

    private function ownedLabelIds(Request $request, array $labelIds): array
    {
        return $request->user()
            ->kanbanLabels()
            ->whereIn('id', $labelIds)
            ->pluck('id')
            ->all();
    }

    private function serializeColumn(KanbanColumn $column, $tasks = null): array
    {
        return [
            'id' => $column->id,
            'project_id' => $column->project_id,
            'title' => $column->title,
            'color' => $column->color,
            'position' => $column->position,
            'tasks' => $tasks ? $tasks->map(fn (KanbanTask $task): array => $this->serializeTask($task))->values() : [],
        ];
    }

    private function serializeTask(KanbanTask $task): array
    {
        $timezone = $task->user?->timezone ?? config('app.timezone');

        return [
            'id' => $task->id,
            'task_date' => $task->task_date?->toDateString(),
            'kanban_column_id' => $task->kanban_column_id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toDateString(),
            'due_time' => $task->due_time,
            'reminder_option' => $task->reminder_option,
            'custom_reminder_at' => $this->formatRawTaskDateTimeForUser($task, 'custom_reminder_at', $timezone),
            'reminder_at' => $this->formatRawTaskDateTimeForUser($task, 'reminder_at', $timezone),
            'reminder_sent_at' => $this->formatRawTaskDateTimeForUser($task, 'reminder_sent_at', $timezone),
            'color' => $task->color,
            'status' => $task->status,
            'is_completed' => $task->is_completed,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'position' => $task->position,
            'labels' => $task->labels->map(fn (KanbanLabel $label): array => $this->serializeLabel($label))->values(),
        ];
    }

    private function formatRawTaskDateTimeForUser(KanbanTask $task, string $field, string $timezone): ?string
    {
        $value = $task->getRawOriginal($field);

        return $value ? CarbonImmutable::parse($value, 'UTC')->timezone($timezone)->format('Y-m-d\TH:i') : null;
    }

    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'route_identifier' => $project->slug ?: (string) $project->id,
            'name' => $project->name,
            'icon' => $project->icon,
            'tasks_count' => $project->tasks_count ?? null,
            'created_at' => $project->created_at?->toIso8601String(),
        ];
    }

    private function serializeLabel(KanbanLabel $label): array
    {
        return [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
        ];
    }
}
