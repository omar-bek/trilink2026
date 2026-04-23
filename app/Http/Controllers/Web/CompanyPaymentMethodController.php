<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentRail;
use App\Http\Controllers\Controller;
use App\Models\CompanyPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Matrix of accepted/rejected payment rails per company. Each row the
 * manager ticks here becomes a CompanyPaymentMethod; the PaymentService
 * consults this table before rendering the settlement-method dropdown
 * so a tenant that refuses PayPal or Stripe never sees them as options.
 */
class CompanyPaymentMethodController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $this->authorize($request);

        $existing = $company->paymentMethods()->get()->keyBy(fn ($m) => $m->rail->value);
        $receivingAccounts = $company->bankAccounts()->where('status', 'active')->get();

        return view('dashboard.settings.payment-methods.edit', [
            'rails' => PaymentRail::cases(),
            'existing' => $existing,
            'receivingAccounts' => $receivingAccounts,
            'company' => $company,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->authorize($request);

        $rows = $request->input('rails', []);
        foreach (PaymentRail::cases() as $rail) {
            $row = $rows[$rail->value] ?? [];

            CompanyPaymentMethod::updateOrCreate(
                ['company_id' => $company->id, 'rail' => $rail->value],
                [
                    'accept_incoming' => ! empty($row['accept_incoming']),
                    'allow_outgoing' => ! empty($row['allow_outgoing']),
                    'min_amount_aed' => $this->intOrNull($row['min_amount_aed'] ?? null),
                    'max_amount_aed' => $this->intOrNull($row['max_amount_aed'] ?? null),
                    'preferred_above_aed' => $this->intOrNull($row['preferred_above_aed'] ?? null),
                    'require_dual_approval' => ! empty($row['require_dual_approval']),
                    'receiving_account_id' => $this->intOrNull($row['receiving_account_id'] ?? null),
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        return redirect()->route('settings.payment-methods.edit')
            ->with('status', __('settings.saved'));
    }

    private function authorize(Request $request): \App\Models\Company
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageBilling', $company), 403);

        return $company;
    }

    private function intOrNull(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }
}
