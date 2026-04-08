<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Web controller for the TOTP 2FA self-service flow:
 *
 *   GET  /dashboard/two-factor/setup    — show QR + secret for a pending enable
 *   POST /dashboard/two-factor/enable   — verify the first code and turn 2FA on
 *   POST /dashboard/two-factor/disable  — turn 2FA off (requires password)
 *   POST /dashboard/two-factor/recovery — regenerate recovery codes
 *
 * The login flow challenge (step where a user is asked for a code after
 * entering their password) lives in the auth controller — this controller
 * covers the settings page interactions only.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $service)
    {
    }

    /**
     * Show the setup panel. If the user already has a confirmed 2FA, we
     * skip the QR and show the "disable / regenerate recovery codes" UI
     * instead.
     */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // If 2FA is off OR the user never completed setup, issue a fresh
        // secret so the QR is always up-to-date. We store it on the user
        // row but gate the actual "enable" flag behind two_factor_confirmed_at.
        if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            $secret = $this->service->generateSecret();
            $user->forceFill(['two_factor_secret' => $secret])->save();
        } else {
            $secret = $user->two_factor_secret;
        }

        $uri = $this->service->provisioningUri(
            $secret,
            $user->email,
            config('app.name', 'TriLink'),
        );

        return view('dashboard.settings.two-factor', [
            'secret'      => $secret,
            'uri'         => $uri,
            'qrSrc'       => 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($uri),
            'enabled'     => (bool) $user->two_factor_confirmed_at,
            'recoveryCodes' => $user->two_factor_confirmed_at ? (array) ($user->two_factor_recovery_codes ?? []) : [],
        ]);
    }

    /**
     * Verify the first code the user typed from their authenticator app and
     * flip the enabled flag. Also generates the initial set of recovery
     * codes, which the user must write down — they aren't shown again.
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $user->two_factor_secret) {
            return redirect()
                ->route('dashboard.two-factor.setup')
                ->withErrors(['code' => __('two_factor.setup_expired')]);
        }

        if (! $this->service->verify($user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => __('two_factor.invalid_code')]);
        }

        $user->forceFill([
            'two_factor_confirmed_at'    => now(),
            'two_factor_recovery_codes'  => $this->service->generateRecoveryCodes(),
        ])->save();

        return redirect()
            ->route('dashboard.two-factor.setup')
            ->with('status', __('two_factor.enabled'));
    }

    /**
     * Turn 2FA off. Requires a password confirmation so an attacker who
     * hijacks a session can't just disable the second factor.
     */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'password' => ['required', 'current_password:web'],
        ]);

        $user->forceFill([
            'two_factor_secret'          => null,
            'two_factor_recovery_codes'  => null,
            'two_factor_confirmed_at'    => null,
        ])->save();

        return redirect()
            ->route('dashboard.two-factor.setup')
            ->with('status', __('two_factor.disabled'));
    }

    /**
     * Regenerate the 8 recovery codes, invalidating the old ones. Also
     * requires a password confirmation.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'current_password:web'],
        ]);

        if (! $user->two_factor_confirmed_at) {
            return back()->withErrors(['password' => __('two_factor.not_enabled')]);
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $this->service->generateRecoveryCodes(),
        ])->save();

        return back()->with('status', __('two_factor.recovery_regenerated'));
    }
}
