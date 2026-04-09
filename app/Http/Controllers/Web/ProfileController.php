<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();
        $user?->loadMissing('company');

        return view('dashboard.profile.edit', ['user' => $user]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($data);

        return redirect()->route('profile.edit')->with('status', __('profile.updated_successfully'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('auth.incorrect_password')]);
        }

        $user->update(['password' => $data['password']]);

        return redirect()->route('profile.edit')->with('status', __('auth.password_updated'));
    }

    public function updateCompanyLogo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user?->company;

        abort_unless($company, 404, 'No company associated with this account.');

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);

        // Remove the previous logo file if it exists.
        if ($company->logo && Storage::disk('public')->exists($company->logo)) {
            Storage::disk('public')->delete($company->logo);
        }

        $path = $request->file('logo')->store('companies/logos', 'public');
        $company->update(['logo' => $path]);

        return redirect()->route('profile.edit')->with('status', __('profile.logo_updated'));
    }

    /**
     * Sprint D.17 — persist per-user notification preferences. The form
     * only sends a small payload (channel toggles + digest mode) and we
     * shape it server-side into the canonical JSON read by
     * User::deliveryChannelsFor() so the model is the single source of
     * truth for what gets delivered where.
     */
    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel_database' => ['nullable', 'boolean'],
            'channel_mail'     => ['nullable', 'boolean'],
            'digest_mode'      => ['required', 'in:realtime,daily,off'],
        ]);

        $user = $request->user();

        // Read-modify-write so we don't trample any per-type overrides
        // a future iteration may add via the API.
        $current = $user->notification_preferences ?? \App\Models\User::defaultNotificationPreferences();
        $current['channels'] = [
            'database' => (bool) ($data['channel_database'] ?? false),
            'mail'     => (bool) ($data['channel_mail'] ?? false),
        ];
        $current['digest'] = ['mode' => $data['digest_mode']];

        // At least one channel must remain on — silently re-enabling the
        // database channel is the right call here. Losing all channels
        // would mean the user simply never finds out about anything,
        // which is worse than a non-honoured "off" toggle.
        if (!$current['channels']['database'] && !$current['channels']['mail']) {
            $current['channels']['database'] = true;
        }

        $user->update(['notification_preferences' => $current]);

        return redirect()->route('profile.edit')->with('status', __('profile.notifications_updated'));
    }
}
