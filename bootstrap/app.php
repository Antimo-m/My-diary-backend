<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureSecretDiaryUnlocked;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        // Backend API-only: niente pagina di login lato server, i guest ricevono 401.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('api', [
            SetApiLocale::class,
        ]);
        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'role' => EnsureUserHasRole::class,
            'secret-diary.unlocked' => EnsureSecretDiaryUnlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
