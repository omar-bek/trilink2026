<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        /*
        |--------------------------------------------------------------------------
        | Authorization gate fallback
        |--------------------------------------------------------------------------
        |
        | This `before` callback runs before any defined Gate ability check
        | (including @can in Blade and $user->can() in code). It lets us:
        |
        |   1. Short-circuit admins to always-allowed (return true).
        |   2. Resolve any unknown ability name as a permission key against
        |      User::hasPermission(), which itself consults Spatie role-based
        |      permissions seeded by RolesAndPermissionsSeeder.
        |
        | Returning null falls through to any explicitly-defined Gate, so this
        | does not break callers that register specific abilities.
        */
        Gate::before(function (?User $user, string $ability) {
            if (!$user) {
                return null;
            }
            if ($user->isAdmin()) {
                return true;
            }
            // Only treat dotted ability names as permission keys (e.g.
            // "payments.approve") so we don't accidentally hijack policy
            // method names like "view" or "update".
            if (str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }
            return null;
        });
    }
}
