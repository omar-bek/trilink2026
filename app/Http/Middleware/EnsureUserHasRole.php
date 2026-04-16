<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-guard role middleware that checks the User::role enum column directly.
 *
 * Spatie's role middleware operates on its own guard-aware roles table; our
 * RolesAndPermissionsSeeder seeds those under the `api` guard, so it cannot
 * authorize web/session-based requests. This middleware sidesteps that by
 * trusting the role column on the User model — which is the source of truth
 * regardless of which auth guard performed the login.
 *
 * Usage in routes: ->middleware('web.role:buyer')   or   'web.role:buyer,supplier'
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Authorize against the user's primary role AND any secondary roles
        // their company manager has granted (e.g. a buyer who is also a
        // supplier on the same account).
        $owned = method_exists($user, 'allRoles')
            ? $user->allRoles()
            : [$user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role];

        if (empty(array_intersect($owned, $roles))) {
            abort(403, 'Forbidden: your role does not allow this action.');
        }

        return $next($request);
    }
}
