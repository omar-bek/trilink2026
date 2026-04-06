<?php

namespace App\Services;

use App\Enums\PaymentStatus;
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
