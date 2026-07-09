<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiaryNoteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\KanbanController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SecretDiaryAuthController;
use App\Http\Controllers\Api\SecretDiaryNoteController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/home', [HomeController::class, 'show'])->middleware('throttle:api-read');

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user'])->middleware('throttle:api-read');
    Route::put('/user', [AuthController::class, 'updateUser'])->middleware('throttle:api-write');
    Route::delete('/user', [AuthController::class, 'destroyAccount'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:api-write');

    Route::get('/diary-notes', [DiaryNoteController::class, 'index'])->middleware('throttle:api-read');
    Route::get('/diary-notes/{note}/cover', [DiaryNoteController::class, 'cover'])->middleware('throttle:api-read');
    Route::get('/diary-notes/{note}', [DiaryNoteController::class, 'show'])->middleware('throttle:api-read');
    Route::post('/diary-notes', [DiaryNoteController::class, 'store'])->middleware('throttle:api-write');
    Route::match(['put', 'patch'], '/diary-notes/{note}', [DiaryNoteController::class, 'update'])->middleware('throttle:api-write');
    Route::delete('/diary-notes/{note}', [DiaryNoteController::class, 'destroy'])->middleware('throttle:api-write');

    Route::get('/secret-diary/status', [SecretDiaryAuthController::class, 'status']);
    Route::post('/secret-diary/setup', [SecretDiaryAuthController::class, 'setup'])->middleware('throttle:5,1');
    Route::post('/secret-diary/unlock', [SecretDiaryAuthController::class, 'unlock'])->middleware('throttle:5,1');
    Route::post('/secret-diary/lock', [SecretDiaryAuthController::class, 'lock']);
    Route::post('/secret-diary/forgot-password', [SecretDiaryAuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/secret-diary/reset-password', [SecretDiaryAuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::middleware('secret-diary.unlocked')->group(function (): void {
        Route::get('/secret-diary/notes', [SecretDiaryNoteController::class, 'index'])->middleware('throttle:api-read');
        Route::get('/secret-diary/notes/{note}/cover', [SecretDiaryNoteController::class, 'cover'])->middleware('throttle:api-read');
        Route::get('/secret-diary/notes/{note}', [SecretDiaryNoteController::class, 'show'])->middleware('throttle:api-read');
        Route::post('/secret-diary/notes', [SecretDiaryNoteController::class, 'store'])->middleware('throttle:api-write');
        Route::match(['put', 'patch'], '/secret-diary/notes/{note}', [SecretDiaryNoteController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('/secret-diary/notes/{note}', [SecretDiaryNoteController::class, 'destroy'])->middleware('throttle:api-write');
    });

    Route::get('/bacheca/board', [KanbanController::class, 'board'])->middleware('throttle:api-read');
    Route::get('/bacheca/daily', [KanbanController::class, 'daily'])->middleware('throttle:api-read');
    Route::get('/bacheca/projects', [KanbanController::class, 'projects'])->middleware('throttle:api-read');
    Route::get('/bacheca/project/{identifier}', [KanbanController::class, 'project'])->middleware('throttle:api-read');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('throttle:api-write');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('throttle:api-write');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('throttle:api-write');
    Route::post('/activities/{id}/toggle-complete', [ActivityController::class, 'toggleComplete'])->middleware('throttle:api-write');
    Route::get('/stats/profile', [StatsController::class, 'profile'])->middleware('throttle:stats');

    Route::post('/bacheca/columns', [KanbanController::class, 'storeColumn'])->middleware('throttle:api-write');
    Route::put('/bacheca/columns/{column}', [KanbanController::class, 'updateColumn'])->middleware('throttle:api-write');
    Route::patch('/bacheca/columns/order', [KanbanController::class, 'moveColumns'])->middleware('throttle:api-write');
    Route::delete('/bacheca/columns/{column}', [KanbanController::class, 'destroyColumn'])->middleware('throttle:api-write');

    Route::post('/bacheca/tasks', [KanbanController::class, 'storeTask'])->middleware('throttle:api-write');
    Route::put('/bacheca/tasks/{task}', [KanbanController::class, 'updateTask'])->middleware('throttle:api-write');
    Route::patch('/bacheca/tasks/{task}/move', [KanbanController::class, 'moveTask'])->middleware('throttle:api-write');
    Route::delete('/bacheca/tasks/{task}', [KanbanController::class, 'destroyTask'])->middleware('throttle:api-write');

    Route::post('/bacheca/labels', [KanbanController::class, 'storeLabel'])->middleware('throttle:api-write');
    Route::put('/bacheca/labels/{label}', [KanbanController::class, 'updateLabel'])->middleware('throttle:api-write');
    Route::delete('/bacheca/labels/{label}', [KanbanController::class, 'destroyLabel'])->middleware('throttle:api-write');
});
