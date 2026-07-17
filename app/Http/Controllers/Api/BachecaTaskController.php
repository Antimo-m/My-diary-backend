<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bacheca\MoveTaskRequest;
use App\Http\Requests\Bacheca\StoreTaskRequest;
use App\Http\Requests\Bacheca\UpdateTaskRequest;
use App\Http\Resources\KanbanTaskResource;
use App\Models\KanbanTask;
use App\Services\BachecaBoardService;
use App\Services\TaskReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BachecaTaskController extends Controller
{
    public function __construct(
        private readonly BachecaBoardService $boardService,
        private readonly TaskReminderService $taskReminderService,
    ) {}

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['project_id'] = $this->boardService->resolveProjectId($user, $validated);
        $validated['task_date'] = $validated['task_date'] ?? today()->toDateString();
        $this->validateReminderTime($validated);
        $validated['kanban_column_id'] = $this->boardService->resolveColumnId($user, $validated);
        $validated['status'] = $validated['status'] ?? KanbanTask::STATUS_TODO;
        $validated['position'] = $validated['position'] ?? $this->boardService->nextTaskPosition($user, $validated);
        $validated['_timezone'] = $user->timezone ?? config('app.timezone');
        $validated = $this->taskReminderService->prepareTaskAttributes($validated);
        $labelIds = $this->boardService->ownedLabelIds($user, $validated['label_ids'] ?? []);
        unset($validated['label_ids']);

        $task = $user->kanbanTasks()->create($validated);
        $task->labels()->sync($labelIds);
        $task->load(['labels', 'user']);

        return response()->json([
            'message' => __('bacheca.task_created'),
            'data' => KanbanTaskResource::make($task),
        ], 201);
    }

    public function update(UpdateTaskRequest $request, string $task): JsonResponse
    {
        $user = $request->user();
        $kanbanTask = $this->boardService->findOwnedTask($user, $task);
        $validated = $request->validated();
        $this->validateReminderTime($this->taskValidationContext($kanbanTask, $validated));

        if (array_key_exists('project_id', $validated)) {
            $validated['project_id'] = $this->boardService->resolveProjectId($user, $validated);
        }

        if (array_key_exists('kanban_column_id', $validated)) {
            $validated['kanban_column_id'] = $this->boardService->resolveColumnId($user, [
                ...$validated,
                'project_id' => $validated['project_id'] ?? $kanbanTask->project_id,
                'task_date' => $validated['task_date'] ?? $kanbanTask->task_date?->toDateString(),
            ]);
        }

        $validated['_timezone'] = $user->timezone ?? config('app.timezone');
        $validated = $this->taskReminderService->prepareTaskAttributes($validated);

        $labelsWereSubmitted = array_key_exists('label_ids', $validated);
        $labelIds = $labelsWereSubmitted ? $this->boardService->ownedLabelIds($user, $validated['label_ids'] ?? []) : [];
        unset($validated['label_ids']);

        $kanbanTask->update($validated);

        if ($labelsWereSubmitted) {
            $kanbanTask->labels()->sync($labelIds);
        }

        $kanbanTask->load(['labels', 'user']);

        return response()->json([
            'message' => __('bacheca.task_updated'),
            'data' => KanbanTaskResource::make($kanbanTask->fresh(['labels', 'user'])),
        ]);
    }

    public function move(MoveTaskRequest $request, string $task): JsonResponse
    {
        $user = $request->user();
        $kanbanTask = $this->boardService->findOwnedTask($user, $task);
        $validated = $request->validated();
        $column = $this->boardService->findOwnedColumnInBoard(
            $user,
            (string) $validated['kanban_column_id'],
            $kanbanTask->project_id,
            $kanbanTask->project_id ? null : $kanbanTask->task_date?->toDateString(),
        );

        $kanbanTask->update([
            'kanban_column_id' => $column->id,
            'status' => $validated['status'] ?? $kanbanTask->status,
            'position' => $validated['position'],
        ]);

        foreach ($validated['ordered_ids'] ?? [] as $position => $taskId) {
            $user
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
            'message' => __('bacheca.task_moved'),
            'data' => KanbanTaskResource::make($kanbanTask->fresh(['labels', 'user'])),
        ]);
    }

    public function destroy(Request $request, string $task): JsonResponse
    {
        $this->boardService->findOwnedTask($request->user(), $task)->delete();

        return response()->json(['message' => __('bacheca.task_deleted')]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function taskValidationContext(KanbanTask $task, array $validated): array
    {
        $task->loadMissing('user');
        $timezone = $task->user?->timezone ?? config('app.timezone');

        return array_merge([
            'due_date' => $task->due_date?->toDateString(),
            'due_time' => $task->due_time,
            'reminder_option' => $task->reminder_option,
            'custom_reminder_at' => KanbanTaskResource::rawDateTimeForUser($task, 'custom_reminder_at', $timezone),
            '_timezone' => $timezone,
        ], $validated);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateReminderTime(array $attributes): void
    {
        if (($attributes['reminder_option'] ?? null) !== 'custom') {
            return;
        }

        if ($this->taskReminderService->reminderIsAfterDueDate($attributes)) {
            throw ValidationException::withMessages([
                'custom_reminder_at' => __('bacheca.reminder_after_due'),
            ]);
        }
    }
}
