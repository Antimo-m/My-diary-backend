<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiaryNoteController;
use App\Http\Controllers\Api\KanbanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/home', function (Request $request) {
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
            ->with(['tasks' => fn ($query) => $query
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
            'today_tasks' => $user?->kanbanTasks()->whereDate('task_date', today())->count() ?? 0,
        ],
        'recent_notes' => $recentNotes,
        'today_tasks' => $todayTasks,
        'today_columns' => $todayColumns,
        'preview_columns' => [
            ['title' => 'Da fare', 'state' => 'todo'],
            ['title' => 'In corso', 'state' => 'active'],
            ['title' => 'Completato', 'state' => 'done'],
        ],
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('diary-notes', DiaryNoteController::class)
        ->parameters(['diary-notes' => 'note']);

    Route::get('/kanban/board', [KanbanController::class, 'board']);

    Route::post('/kanban/columns', [KanbanController::class, 'storeColumn']);
    Route::put('/kanban/columns/{column}', [KanbanController::class, 'updateColumn']);
    Route::patch('/kanban/columns/order', [KanbanController::class, 'moveColumns']);
    Route::delete('/kanban/columns/{column}', [KanbanController::class, 'destroyColumn']);

    Route::post('/kanban/tasks', [KanbanController::class, 'storeTask']);
    Route::put('/kanban/tasks/{task}', [KanbanController::class, 'updateTask']);
    Route::patch('/kanban/tasks/{task}/move', [KanbanController::class, 'moveTask']);
    Route::delete('/kanban/tasks/{task}', [KanbanController::class, 'destroyTask']);

    Route::post('/kanban/labels', [KanbanController::class, 'storeLabel']);
    Route::put('/kanban/labels/{label}', [KanbanController::class, 'updateLabel']);
    Route::delete('/kanban/labels/{label}', [KanbanController::class, 'destroyLabel']);
});
