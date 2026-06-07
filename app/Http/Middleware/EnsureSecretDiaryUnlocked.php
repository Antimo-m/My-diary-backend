<?php

namespace App\Http\Middleware;

use App\Support\SecretDiarySession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecretDiaryUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SecretDiarySession::isUnlocked($request)) {
            return response()->json([
                'message' => __('secret_diary.locked'),
            ], 423);
        }

        return $next($request);
    }
}
