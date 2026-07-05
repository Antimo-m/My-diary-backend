<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Apply hardening headers to every response. HSTS is only emitted over
     * HTTPS so local HTTP development is not permanently pinned to TLS, and
     * the Content-Security-Policy is tightened for JSON/API responses while
     * staying document-friendly for the server-rendered Blade pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()'
        );

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";
        }

        return "frame-ancestors 'none'; object-src 'none'; base-uri 'self'";
    }
}
