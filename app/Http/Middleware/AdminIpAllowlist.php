<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 8 (UAE Compliance Roadmap) — NESA IP allowlisting for admin
 * routes. When SECURITY_ADMIN_IP_ALLOWLIST is configured, only
 * requests from listed IPs (or CIDR ranges) may reach the admin
 * dashboard. All others get a 403.
 *
 * When the env var is EMPTY or not set, the middleware is a no-op —
 * every IP is allowed. This is the default for dev/staging where
 * developers work from varying IPs.
 *
 * Config:
 *   SECURITY_ADMIN_IP_ALLOWLIST=10.0.0.0/8,203.0.113.42
 *
 * Usage in bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->appendToGroup('admin', [AdminIpAllowlist::class]);
 *   })
 */
class AdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = array_filter(array_map(
            'trim',
            explode(',', (string) config('security.admin_ip_allowlist', ''))
        ));

        if (empty($allowlist)) {
            return $next($request);
        }

        $clientIp = (string) $request->ip();

        foreach ($allowlist as $allowed) {
            if ($this->ipMatches($clientIp, $allowed)) {
                return $next($request);
            }
        }

        abort(403, 'Access denied — your IP is not in the admin allowlist.');
    }

    private function ipMatches(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
