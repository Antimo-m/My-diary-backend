<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\DiaryNoteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\KanbanController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SecretDiaryAuthController;
use App\Http\Controllers\Api\SecretDiaryNoteController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/home', [HomeController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('diary-notes', DiaryNoteController::class)
        ->parameters(['diary-notes' => 'note']);

    Route::get('/secret-diary/status', [SecretDiaryAuthController::class, 'status']);
    Route::post('/secret-diary/setup', [SecretDiaryAuthController::class, 'setup'])->middleware('throttle:5,1');
    Route::post('/secret-diary/unlock', [SecretDiaryAuthController::class, 'unlock'])->middleware('throttle:5,1');
    Route::post('/secret-diary/lock', [SecretDiaryAuthController::class, 'lock']);
    Route::post('/secret-diary/forgot-password', [SecretDiaryAuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/secret-diary/reset-password', [SecretDiaryAuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::apiResource('secret-diary/notes', SecretDiaryNoteController::class)
        ->middleware('secret-diary.unlocked')
        ->parameters(['notes' => 'note']);

    Route::get('/kanban/board', [KanbanController::class, 'board']);
    Route::get('/kanban/daily', [KanbanController::class, 'daily']);
    Route::get('/kanban/projects', [KanbanController::class, 'projects']);
    Route::get('/kanban/project/{id}', [KanbanController::class, 'project']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::post('/activities/{id}/toggle-complete', [ActivityController::class, 'toggleComplete']);
    Route::get('/stats/profile', [StatsController::class, 'profile']);

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
