<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated dashboard request to enforce the three
 * policy knobs the company manager controls:
 *
 *   1. IP allowlist       — bounces requests from outside the listed
 *                           CIDR ranges with a 403 page.
 *   2. Session idle       — logs the user out if no request arrived
 *                           within the configured idle window.
 *   3. Session max age    — forces re-login after the absolute session
 *                           cap (covers "laptop stolen a week ago").
 *   4. 2FA enforcement    — once the grace period expires, any user
 *                           without two_factor_confirmed_at is bounced
 *                           to /two-factor/setup until they enrol.
 *
 * Kept intentionally small — the auth flow itself (AuthController) is
 * responsible for rejecting logins that already violate the policy. This
 * middleware is the post-login enforcement layer that catches sessions
 * that were established before a policy change.
 */
class EnforceCompanySecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->company_id) {
            return $next($request);
        }

        $policy = $user->company?->securityPolicy();
        if (! $policy) {
            return $next($request);
        }

        // Skip enforcement on the logout / 2FA-setup routes themselves
        // — otherwise a user locked out by the policy has no way to
        // resolve the violation.
        $route = $request->route()?->getName() ?? '';
        $isEscapeHatch = in_array($route, [
            'logout',
            'dashboard.two-factor.setup',
            'dashboard.two-factor.enable',
            'dashboard.two-factor.recovery',
        ], true);

        if ($policy->ip_allowlist_enabled && ! empty($policy->ip_allowlist) && ! $isEscapeHatch) {
            if (! IpUtils::checkIp($request->ip() ?? '', $policy->ip_allowlist)) {
                Auth::logout();
                $request->session()->invalidate();
                abort(403, __('settings.ip_not_allowed'));
            }
        }

        $now = now();
        $idleLimitSeconds = (int) $policy->session_idle_timeout_minutes * 60;
        $absoluteLimitSeconds = (int) $policy->session_absolute_max_hours * 3600;

        $lastActivity = $request->session()->get('_company_last_activity');
        $sessionStarted = $request->session()->get('_company_session_started', $now->timestamp);

        if ($lastActivity && ($now->timestamp - (int) $lastActivity) > $idleLimitSeconds) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('error', __('auth.session_idle_expired'));
        }

        if (($now->timestamp - (int) $sessionStarted) > $absoluteLimitSeconds) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('error', __('auth.session_max_age_exceeded'));
        }

        $request->session()->put('_company_last_activity', $now->timestamp);
        $request->session()->put('_company_session_started', $sessionStarted);

        if ($policy->enforce_two_factor && ! $user->two_factor_confirmed_at && ! $isEscapeHatch) {
            $graceSeconds = (int) $policy->two_factor_grace_days * 86400;
            $createdAt = $user->created_at?->timestamp ?? $now->timestamp;
            if (($now->timestamp - $createdAt) > $graceSeconds) {
                return redirect()->route('dashboard.two-factor.setup')
                    ->with('error', __('auth.two_factor_required'));
            }
        }

        return $next($request);
    }
}
