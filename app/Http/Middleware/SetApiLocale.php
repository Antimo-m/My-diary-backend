<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?: strtolower(substr((string) $request->header('Accept-Language', 'it'), 0, 2));

        App::setLocale(in_array($locale, ['it', 'en'], true) ? $locale : 'it');

        return $next($request);
    }
}
