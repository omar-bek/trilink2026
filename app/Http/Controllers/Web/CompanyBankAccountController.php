<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanyBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manager-only CRUD for the tenant's receiving / payout bank accounts.
 * Replaces the single-row CompanyBankDetail flow for companies that
 * operate multiple currencies or branches. Gated by the CompanyPolicy
 * manageBilling check — the same gate the legacy bank-details form
 * uses, so permission rollout stays consistent.
 */
class CompanyBankAccountController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->authorize($request);

        $accounts = $company->bankAccounts()->withTrashed()
            ->orderByDesc('is_default_receiving')
            ->orderBy('currency')
            ->orderBy('label')
            ->get();

        return view('dashboard.settings.bank-accounts.index', compact('accounts', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->authorize($request);
        $data = $this->validateAccount($request);

        DB::transaction(function () use ($company, $data) {
            $account = $company->bankAccounts()->create($data + ['status' => 'active']);
            $this->promoteDefaults($company->id, $account);
        });

        return redirect()->route('settings.bank-accounts.index')
            ->with('status', __('settings.saved'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $company = $this->authorize($request);
        $account = CompanyBankAccount::where('company_id', $company->id)->findOrFail($id);
        $data = $this->validateAccount($request, $account->id);

        DB::transaction(function () use ($company, $account, $data) {
            $account->update($data);
            $this->promoteDefaults($company->id, $account);
        });

        return redirect()->route('settings.bank-accounts.index')
            ->with('status', __('settings.saved'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $company = $this->authorize($request);
        $account = CompanyBankAccount::where('company_id', $company->id)->findOrFail($id);

        abort_if($account->is_default_receiving, 422, __('settings.cannot_delete_default_account'));
        $account->delete();

        return back()->with('status', __('settings.deleted'));
    }

    private function authorize(Request $request): \App\Models\Company
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageBilling', $company), 403);

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'holder_name' => ['required', 'string', 'max:200'],
            'bank_name' => ['required', 'string', 'max:200'],
            'iban' => ['nullable', 'string', 'max:50'],
            'swift' => ['nullable', 'string', 'max:20'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3'],
            'is_default_receiving' => ['nullable', 'boolean'],
            'is_default_payout' => ['nullable', 'boolean'],
            'is_wps_account' => ['nullable', 'boolean'],
            'is_tax_account' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach (['is_default_receiving', 'is_default_payout', 'is_wps_account', 'is_tax_account'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }

    /**
     * A company can have only one default for each of the four role
     * flags (receiving/payout/wps/tax). When one row claims default we
     * flip the same flag off on the rest so the constraint is soft but
     * consistent.
     */
    private function promoteDefaults(int $companyId, CompanyBankAccount $account): void
    {
        foreach (['is_default_receiving', 'is_default_payout', 'is_wps_account', 'is_tax_account'] as $flag) {
            if ($account->{$flag}) {
                CompanyBankAccount::where('company_id', $companyId)
                    ->where('id', '!=', $account->id)
                    ->update([$flag => false]);
            }
        }
    }
}
