<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\CompanyPasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Forgot-password / reset-password flow using Laravel's built-in Password broker.
 *
 * Uses the existing `password_reset_tokens` table — no extra schema needed.
 */
class PasswordResetController extends Controller
{
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Always show the same success message — never reveal whether the
        // email exists in the system.
        return back()->with('status', __('auth.email_sent'));
    }

    public function showReset(string $token, Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        // Look up the user before validation so the password policy rule
        // can compare the new password against the tenant's history.
        $targetUser = User::where('email', $request->input('email'))->first();

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', new CompanyPasswordPolicy($targetUser)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $history = array_values(array_filter((array) ($user->password_history ?? [])));
                array_unshift($history, $user->password);
                $depth = (int) ($user->company?->securityPolicy()->password_history_count ?? 3);
                $history = array_slice($history, 0, max(1, $depth));

                $user->forceFill([
                    'password' => $password,
                    'password_history' => $history,
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('auth.password_updated'));
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
