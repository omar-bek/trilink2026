<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach security headers to every response.
 *
 * - X-Frame-Options: prevent clickjacking
 * - X-Content-Type-Options: prevent MIME sniffing
 * - X-XSS-Protection: legacy XSS filter
 * - Referrer-Policy: control referrer leakage
 * - Strict-Transport-Security: enforce HTTPS
 * - Permissions-Policy: restrict browser APIs
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        if (! app()->isLocal()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (config('security.headers_enabled')) {
            $csp = config('security.csp');
            if ($csp) {
                $response->headers->set('Content-Security-Policy', $csp);
            }
        }

        return $response;
    }
}
