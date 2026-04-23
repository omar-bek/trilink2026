<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Contract;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Models\Payment;
use App\Services\Escrow\BankPartnerException;
use App\Services\EscrowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 3 — buyer-facing escrow workflow. Every action goes through
 * EscrowService, which owns the bank API call + ledger write + Payment
 * sync. The controller's job is just authorization, validation, and
 * flash messaging.
 *
 * Authorization rules:
 *   - activate / deposit / refund: only the buyer party of the contract
 *   - manualRelease: buyer party (the buyer is the one wiring funds out;
 *     the supplier doesn't have permission to drain their own account)
 *   - dashboard / show: any party of the contract
 *
 * Permission keys (declared in App\Support\Permissions):
 *   - escrow.view, escrow.activate, escrow.deposit, escrow.release
 */
class EscrowController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly EscrowService $escrowService) {}

    /**
     * Buyer activates escrow on a signed contract. Idempotent — calling
     * twice returns the existing account flash with no error.
     */
    public function activate(string $id): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('escrow.activate'), 403);
        $this->authorizeBuyer($contract, $user);

        try {
            $this->escrowService->activate($contract);
        } catch (BankPartnerException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('escrow.activated_successfully'));
    }

    /**
     * Buyer deposits funds into escrow. Validates min amount + currency
     * match before delegating to the service.
     */
    public function deposit(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('escrow.deposit'), 403);
        $this->authorizeBuyer($contract, $user);

        $account = $contract->escrowAccount;
        abort_unless($account, 404, 'No escrow account on this contract');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $this->escrowService->deposit(
                account: $account,
                amount: (float) $validated['amount'],
                currency: strtoupper($validated['currency'] ?? $account->currency),
                user: $user,
            );
        } catch (BankPartnerException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('escrow.deposit_recorded'));
    }

    /**
     * Manual release of escrow funds (Sprint 12 / task 3.8). Buyer chooses
     * an amount + optional payment milestone to settle. The form lives
     * inside a modal on the contract show page.
     */
    public function manualRelease(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('escrow.release'), 403);
        $this->authorizeBuyer($contract, $user);

        $account = $contract->escrowAccount;
        abort_unless($account, 404, 'No escrow account on this contract');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'milestone' => ['nullable', 'string', 'max:100'],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = null;
        if (! empty($validated['payment_id'])) {
            $payment = Payment::where('contract_id', $contract->id)
                ->where('id', $validated['payment_id'])
                ->first();
        }

        try {
            $this->escrowService->release(
                account: $account,
                amount: (float) $validated['amount'],
                currency: $account->currency,
                milestone: $validated['milestone'] ?? $payment?->milestone,
                payment: $payment,
                trigger: EscrowRelease::TRIGGER_MANUAL,
                user: $user,
                notes: $validated['notes'] ?? null,
            );
        } catch (BankPartnerException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('escrow.release_recorded'));
    }

    /**
     * Buyer-initiated refund of remaining escrow funds (cancellation /
     * dispute resolved in their favour).
     */
    public function refund(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('escrow.release'), 403);
        $this->authorizeBuyer($contract, $user);

        $account = $contract->escrowAccount;
        abort_unless($account, 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
        ]);

        try {
            $this->escrowService->refund(
                account: $account,
                amount: (float) $validated['amount'],
                currency: $account->currency,
                reason: $validated['reason'],
                user: $user,
            );
        } catch (BankPartnerException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        // Phase A hardening — Cabinet Decision 52/2017 Article 60 requires
        // a tax credit note whenever a tax invoice is reversed. Auto-cut
        // one against the payment the refund unwinds so the buyer can
        // reverse their input tax and the supplier can reduce their
        // output tax on the next VAT return. Silent failure on CN
        // generation doesn't roll back the refund — finance can retry.
        if (! empty($validated['payment_id'])) {
            $payment = \App\Models\Payment::find($validated['payment_id']);
            if ($payment && $payment->contract_id === $contract->id) {
                try {
                    app(\App\Services\Payments\CreditNoteAutoGenerator::class)
                        ->generateFromRefund(
                            $payment,
                            (float) $validated['amount'],
                            \App\Models\TaxCreditNote::REASON_REFUND,
                            $user?->id,
                        );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('credit note auto-gen skipped', [
                        'payment_id' => $payment->id,
                        'reason' => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('escrow.refund_recorded'));
    }

    /**
     * Phase 3 / Sprint 14 / task 3.16 — escrow dashboard for the current
     * tenant. Shows held / released / total broken down by contract,
     * with a click-through to each contract's full ledger.
     */
    public function dashboard(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('escrow.view'), 403);

        $companyId = $this->currentCompanyId();

        // Pull every escrow account whose contract has the current company
        // as a party — buyer side OR supplier side. A dual-role company
        // sees ALL escrow accounts it's involved in, regardless of which
        // side of the trade it's on. The old isSupplierSideUser() dispatch
        // hid buyer-side accounts from supplier-typed companies.
        $accounts = EscrowAccount::query()
            ->with(['contract:id,contract_number,title,buyer_company_id,parties,currency,total_amount', 'releases'])
            ->whereHas('contract', function ($q) use ($companyId) {
                if (! $companyId) {
                    return;
                }
                $q->where(function ($q2) use ($companyId) {
                    $q2->where('buyer_company_id', $companyId)
                        ->orWhereJsonContains('parties', ['company_id' => $companyId]);
                });
            })
            ->latest()
            ->get();

        // KPI strip across the top of the dashboard.
        $kpis = [
            'total_held' => 0.0,
            'total_released' => 0.0,
            'active_count' => 0,
            'closed_count' => 0,
        ];
        foreach ($accounts as $account) {
            $kpis['total_held'] += (float) $account->availableBalance();
            $kpis['total_released'] += (float) $account->total_released;
            if ($account->isActive()) {
                $kpis['active_count']++;
            }
            if ($account->isClosed()) {
                $kpis['closed_count']++;
            }
        }

        return view('dashboard.escrow.index', [
            'accounts' => $accounts,
            'kpis' => $kpis,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Resolve the contract by numeric id OR contract_number, matching the
     * existing ContractController convention so urls work both ways.
     */
    private function findContractOrFail(string $id): Contract
    {
        if (str_starts_with($id, 'CTR-') || str_starts_with($id, 'CNT-')) {
            return Contract::where('contract_number', $id)->firstOrFail();
        }

        return Contract::findOrFail((int) $id);
    }

    /**
     * Buyer-side guard. Suppliers cannot deposit / release / refund into
     * an escrow account they're the beneficiary of — that would defeat
     * the purpose of escrow.
     */
    private function authorizeBuyer(Contract $contract, $user): void
    {
        abort_unless($user && $user->company_id === $contract->buyer_company_id, 403, 'Only the buyer side may perform this escrow action.');
    }
}
