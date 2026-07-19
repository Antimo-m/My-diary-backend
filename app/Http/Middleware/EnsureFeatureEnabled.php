<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Applica un feature flag a una rotta: 'feature:monitoring'.
     * 404 e non 403: una funzionalita spenta deve sembrare inesistente.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! config("features.{$feature}", false)) {
            abort(404);
        }

        return $next($request);
    }
}
