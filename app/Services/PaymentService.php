<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentStatusNotification;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayFactory $gatewayFactory,
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return Payment::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['contract_id'] ?? null, fn ($q, $v) => $q->where('contract_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['buyer_id'] ?? null, fn ($q, $v) => $q->where('buyer_id', $v))
            ->with(['contract', 'company', 'recipientCompany'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Payment
    {
        return Payment::with(['contract', 'company', 'recipientCompany', 'buyer'])->find($id);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data)->load(['contract', 'company', 'recipientCompany']);
    }

    /**
     * Auto-create one Payment record per milestone in a contract's
     * payment_schedule. Called from ContractService::sign() the moment a
     * contract reaches ACTIVE status (i.e. all parties have signed).
     *
     * Idempotent: re-running this on a contract that already has payments is
     * a no-op so a re-signature event cannot duplicate the schedule.
     *
     * Each Payment is created in PENDING_APPROVAL state — the company's
     * finance employee then approves and processes them on the milestone due
     * date through the existing Payment dashboard.
     */
    public function generateFromSchedule(Contract $contract): int
    {
        if ($contract->payments()->exists()) {
            return 0;
        }

        $schedule = $contract->payment_schedule ?? [];
        if (empty($schedule)) {
            return 0;
        }

        // The buyer is always the payer; the supplier party is always the
        // recipient. The contract carries this on parties[].
        $buyerCompanyId = $contract->buyer_company_id;
        $supplierParty = collect($contract->parties ?? [])
            ->firstWhere('role', 'supplier');
        $supplierCompanyId = $supplierParty['company_id'] ?? null;

        if (!$buyerCompanyId || !$supplierCompanyId) {
            return 0;
        }

        // Use the contract's PR buyer when available; otherwise fall back to
        // any active user in the buyer company so the buyer_id NOT NULL FK
        // is satisfied. The finance employee will approve regardless.
        $buyerId = $contract->purchaseRequest?->buyer_id
            ?? User::where('company_id', $buyerCompanyId)->value('id');

        if (!$buyerId) {
            return 0;
        }

        $created = 0;
        foreach ($schedule as $milestone) {
            $amount = (float) ($milestone['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            // Use the tax rate frozen onto the milestone at contract-creation
            // time so subsequent admin tax-rate edits don't retroactively
            // change a signed contract's payments.
            $vatRate = $milestone['tax_rate'] ?? null;

            Payment::create([
                'contract_id'          => $contract->id,
                'company_id'           => $buyerCompanyId,
                'recipient_company_id' => $supplierCompanyId,
                'buyer_id'             => $buyerId,
                'status'               => PaymentStatus::PENDING_APPROVAL,
                'amount'               => $amount,
                'vat_rate'             => $vatRate,
                'currency'             => $milestone['currency'] ?? $contract->currency ?? 'AED',
                'milestone'            => $milestone['milestone'] ?? null,
            ]);

            $created++;
        }

        return $created;
    }

    public function update(int $id, array $data): ?Payment
    {
        $payment = Payment::find($id);
        if (!$payment) return null;

        $payment->update($data);
        return $payment->fresh(['contract', 'company']);
    }

    public function approve(int $id, int $approverId): ?Payment
    {
        $payment = Payment::find($id);
        if (!$payment || $payment->status !== PaymentStatus::PENDING_APPROVAL) return null;

        $payment->update([
            'status' => PaymentStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $approverId,
        ]);

        $this->notifyParties($payment, 'approved');

        return $payment->fresh();
    }

    public function reject(int $id, string $reason): ?Payment
    {
        $payment = Payment::find($id);
        if (!$payment || $payment->status !== PaymentStatus::PENDING_APPROVAL) return null;

        $payment->update([
            'status' => PaymentStatus::REJECTED,
            'rejection_reason' => $reason,
        ]);

        $this->notifyParties($payment, 'rejected');

        return $payment->fresh();
    }

    /**
     * Notify both the paying company and the recipient company that a payment
     * changed state. Both sides care for different reasons (cash out vs cash in)
     * but the click-through target is the same payment page.
     */
    private function notifyParties(Payment $payment, string $action): void
    {
        $companyIds = collect([$payment->company_id, $payment->recipient_company_id])
            ->filter()
            ->unique()
            ->all();

        if (empty($companyIds)) {
            return;
        }

        $recipients = User::whereIn('company_id', $companyIds)->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PaymentStatusNotification($payment, $action));
        }
    }

    public function process(int $id, string $gateway = 'stripe'): Payment|string
    {
        $payment = Payment::find($id);
        if (!$payment) return 'Payment not found';

        if ($payment->status !== PaymentStatus::APPROVED) {
            return 'Payment must be approved before processing';
        }

        try {
            $paymentGateway = $this->gatewayFactory->make($gateway);
            $result = $paymentGateway->charge($payment);

            $payment->update([
                'status' => PaymentStatus::PROCESSING,
                'payment_gateway' => $gateway,
                'gateway_payment_id' => $result['payment_id'] ?? null,
                'gateway_order_id' => $result['order_id'] ?? null,
            ]);

            return $payment->fresh();
        } catch (\Exception $e) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'retry_count' => $payment->retry_count + 1,
            ]);

            return 'Payment processing failed: ' . $e->getMessage();
        }
    }
}
