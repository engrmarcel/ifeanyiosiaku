<?php

namespace App\Http\Middleware;

use App\Domain\Cms\Http\EmbedController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for responses served by Laravel. The public website sets its own
 * Content-Security-Policy; API and admin responses get a restrictive policy here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->routeIs(EmbedController::ROUTE)) {
            // Custom-code blocks: framed by our own pages only, under the sandbox policy the
            // controller sets (ADR-031). Every other response may never be framed.
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
            $headers->set('Referrer-Policy', 'no-referrer');
        } else {
            $headers->set('X-Frame-Options', 'DENY');
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            if ($request->is('api/*')) {
                $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
            }
        }

        if ($request->user() !== null) {
            // Authenticated responses must never be stored by shared caches.
            $headers->set('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
