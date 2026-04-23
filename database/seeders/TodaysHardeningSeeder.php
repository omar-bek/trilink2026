<?php

namespace Database\Seeders;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMilestone;
use App\Enums\PaymentRail;
use App\Enums\PaymentStatus;
use App\Models\Bid;
use App\Models\ChequeEvent;
use App\Models\Company;
use App\Models\Contract;
use App\Models\NegotiationMessage;
use App\Models\Payment;
use App\Models\PaymentApproval;
use App\Models\PlatformFeeAllocation;
use App\Models\PostdatedCheque;
use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Payments\PaymentScheduleValidator;
use App\Services\SettlementCalendarService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for everything the 2026-04-22 hardening pass added:
 *   - negotiation round cap + expiry + signed acceptance + VAT snapshot
 *   - payment FX lock, Corporate Tax, WHT columns
 *   - dual-approval ledger (PaymentApproval)
 *   - platform fee allocations
 *   - post-dated cheques + cheque events
 *   - credit note auto-link from a refund
 *   - bank reconciliation periods (one closed, one open)
 *   - VAT return period tag on existing tax invoices
 *
 * Fully idempotent — every write is firstOrCreate / updateOrCreate so
 * `php artisan db:seed --class=TodaysHardeningSeeder` can run repeatedly
 * without drift.
 */
class TodaysHardeningSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('TodaysHardeningSeeder: starting…');

        $this->seedNegotiationHardening();
        $this->seedPaymentFxAndTaxSnapshots();
        $this->seedDualApprovalLedger();
        $this->seedPlatformFeeAllocations();
        $this->seedPostdatedCheques();
        $this->seedRefundCreditNote();
        $this->seedBankReconciliationPeriods();
        $this->seedVatReturnPeriodTags();

        $this->command->info(sprintf(
            'TodaysHardeningSeeder: done — %d negotiation messages, %d cheques, %d cheque events, %d payment approvals, %d platform fee allocations, %d recon periods.',
            NegotiationMessage::count(),
            PostdatedCheque::count(),
            ChequeEvent::count(),
            PaymentApproval::count(),
            PlatformFeeAllocation::count(),
            DB::table('bank_reconciliation_periods')->count(),
        ));
    }

    // ----------------------------------------------------------------
    // 1. Negotiation hardening — round cap, expiry, VAT snapshot,
    //    signed acceptance.
    // ----------------------------------------------------------------
    private function seedNegotiationHardening(): void
    {
        $calendar = app(SettlementCalendarService::class);

        // Make sure every bid carries a sensible round cap so the UI
        // shows the "Round n of N" label. Big-ticket bids get 3 (short
        // leash); everything else stays on the platform default 5.
        Bid::query()->whereNull('negotiation_round_cap')->chunkById(50, function ($chunk) {
            foreach ($chunk as $bid) {
                $cap = ((float) $bid->price) >= 250000 ? 3 : 5;
                $bid->update(['negotiation_round_cap' => $cap]);
            }
        });

        // Backfill VAT / expiry / signed fields on the demo negotiation
        // messages created by ComprehensiveSeeder. The existing rows
        // don't have offer['subtotal_excl_tax'] etc.; we add them so the
        // Blade component renders a full breakdown.
        $counters = NegotiationMessage::query()
            ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
            ->whereNull('subtotal_excl_tax')
            ->get();

        foreach ($counters as $msg) {
            $offer = $msg->offer ?? [];
            $amount = (float) ($offer['amount'] ?? 0);
            $subtotal = round($amount, 2);
            $tax = round($subtotal * 0.05, 2); // UAE 5% VAT default
            $total = round($subtotal + $tax, 2);

            $msg->update([
                'offer' => array_merge($offer, [
                    'tax_treatment' => 'exclusive',
                    'tax_rate' => 5.0,
                    'subtotal_excl_tax' => $subtotal,
                    'tax_amount' => $tax,
                    'total_incl_tax' => $total,
                ]),
                'subtotal_excl_tax' => $subtotal,
                'tax_amount' => $tax,
                'total_incl_tax' => $total,
                'expires_at' => $msg->round_status === NegotiationMessage::ROUND_OPEN
                    ? $calendar->addBusinessDays(now(), 2)->endOfDay()
                    : null,
            ]);
        }

        // Stamp signature fields on ONE accepted round so the signed-
        // acceptance UI has a realistic example to render.
        $target = Bid::query()
            ->where('status', 'under_review')
            ->with(['rfq.company'])
            ->first();
        if (! $target) {
            return;
        }

        $buyer = User::query()
            ->where('company_id', $target->rfq?->company_id)
            ->first();
        if (! $buyer) {
            return;
        }

        // Find or create an accepted-round message. If the bid already
        // has one, stamp signatures on it; otherwise add a brand-new
        // round 3 accepted by the buyer.
        $accepted = NegotiationMessage::firstOrCreate(
            [
                'bid_id' => $target->id,
                'kind' => NegotiationMessage::KIND_COUNTER_OFFER,
                'round_number' => 3,
            ],
            [
                'sender_id' => $buyer->id,
                'sender_side' => 'buyer',
                'body' => 'Agreed on the revised terms.',
                'offer' => [
                    'amount' => (float) $target->price * 0.93,
                    'currency' => $target->currency ?? 'AED',
                    'delivery_days' => 10,
                    'payment_terms' => '20% advance, 60% production, 20% delivery',
                    'reason' => 'Final position — we can proceed.',
                    'tax_treatment' => 'exclusive',
                    'tax_rate' => 5.0,
                    'subtotal_excl_tax' => round((float) $target->price * 0.93, 2),
                    'tax_amount' => round((float) $target->price * 0.93 * 0.05, 2),
                    'total_incl_tax' => round((float) $target->price * 0.93 * 1.05, 2),
                ],
                'round_status' => NegotiationMessage::ROUND_ACCEPTED,
                'subtotal_excl_tax' => round((float) $target->price * 0.93, 2),
                'tax_amount' => round((float) $target->price * 0.93 * 0.05, 2),
                'total_incl_tax' => round((float) $target->price * 0.93 * 1.05, 2),
            ],
        );

        $accepted->update([
            'round_status' => NegotiationMessage::ROUND_ACCEPTED,
            'responded_at' => $accepted->responded_at ?? now()->subDays(2),
            'responded_by' => $buyer->id,
            'signed_by_name' => $buyer->full_name ?? trim(($buyer->first_name ?? '').' '.($buyer->last_name ?? '')),
            'signed_at' => $accepted->signed_at ?? now()->subDays(2),
            'signature_ip' => '94.200.10.42',
            'signature_hash' => $accepted->signature_hash ?? hash('sha256', 'demo|'.$accepted->id),
        ]);
    }

    // ----------------------------------------------------------------
    // 2. FX lock + WHT + Corporate Tax snapshots on existing payments.
    // ----------------------------------------------------------------
    private function seedPaymentFxAndTaxSnapshots(): void
    {
        Payment::query()->whereNull('fx_locked_at')->chunkById(100, function ($chunk) {
            foreach ($chunk as $payment) {
                $currency = strtoupper((string) ($payment->currency ?? 'AED'));
                // Demo: AED stays at 1:1; non-AED gets a reasonable snapshot.
                $rate = $currency === 'AED' ? 1.0 : ($currency === 'USD' ? 3.6725 : 4.0);
                $amount = (float) $payment->amount;
                $inBase = round($amount * $rate, 2);

                $payment->update([
                    'fx_rate_snapshot' => $rate,
                    'fx_base_currency' => 'AED',
                    'fx_locked_at' => $payment->approved_at ?? now()->subDays(7),
                    'amount_in_base' => $inBase,
                    // Corporate Tax 9% snapshot — illustrative, not deducted.
                    'corporate_tax_applicable' => $inBase >= 375000 / 12,
                    'corporate_tax_rate' => $inBase >= 375000 / 12 ? 9.0 : 0.0,
                    'corporate_tax_amount' => $inBase >= 375000 / 12
                        ? round($amount * 0.09, 2)
                        : 0,
                    // Dispute window — 14 days default.
                    'dispute_window_days' => 14,
                ]);
            }
        });

        // WHT demo — stamp 5% on the first payment whose recipient is
        // non-AE; if none, fall back to any payment so the UI has a
        // concrete row to render for the withholding-tax column.
        $demo = Payment::query()
            ->whereHas('recipientCompany', fn ($q) => $q->where('country', '!=', 'AE')->whereNotNull('country'))
            ->where(function ($q) {
                $q->whereNull('wht_amount')->orWhere('wht_amount', 0);
            })
            ->first();
        if (! $demo) {
            $demo = Payment::query()
                ->where(function ($q) {
                    $q->whereNull('wht_amount')->orWhere('wht_amount', 0);
                })
                ->orderByDesc('amount')
                ->first();
        }
        if ($demo) {
            $wht = round((float) $demo->amount * 0.05, 2);
            $demo->update(['wht_rate' => 5.0, 'wht_amount' => $wht]);
        }
    }

    // ----------------------------------------------------------------
    // 3. Dual-approval ledger — record a primary+secondary approval on
    //    the largest existing payment.
    // ----------------------------------------------------------------
    private function seedDualApprovalLedger(): void
    {
        $big = Payment::query()
            ->where('amount_in_base', '>=', 500000)
            ->orWhere('amount', '>=', 500000)
            ->orderByDesc('amount')
            ->first();
        if (! $big) {
            // Fallback: flag the biggest existing payment as needing dual
            // approval so the seeder still produces a concrete example.
            $big = Payment::query()->orderByDesc('amount')->first();
        }
        if (! $big) {
            return;
        }

        $approvers = User::query()
            ->where('company_id', $big->company_id)
            ->limit(2)
            ->get();
        if ($approvers->count() < 2) {
            return;
        }

        $primary = $approvers[0];
        $secondary = $approvers[1];

        PaymentApproval::query()->updateOrCreate(
            ['payment_id' => $big->id, 'approver_id' => $primary->id, 'role' => PaymentApproval::ROLE_PRIMARY],
            [
                'action' => PaymentApproval::ACTION_APPROVED,
                'notes' => 'Demo — primary approval.',
                'amount_snapshot' => $big->amount,
                'currency_snapshot' => $big->currency,
                'ip_address' => '94.200.10.42',
                'user_agent' => 'Seeder/1.0',
            ],
        );
        PaymentApproval::query()->updateOrCreate(
            ['payment_id' => $big->id, 'approver_id' => $secondary->id, 'role' => PaymentApproval::ROLE_SECONDARY],
            [
                'action' => PaymentApproval::ACTION_APPROVED,
                'notes' => 'Demo — secondary approval.',
                'amount_snapshot' => $big->amount,
                'currency_snapshot' => $big->currency,
                'ip_address' => '94.200.10.43',
                'user_agent' => 'Seeder/1.0',
            ],
        );

        $big->update([
            'requires_dual_approval' => true,
            'approved_by' => $primary->id,
            'approved_at' => $big->approved_at ?? now()->subDays(3),
            'second_approver_id' => $secondary->id,
            'second_approved_at' => now()->subDays(3)->addHours(2),
        ]);
    }

    // ----------------------------------------------------------------
    // 4. Platform fee allocations — one transaction fee + one escrow
    //    fee per completed payment.
    // ----------------------------------------------------------------
    private function seedPlatformFeeAllocations(): void
    {
        $payments = Payment::query()
            ->whereIn('status', [PaymentStatus::COMPLETED->value, PaymentStatus::APPROVED->value])
            ->limit(10)
            ->get();

        foreach ($payments as $p) {
            $base = (float) $p->amount;
            $currency = strtoupper((string) ($p->currency ?? 'AED'));

            PlatformFeeAllocation::query()->updateOrCreate(
                ['payment_id' => $p->id, 'fee_type' => PlatformFeeAllocation::TYPE_TRANSACTION],
                [
                    'base_amount' => round($base, 2),
                    'rate' => 0.0125,
                    'fee_amount' => round($base * 0.0125, 2),
                    'currency' => $currency,
                ],
            );
            PlatformFeeAllocation::query()->updateOrCreate(
                ['payment_id' => $p->id, 'fee_type' => PlatformFeeAllocation::TYPE_ESCROW],
                [
                    'base_amount' => round($base, 2),
                    'rate' => 0.005,
                    'fee_amount' => round($base * 0.005, 2),
                    'currency' => $currency,
                ],
            );
        }
    }

    // ----------------------------------------------------------------
    // 5. Post-dated cheques — 3 lifecycle examples: CLEARED, ISSUED
    //    awaiting presentation, RETURNED (bounced).
    // ----------------------------------------------------------------
    private function seedPostdatedCheques(): void
    {
        $contracts = Contract::query()
            ->whereIn('status', ['active', 'signed', 'completed'])
            ->take(3)
            ->get();
        if ($contracts->isEmpty()) {
            return;
        }

        $admin = User::query()->where('role', 'admin')->first() ?? User::query()->first();
        if (! $admin) {
            return;
        }

        $defs = [
            ['CHQ-2026-0001', $contracts[0], ChequeStatus::CLEARED, 'Emirates NBD', 'AE070331234567890123456', -14, -7],
            ['CHQ-2026-0002', $contracts->get(1) ?? $contracts[0], ChequeStatus::ISSUED, 'Mashreq Bank', 'AE330260001234567890123', 7, 30],
            ['CHQ-2026-0003', $contracts->get(2) ?? $contracts[0], ChequeStatus::RETURNED, 'Abu Dhabi Commercial Bank', 'AE550345678901234567890', -21, -14],
        ];

        foreach ($defs as [$num, $contract, $status, $bank, $iban, $issueOffset, $presentOffset]) {
            $supplierParty = collect($contract->parties ?? [])->firstWhere('role', 'supplier');
            $supplierCompanyId = is_array($supplierParty)
                ? ($supplierParty['company_id'] ?? null)
                : ($supplierParty->company_id ?? null);
            if (! $supplierCompanyId) {
                continue;
            }

            $linkedPayment = Payment::query()
                ->where('contract_id', $contract->id)
                ->orderBy('id')
                ->first();

            $cheque = PostdatedCheque::firstOrCreate(
                ['cheque_number' => $num, 'issuer_company_id' => $contract->buyer_company_id],
                [
                    'beneficiary_company_id' => $supplierCompanyId,
                    'contract_id' => $contract->id,
                    'payment_id' => $linkedPayment?->id,
                    'drawer_bank_name' => $bank,
                    'drawer_bank_swift' => 'EBILAEAD',
                    'drawer_account_iban' => $iban,
                    'issue_date' => now()->addDays($issueOffset)->toDateString(),
                    'presentation_date' => now()->addDays($presentOffset)->toDateString(),
                    'amount' => $linkedPayment?->amount ?? 25000,
                    'currency' => $linkedPayment?->currency ?? 'AED',
                    'status' => $status->value,
                    'return_reason' => $status === ChequeStatus::RETURNED ? 'Insufficient funds' : null,
                    'deposited_at' => in_array($status, [ChequeStatus::CLEARED, ChequeStatus::RETURNED], true) ? now()->addDays($presentOffset) : null,
                    'cleared_at' => $status === ChequeStatus::CLEARED ? now()->addDays($presentOffset + 1) : null,
                    'returned_at' => $status === ChequeStatus::RETURNED ? now()->addDays($presentOffset + 1) : null,
                    'notes' => 'Seeded demo cheque.',
                    'created_by' => $admin->id,
                ],
            );

            // Event trail — issued → (deposited → cleared) OR (deposited → returned).
            $events = [['issued', $cheque->issue_date]];
            if (in_array($status, [ChequeStatus::CLEARED, ChequeStatus::RETURNED], true)) {
                $events[] = ['deposited', $cheque->deposited_at];
                $events[] = [$status === ChequeStatus::CLEARED ? 'cleared' : 'returned', $status === ChequeStatus::CLEARED ? $cheque->cleared_at : $cheque->returned_at];
            }

            foreach ($events as [$event, $ts]) {
                ChequeEvent::firstOrCreate(
                    ['postdated_cheque_id' => $cheque->id, 'event' => $event],
                    [
                        'actor_user_id' => $admin->id,
                        'metadata' => $event === 'returned' ? ['reason' => 'Insufficient funds'] : null,
                        'created_at' => $ts ?? now(),
                    ],
                );
            }
        }
    }

    // ----------------------------------------------------------------
    // 6. Credit note auto-linked to a refunded payment. Picks an
    //    existing tax invoice and issues a CN snapshot against it.
    // ----------------------------------------------------------------
    private function seedRefundCreditNote(): void
    {
        $invoice = TaxInvoice::query()->first();
        if (! $invoice) {
            return;
        }
        $payment = Payment::query()->where('id', $invoice->payment_id)->first();
        if (! $payment) {
            return;
        }

        // Assume a 20% refund scenario for the demo.
        $ratio = 0.2;
        $subtotal = round((float) $invoice->subtotal_excl_tax * $ratio, 2);
        $tax = round((float) $invoice->total_tax * $ratio, 2);
        $total = round($subtotal + $tax, 2);

        $items = is_array($invoice->line_items ?? null) ? $invoice->line_items : [];
        foreach ($items as &$li) {
            foreach (['amount', 'tax_amount', 'total'] as $k) {
                if (isset($li[$k])) {
                    $li[$k] = round((float) $li[$k] * $ratio, 2);
                }
            }
            $li['is_credit_note'] = true;
        }
        unset($li);

        $cnNumber = sprintf('CN-%d-%06d', (int) now()->year, (int) $invoice->id);

        $cn = TaxCreditNote::firstOrCreate(
            ['credit_note_number' => $cnNumber, 'original_invoice_id' => $invoice->id],
            [
                'issue_date' => now()->toDateString(),
                'reason' => TaxCreditNote::REASON_REFUND,
                'notes' => 'Seeded demo — 20% partial refund on payment #'.$payment->id,
                'line_items' => $items,
                'subtotal_excl_tax' => $subtotal,
                'total_tax' => $tax,
                'total_inclusive' => $total,
                'currency' => $invoice->currency,
                'issued_by' => $invoice->issued_by,
                'issued_at' => now(),
                'metadata' => ['payment_id' => $payment->id, 'ratio' => $ratio],
            ],
        );

        $payment->update(['refund_credit_note_id' => $cn->id]);
    }

    // ----------------------------------------------------------------
    // 7. Bank reconciliation periods — one closed (last month) and one
    //    open (this month) per tenant company.
    // ----------------------------------------------------------------
    private function seedBankReconciliationPeriods(): void
    {
        $companies = Company::query()
            ->whereHas('users')
            ->take(3)
            ->get();

        foreach ($companies as $company) {
            $lastMonth = Carbon::now()->subMonthNoOverflow();
            $thisMonth = Carbon::now();

            DB::table('bank_reconciliation_periods')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'period_start' => $lastMonth->copy()->startOfMonth()->toDateString(),
                    'period_end' => $lastMonth->copy()->endOfMonth()->toDateString(),
                ],
                [
                    'status' => 'closed',
                    'lines_matched' => 24,
                    'lines_unmatched' => 0,
                    'opening_balance' => 150000,
                    'closing_balance' => 182450,
                    'closed_at' => $lastMonth->copy()->endOfMonth()->addDays(3),
                    'closed_by' => User::query()->where('company_id', $company->id)->value('id'),
                    'closure_notes' => 'Closed after all lines matched.',
                    'created_at' => $lastMonth->copy()->startOfMonth(),
                    'updated_at' => $lastMonth->copy()->endOfMonth()->addDays(3),
                ],
            );

            DB::table('bank_reconciliation_periods')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'period_start' => $thisMonth->copy()->startOfMonth()->toDateString(),
                    'period_end' => $thisMonth->copy()->endOfMonth()->toDateString(),
                ],
                [
                    'status' => 'open',
                    'lines_matched' => 12,
                    'lines_unmatched' => 3,
                    'opening_balance' => 182450,
                    'closing_balance' => null,
                    'closed_at' => null,
                    'closed_by' => null,
                    'closure_notes' => null,
                    'created_at' => $thisMonth->copy()->startOfMonth(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    // ----------------------------------------------------------------
    // 8. VAT return period tagging on existing tax invoices. Quarterly
    //    filer is the platform default.
    // ----------------------------------------------------------------
    private function seedVatReturnPeriodTags(): void
    {
        TaxInvoice::query()->whereNull('vat_return_period')->chunkById(100, function ($chunk) {
            foreach ($chunk as $inv) {
                $date = $inv->issue_date ?? $inv->created_at ?? now();
                $year = (int) $date->format('Y');
                $quarter = (int) ceil(((int) $date->format('m')) / 3);
                $inv->update(['vat_return_period' => sprintf('%d-Q%d', $year, $quarter)]);
            }
        });
    }
}
