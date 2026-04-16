<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBankDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Tabbed Settings page — Company Profile / Personal Info / Notifications /
 * Security / Payment Methods.
 *
 * Active tab is selected via `?tab=` query string. Each tab posts to its own
 * action below; we redirect back to the same tab on success/error.
 */
class SettingsController extends Controller
{
    private const TABS = ['company', 'personal', 'notifications', 'security', 'payment'];

    public function index(Request $request): View
    {
        $tab = $this->resolveTab($request->query('tab'));
        $user = $request->user();
        $company = $user?->company;

        return view('dashboard.settings.index', compact('tab', 'user', 'company'));
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
        ]);

        Company::where('id', $user->company_id)->update($data);

        return redirect()->route('settings.index', ['tab' => 'company'])
            ->with('status', __('settings.saved'));
    }

    public function updatePersonal(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        return redirect()->route('settings.index', ['tab' => 'personal'])
            ->with('status', __('settings.saved'));
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Notification preferences are stored in `custom_permissions` JSON column
        // under a `notifications` key — keeps the schema additive.
        $existing = $user->custom_permissions ?? [];
        $existing['notifications'] = [
            'rfq_matches' => $request->boolean('rfq_matches'),
            'bid_updates' => $request->boolean('bid_updates'),
            'contract_milestones' => $request->boolean('contract_milestones'),
            'messages' => $request->boolean('messages'),
            'marketing' => $request->boolean('marketing'),
            // Phase 1 / task 1.7 — minimum match score that the daily
            // saved-search digest will surface. Clamped to 0..100.
            'rfq_match_threshold' => max(0, min(100, (int) $request->input('rfq_match_threshold', 50))),
        ];

        $user->update(['custom_permissions' => $existing]);

        return redirect()->route('settings.index', ['tab' => 'notifications'])
            ->with('status', __('settings.saved'));
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('auth.incorrect_password')]);
        }

        $user->update(['password' => $data['password']]);

        return redirect()->route('settings.index', ['tab' => 'security'])
            ->with('status', __('auth.password_updated'));
    }

    /**
     * Save payment method details.
     *
     * For suppliers this is their RECEIVING bank account (where TriLink sends
     * their earnings). Persisted in the typed `company_bank_details` table
     * (one row per company) that replaced the old `info_request` JSON blob
     * in Phase 0 / task 0.6.
     *
     * For buyers we currently just redirect back (the buyer-side "payment
     * methods" list is a display-only mock until a real card/bank tokenizer
     * is wired).
     */
    public function updatePayment(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $request->validate([
            'bank_holder' => ['nullable', 'string', 'max:200'],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'bank_iban' => ['nullable', 'string', 'max:50'],
            'bank_swift' => ['nullable', 'string', 'max:20'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_currency' => ['nullable', 'string', 'size:3'],
        ]);

        CompanyBankDetail::updateOrCreate(
            ['company_id' => $user->company_id],
            [
                'holder_name' => $data['bank_holder'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'iban' => $data['bank_iban'] ?? null,
                'swift' => $data['bank_swift'] ?? null,
                'notes' => $data['bank_account_number'] ?? null,
                'currency' => $data['bank_currency'] ?? null,
            ]
        );

        return redirect()->route('settings.index', ['tab' => 'payment'])
            ->with('status', __('settings.saved'));
    }

    private function resolveTab(?string $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : 'company';
    }
}
