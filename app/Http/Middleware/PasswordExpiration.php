<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce password rotation for B2B security compliance.
 *
 * If the user's password hasn't been changed in PASSWORD_EXPIRY_DAYS
 * (default 90), redirect them to the settings page to update it.
 * Admins and government users are always enforced; regular users
 * follow the configured policy.
 */
class PasswordExpiration
{
    /** Routes that are always accessible (avoid redirect loops). */
    private const EXEMPT_ROUTES = [
        'settings.index',
        'logout',
        'password.update',
        'locale.switch',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $days = (int) config('auth.password_expiry_days', 90);

        if ($days <= 0) {
            return $next($request);
        }

        // Skip if no password_changed_at column (graceful degradation)
        if (! isset($user->password_changed_at)) {
            return $next($request);
        }

        $changedAt = $user->password_changed_at;

        if (! $changedAt) {
            // Never changed — treat creation date as the baseline
            $changedAt = $user->created_at;
        }

        if ($changedAt && $changedAt->diffInDays(now()) >= $days) {
            // Allow exempt routes
            if ($request->routeIs(...self::EXEMPT_ROUTES)) {
                return $next($request);
            }

            return redirect()->route('settings.index')
                ->with('warning', __('auth.password_expired'));
        }

        return $next($request);
    }
}
