<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Gate per ruolo: 'role:admin' oppure 'role:admin,developer'.
     * super_admin passa sempre; la FASE 5 (gestionale con OTP) si appoggera
     * a questo stesso middleware rendendo l'accesso piu severo.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role;

        if ($userRole !== 'super_admin' && ! in_array($userRole, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
