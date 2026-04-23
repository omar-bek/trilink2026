<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBankDetail;
use App\Rules\CompanyPasswordPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Tabbed Settings page — Company Profile / Personal Info / Notifications /
 * Security / Payment Methods / Defaults / Company Security.
 *
 * Access model: COMPANY-WIDE tabs (company, payment, defaults, security_policy,
 * branding, approvals) require manager-level authorisation via CompanyPolicy;
 * PERSONAL tabs (personal, notifications, security) are self-service for every
 * authenticated user. The controller enforces this per-action so a non-manager
 * who renders the page sees read-only company tabs but can still update their
 * own password.
 */
class SettingsController extends Controller
{
    private const TABS = [
        'company', 'personal', 'notifications', 'security',
        'payment', 'defaults', 'security_policy', 'branding', 'approvals',
    ];

    public function index(Request $request): View
    {
        $tab = $this->resolveTab($request->query('tab'));
        $user = $request->user();
        $company = $user?->company;

        // Pre-resolve policy records so the blade can bind form fields
        // without each partial having to call the accessor itself.
        $securityPolicy = $company?->securityPolicy();
        $defaults = $company?->commercialDefaults();

        $canEditCompany = $company && $user->can('update', $company);
        $canManageBilling = $company && $user->can('manageBilling', $company);
        $canManageSecurity = $company && $user->can('manageSecurity', $company);
        $canManageDefaults = $company && $user->can('manageDefaults', $company);
        $canManageBranding = $company && $user->can('manageBranding', $company);
        $canManageApprovals = $company && $user->can('manageApprovals', $company);

        return view('dashboard.settings.index', compact(
            'tab', 'user', 'company', 'securityPolicy', 'defaults',
            'canEditCompany', 'canManageBilling', 'canManageSecurity',
            'canManageDefaults', 'canManageBranding', 'canManageApprovals',
        ));
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $request->user()->can('update', $company) ?: throw new AuthorizationException;

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

        $company->update($data);

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

        $existing = $user->custom_permissions ?? [];
        $existing['notifications'] = [
            'rfq_matches' => $request->boolean('rfq_matches'),
            'bid_updates' => $request->boolean('bid_updates'),
            'contract_milestones' => $request->boolean('contract_milestones'),
            'messages' => $request->boolean('messages'),
            'marketing' => $request->boolean('marketing'),
            'rfq_match_threshold' => max(0, min(100, (int) $request->input('rfq_match_threshold', 50))),
        ];

        $user->update(['custom_permissions' => $existing]);

        return redirect()->route('settings.index', ['tab' => 'notifications'])
            ->with('status', __('settings.saved'));
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', new CompanyPasswordPolicy($user)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('auth.incorrect_password')]);
        }

