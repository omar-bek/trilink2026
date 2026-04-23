<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect($this->landingFor(Auth::user()->role));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);

        // Step 1 — locate the user to apply the company's lockout policy
        // BEFORE we expose the Auth::attempt() side-effects. We still
        // respond with a generic "invalid credentials" message to avoid
        // leaking whether the email exists.
        $user = User::where('email', $credentials['email'])->first();

        if ($user?->locked_until && $user->locked_until->isFuture()) {
            $minutes = max(1, $user->locked_until->diffInMinutes(now()->addMinute()));

            return back()->withErrors([
                'email' => __('auth.account_locked', ['minutes' => $minutes]),
            ])->onlyInput('email');
        }

        $passwordOk = $user && Hash::check($credentials['password'], $user->password);
        $statusOk = $user?->status === UserStatus::ACTIVE;

        if (! $passwordOk || ! $statusOk) {
            if ($user) {
                $this->registerFailedAttempt($user);
            }

            return back()->withErrors([
                'email' => 'The provided credentials are invalid or your account is inactive.',
            ])->onlyInput('email');
        }

        // Successful credential check — clear failure counters, log the
        // user in, rotate the session and stamp the session-start time
        // so EnforceCompanySecurityPolicy knows when the session began.
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login' => now(),
            'last_login_ip' => $request->ip(),
            'session_started_at' => now(),
        ])->save();

        Auth::login($user, $remember);

        $request->session()->regenerate();
        $request->session()->put('_company_session_started', now()->timestamp);
        $request->session()->put('_company_last_activity', now()->timestamp);

        return redirect()->intended($this->landingFor($user->role));
    }

    /**
     * Bump the user's failure counter and lock the account when the
     * company-configured threshold is reached. The lockout window is
     * also sourced from the policy so a stricter tenant can shorten
     * both the attempt budget and the cool-off.
     */
    private function registerFailedAttempt(User $user): void
    {
        $policy = $user->company?->securityPolicy();
        $maxAttempts = (int) ($policy->max_login_attempts ?? 5);
        $lockoutMinutes = (int) ($policy->lockout_minutes ?? 15);

        $attempts = (int) $user->failed_login_attempts + 1;

        $update = ['failed_login_attempts' => $attempts];
        if ($attempts >= $maxAttempts) {
            $update['locked_until'] = now()->addMinutes($lockoutMinutes);
            $update['failed_login_attempts'] = 0;
        }

        $user->forceFill($update)->save();
    }

    private function landingFor($role): string
    {
        $value = $role instanceof UserRole ? $role->value : (string) $role;

        return match ($value) {
            'admin' => route('admin.index'),
            'government' => route('gov.index'),
            default => route('dashboard'),
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
