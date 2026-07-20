<?php

use Illuminate\Support\Facades\Route;

// Il backend espone solo l'API: la radice risponde con lo stato del servizio
// invece di rimandare al frontend, cosi visitando l'URL del backend si vede
// il backend (health check completo su /up).
Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'ok',
        'api' => url('/api/v1'),
        'frontend' => config('app.frontend_url'),
    ]);
})->name('home');
