<?php

use Illuminate\Support\Facades\Route;

// Il backend espone solo l'API: l'interfaccia (login compreso) vive nella SPA.
Route::get('/', function () {
    return redirect()->away(config('app.frontend_url'));
})->name('home');