        // Push the OLD hash onto the history list BEFORE we overwrite the
        // password column, and trim to the configured history depth so
        // the JSON column doesn't grow unbounded.
        $policy = $user->company?->securityPolicy();
        $historyDepth = (int) ($policy->password_history_count ?? 3);
        $history = array_values(array_filter((array) ($user->password_history ?? [])));
        array_unshift($history, $user->password);
        $history = array_slice($history, 0, max(1, $historyDepth));

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_history' => $history,
            'password_changed_at' => now(),
        ])->save();

        return redirect()->route('settings.index', ['tab' => 'security'])
            ->with('status', __('auth.password_updated'));
    }

    /**
     * Save the company-level receiving bank account. Gated by
     * `manageBilling` because a misconfigured IBAN here reroutes every
     * future payout to the wrong destination.
     */
    public function updatePayment(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $request->user()->can('manageBilling', $company) ?: throw new AuthorizationException;

        $data = $request->validate([
            'bank_holder' => ['nullable', 'string', 'max:200'],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'bank_iban' => ['nullable', 'string', 'max:50'],
            'bank_swift' => ['nullable', 'string', 'max:20'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_currency' => ['nullable', 'string', 'size:3'],
        ]);

        CompanyBankDetail::updateOrCreate(
            ['company_id' => $company->id],
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

    public function updateDefaults(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $request->user()->can('manageDefaults', $company) ?: throw new AuthorizationException;

        $data = $request->validate([
            'default_currency' => ['required', 'string', 'size:3'],
            'default_language' => ['required', 'in:en,ar'],
            'default_timezone' => ['required', 'string', 'max:64'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'default_vat_rate' => ['required', 'numeric', 'between:0,100'],
            'default_vat_treatment' => ['required', 'in:standard,zero_rated,exempt,out_of_scope,reverse_charge'],
            'default_payment_terms_days' => ['required', 'integer', 'between:0,365'],
            'late_payment_penalty_percent' => ['required', 'integer', 'between:0,100'],
            'contract_approval_threshold_aed' => ['nullable', 'integer', 'min:0'],
            'payment_dual_approval_threshold_aed' => ['nullable', 'integer', 'min:0'],
            'require_three_quotes_above_threshold' => ['nullable', 'boolean'],
            'three_quotes_threshold_aed' => ['required_with:require_three_quotes_above_threshold', 'integer', 'min:0'],
            'prefer_local_suppliers' => ['nullable', 'boolean'],
            'require_icv_certificate' => ['nullable', 'boolean'],
        ]);

        $defaults = $company->commercialDefaults();
        $defaults->fill([
            ...$data,
            'require_three_quotes_above_threshold' => $request->boolean('require_three_quotes_above_threshold'),
            'prefer_local_suppliers' => $request->boolean('prefer_local_suppliers'),
            'require_icv_certificate' => $request->boolean('require_icv_certificate'),
        ])->save();

        // Legacy mirror — keep the denormalised column on companies in
        // sync with the canonical defaults row for callers that still
        // read directly from Company::approval_threshold_aed.
        $company->update([
            'approval_threshold_aed' => $data['contract_approval_threshold_aed'] ?? null,
        ]);

        return redirect()->route('settings.index', ['tab' => 'defaults'])
            ->with('status', __('settings.saved'));
    }

    public function updateSecurityPolicy(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $request->user()->can('manageSecurity', $company) ?: throw new AuthorizationException;

        $data = $request->validate([
            'enforce_two_factor' => ['nullable', 'boolean'],
            'two_factor_grace_days' => ['required', 'integer', 'between:0,30'],
            'password_min_length' => ['required', 'integer', 'between:8,64'],
            'password_require_mixed_case' => ['nullable', 'boolean'],
            'password_require_number' => ['nullable', 'boolean'],
            'password_require_symbol' => ['nullable', 'boolean'],
            'password_rotation_days' => ['nullable', 'integer', 'between:30,365'],
            'password_history_count' => ['required', 'integer', 'between:0,24'],
            'session_idle_timeout_minutes' => ['required', 'integer', 'between:5,1440'],
            'session_absolute_max_hours' => ['required', 'integer', 'between:1,720'],
            'ip_allowlist_enabled' => ['nullable', 'boolean'],
            'ip_allowlist' => ['nullable', 'string', 'max:2000'],
            'max_login_attempts' => ['required', 'integer', 'between:3,20'],
            'lockout_minutes' => ['required', 'integer', 'between:1,1440'],
            'allowed_email_domains' => ['nullable', 'string', 'max:1000'],
            'audit_retention_days' => ['nullable', 'integer', 'between:30,3650'],
        ]);

        $ipList = $this->parseList($data['ip_allowlist'] ?? '');
        $emailDomains = array_map(
            fn ($d) => ltrim(strtolower($d), '@'),
            $this->parseList($data['allowed_email_domains'] ?? '')
        );

        $policy = $company->securityPolicy();
        $policy->fill([
            'enforce_two_factor' => $request->boolean('enforce_two_factor'),
            'two_factor_grace_days' => $data['two_factor_grace_days'],
            'password_min_length' => $data['password_min_length'],
            'password_require_mixed_case' => $request->boolean('password_require_mixed_case'),
            'password_require_number' => $request->boolean('password_require_number'),
            'password_require_symbol' => $request->boolean('password_require_symbol'),
            'password_rotation_days' => $data['password_rotation_days'] ?? null,
            'password_history_count' => $data['password_history_count'],
            'session_idle_timeout_minutes' => $data['session_idle_timeout_minutes'],
            'session_absolute_max_hours' => $data['session_absolute_max_hours'],
            'ip_allowlist' => $ipList,
            'ip_allowlist_enabled' => $request->boolean('ip_allowlist_enabled') && ! empty($ipList),
            'max_login_attempts' => $data['max_login_attempts'],
            'lockout_minutes' => $data['lockout_minutes'],
            'allowed_email_domains' => $emailDomains,
            'audit_retention_days' => $data['audit_retention_days'] ?? null,
        ])->save();

        return redirect()->route('settings.index', ['tab' => 'security_policy'])
            ->with('status', __('settings.saved'));
    }

    private function resolveCompany(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        return Company::findOrFail($user->company_id);
    }

    /**
     * Parse a newline- or comma-separated free-text list into a cleaned
     * array — used by the IP allowlist and email-domain allowlist form
     * fields, which render as textareas for convenience.
     *
     * @return array<int, string>
     */
    private function parseList(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
    }

    private function resolveTab(?string $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : 'company';
    }
}
