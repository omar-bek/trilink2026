<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        unset($credentials['remember']);
        $credentials['status'] = UserStatus::ACTIVE->value;

        if (!Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'The provided credentials are invalid or your account is inactive.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Each role lands on its own tailored entry point. `intended()` honors
        // a URL the user was originally trying to reach (set by auth middleware).
        return redirect()->intended($this->landingFor(Auth::user()->role));
    }

    /**
     * Map a role to the URL that role should land on after login.
     */
    private function landingFor($role): string
    {
        $value = $role instanceof UserRole ? $role->value : (string) $role;

        return match ($value) {
            'admin'      => route('admin.index'),
            'government' => route('gov.index'),
            default      => route('dashboard'),
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
