<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Authenticated users carry their language as a column on the
        // user row so queue workers (which have no cookie context) can
        // still render notifications in the right language. The cookie
        // remains the source of truth for guests and the place we
        // detect changes from the language switcher.
        $cookieLocale = $request->cookie('locale', config('app.locale', 'en'));

        if (!in_array($cookieLocale, ['en', 'ar'], true)) {
            $cookieLocale = 'en';
        }

        $user = Auth::user();
        $effective = $cookieLocale;

        if ($user) {
            // Persist on first sight (or whenever the cookie diverges
            // from what we have on file) so the next queued mail uses
            // the new language without waiting for another web hit.
            //
            // Wrapped in try/catch so a missing `locale` column on a
            // fresh clone (migration not yet run) never crashes the
            // request — locale persistence is a nice-to-have for
            // queued mails, the cookie remains the source of truth.
            try {
                if ($user->locale !== $cookieLocale) {
                    $user->forceFill(['locale' => $cookieLocale])->saveQuietly();
                }
                $effective = $user->locale ?: $cookieLocale;
            } catch (\Throwable $e) {
                // Swallow — request must succeed even if persistence fails.
                $effective = $cookieLocale;
            }
        }

        App::setLocale($effective);

        return $next($request);
    }
}
