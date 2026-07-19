<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BachecaBoardController;
use App\Http\Controllers\Api\BachecaColumnController;
use App\Http\Controllers\Api\BachecaLabelController;
use App\Http\Controllers\Api\BachecaTaskController;
use App\Http\Controllers\Api\DiaryNoteController;
use App\Http\Controllers\Api\FrontendErrorController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SecretDiaryAuthController;
use App\Http\Controllers\Api\SecretDiaryNoteController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

// The same route map is exposed both unversioned (legacy clients and stable
// asset URLs like the diary covers) and under the /v1 prefix used by the SPA.
$apiV1Routes = function (): void {
    Route::get('/home', [HomeController::class, 'show'])->middleware('throttle:api-read');

    // Raccolta crash del frontend: pubblica per catturare anche gli errori
    // pre-login, protetta dal throttle dedicato.
    Route::post('/frontend-errors', [FrontendErrorController::class, 'store'])->middleware('throttle:frontend-errors');

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

        Route::get('/bacheca/board', [BachecaBoardController::class, 'board'])->middleware('throttle:api-read');
        Route::get('/bacheca/daily', [BachecaBoardController::class, 'daily'])->middleware('throttle:api-read');
        Route::get('/bacheca/projects', [BachecaBoardController::class, 'projects'])->middleware('throttle:api-read');
        Route::get('/bacheca/project/{identifier}', [BachecaBoardController::class, 'project'])->middleware('throttle:api-read');
        Route::post('/projects', [ProjectController::class, 'store'])->middleware('throttle:api-write');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('throttle:api-write');
        Route::post('/activities/{id}/toggle-complete', [ActivityController::class, 'toggleComplete'])->middleware('throttle:api-write');
        Route::get('/stats/profile', [StatsController::class, 'profile'])->middleware('throttle:stats');

        Route::post('/bacheca/columns', [BachecaColumnController::class, 'store'])->middleware('throttle:api-write');
        Route::put('/bacheca/columns/{column}', [BachecaColumnController::class, 'update'])->middleware('throttle:api-write');
        Route::patch('/bacheca/columns/order', [BachecaColumnController::class, 'move'])->middleware('throttle:api-write');
        Route::delete('/bacheca/columns/{column}', [BachecaColumnController::class, 'destroy'])->middleware('throttle:api-write');

        Route::post('/bacheca/tasks', [BachecaTaskController::class, 'store'])->middleware('throttle:api-write');
        Route::put('/bacheca/tasks/{task}', [BachecaTaskController::class, 'update'])->middleware('throttle:api-write');
        Route::patch('/bacheca/tasks/{task}/move', [BachecaTaskController::class, 'move'])->middleware('throttle:api-write');
        Route::delete('/bacheca/tasks/{task}', [BachecaTaskController::class, 'destroy'])->middleware('throttle:api-write');

        Route::post('/bacheca/labels', [BachecaLabelController::class, 'store'])->middleware('throttle:api-write');
        Route::put('/bacheca/labels/{label}', [BachecaLabelController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('/bacheca/labels/{label}', [BachecaLabelController::class, 'destroy'])->middleware('throttle:api-write');

        Route::middleware('admin')->prefix('monitoring')->group(function (): void {
            Route::get('/errors', [FrontendErrorController::class, 'index'])->middleware('throttle:api-read');
            Route::get('/errors/stats', [FrontendErrorController::class, 'stats'])->middleware('throttle:api-read');
        });
    });
};

Route::group([], $apiV1Routes);
Route::prefix('v1')->group($apiV1Routes);
