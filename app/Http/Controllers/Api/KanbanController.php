<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KanbanColumn;
use App\Models\KanbanLabel;
use App\Models\KanbanTask;
use App\Services\TaskReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KanbanController extends Controller
{
    public function __construct(private readonly TaskReminderService $taskReminderService)
    {
    }

    public function board(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $selectedDate = $validated['date'] ?? today()->toDateString();
        $columns = $this->ensureBoardColumns($request);
        $labels = $this->ensureBoardLabels($request);
        $this->attachLegacyTasksToColumns($request, $columns);

        $tasks = $request->user()
            ->kanbanTasks()
            ->with('labels')
            ->whereDate('task_date', $selectedDate)
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

    public function storeColumn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $validated['position'] = (int) $request->user()->kanbanColumns()->max('position') + 1;
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
        ]);

        foreach ($validated['ordered_ids'] as $position => $columnId) {
            $request->user()
                ->kanbanColumns()
                ->whereKey($columnId)
                ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Colonne riordinate.']);
    }

    public function destroyColumn(Request $request, string $column): JsonResponse
    {
        $kanbanColumn = $this->findOwnedColumn($request, $column);
        $fallbackColumn = $request->user()
            ->kanbanColumns()
            ->where('id', '!=', $kanbanColumn->id)
            ->orderBy('position')
            ->first();

        if (! $fallbackColumn) {
            return response()->json([
                'message' => 'Non puoi eliminare l ultima colonna della board.',
            ], 422);
        }

        $kanbanColumn->tasks()->update(['kanban_column_id' => $fallbackColumn->id]);
        $kanbanColumn->delete();

        return response()->json(['message' => 'Colonna eliminata.']);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $validated = $this->validateTask($request, true);
        $this->validateReminderTime($validated);
        $validated['kanban_column_id'] = $this->resolveColumnId($request, $validated);
        $validated['status'] = $validated['status'] ?? KanbanTask::STATUS_TODO;
        $validated['position'] = $validated['position'] ?? $this->nextTaskPosition($request, $validated);
        $validated = $this->taskReminderService->prepareTaskAttributes($validated);
        $labelIds = $this->ownedLabelIds($request, $validated['label_ids'] ?? []);
        unset($validated['label_ids']);

        $task = $request->user()->kanbanTasks()->create($validated);
        $task->labels()->sync($labelIds);
        $task->load('labels');

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

        if (array_key_exists('kanban_column_id', $validated)) {
            $validated['kanban_column_id'] = $this->resolveColumnId($request, $validated);
        }

        $validated = $this->taskReminderService->prepareTaskAttributes($validated);

        $labelsWereSubmitted = array_key_exists('label_ids', $validated);
        $labelIds = $labelsWereSubmitted ? $this->ownedLabelIds($request, $validated['label_ids'] ?? []) : [];
        unset($validated['label_ids']);

        $kanbanTask->update($validated);

        if ($labelsWereSubmitted) {
            $kanbanTask->labels()->sync($labelIds);
        }

        $kanbanTask->load('labels');

        return response()->json([
            'message' => 'Attivita aggiornata.',
            'data' => $this->serializeTask($kanbanTask->fresh('labels')),
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
        $column = $this->findOwnedColumn($request, (string) $validated['kanban_column_id']);

        $kanbanTask->update([
            'kanban_column_id' => $column->id,
            'status' => $validated['status'] ?? $kanbanTask->status,
            'position' => $validated['position'],
        ]);

        foreach ($validated['ordered_ids'] ?? [] as $position => $taskId) {
            $request->user()
                ->kanbanTasks()
                ->whereKey($taskId)
                ->whereDate('task_date', $kanbanTask->task_date)
                ->where('kanban_column_id', $column->id)
                ->update(['position' => $position]);
        }

        return response()->json([
            'message' => 'Attivita spostata.',
            'data' => $this->serializeTask($kanbanTask->fresh('labels')),
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
            'task_date' => [$creating ? 'required' : 'sometimes', 'date'],
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
        return array_merge([
            'due_date' => $task->due_date?->toDateString(),
            'due_time' => $task->due_time,
            'reminder_option' => $task->reminder_option,
            'custom_reminder_at' => $task->custom_reminder_at?->format('Y-m-d\TH:i'),
        ], $validated);
    }

    private function validateReminderTime(array $attributes): void
    {
        if (($attributes['reminder_option'] ?? null) !== 'custom') {
            return;
        }

        if (empty($attributes['due_date'])) {
            throw ValidationException::withMessages([
                'due_date' => 'Imposta prima la scadenza dell attivita.',
            ]);
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

    private function findOwnedTask(Request $request, string $id): KanbanTask
    {
        return $request->user()
            ->kanbanTasks()
            ->with('labels')
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

    private function resolveColumnId(Request $request, array $validated): int
    {
        if (! empty($validated['kanban_column_id'])) {
            return $this->findOwnedColumn($request, (string) $validated['kanban_column_id'])->id;
        }

        $status = $validated['status'] ?? KanbanTask::STATUS_TODO;
        $index = match ($status) {
            KanbanTask::STATUS_DOING => 1,
            KanbanTask::STATUS_DONE => 2,
            default => 0,
        };
        $columns = $this->ensureBoardColumns($request);

        return (int) $columns->get($index)?->id
            ?: (int) $columns->first()->id;
    }

    private function nextTaskPosition(Request $request, array $validated): int
    {
        return (int) $request->user()
            ->kanbanTasks()
            ->whereDate('task_date', $validated['task_date'])
            ->where('kanban_column_id', $validated['kanban_column_id'])
            ->max('position') + 1;
    }

    private function ensureBoardColumns(Request $request)
    {
        $columns = $request->user()
            ->kanbanColumns()
            ->orderBy('position')
            ->get();

        if ($columns->isNotEmpty()) {
            return $columns;
        }

        foreach ([
            ['title' => 'Da fare', 'color' => '#06b6d4', 'position' => 0],
            ['title' => 'In corso', 'color' => '#f97316', 'position' => 1],
            ['title' => 'Completato', 'color' => '#22c55e', 'position' => 2],
        ] as $default) {
            $request->user()->kanbanColumns()->create($default);
        }

        return $request->user()
            ->kanbanColumns()
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
            'title' => $column->title,
            'color' => $column->color,
            'position' => $column->position,
            'tasks' => $tasks ? $tasks->map(fn (KanbanTask $task): array => $this->serializeTask($task))->values() : [],
        ];
    }

    private function serializeTask(KanbanTask $task): array
    {
        return [
            'id' => $task->id,
            'task_date' => $task->task_date?->toDateString(),
            'kanban_column_id' => $task->kanban_column_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toDateString(),
            'due_time' => $task->due_time,
            'reminder_option' => $task->reminder_option,
            'custom_reminder_at' => $task->custom_reminder_at?->format('Y-m-d\TH:i'),
            'reminder_at' => $task->reminder_at?->format('Y-m-d\TH:i'),
            'reminder_sent_at' => $task->reminder_sent_at?->format('Y-m-d\TH:i'),
            'color' => $task->color,
            'status' => $task->status,
            'position' => $task->position,
            'labels' => $task->labels->map(fn (KanbanLabel $label): array => $this->serializeLabel($label))->values(),
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
