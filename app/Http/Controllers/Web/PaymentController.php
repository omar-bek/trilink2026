<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly PaymentService $service)
    {
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $companyId = $this->currentCompanyId();

        $base = Payment::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $pendingStatuses  = [PaymentStatus::PENDING_APPROVAL->value, PaymentStatus::APPROVED->value, PaymentStatus::PROCESSING->value];
        $completedStatuses = [PaymentStatus::COMPLETED->value];

        $pendingAmount = (clone $base)->whereIn('status', $pendingStatuses)->sum('total_amount');
        $monthAmount   = (clone $base)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $stats = [
            'pending'        => (clone $base)->whereIn('status', $pendingStatuses)->count(),
            'pending_amount' => $this->shortMoney((float) $pendingAmount),
            'completed'      => (clone $base)->whereIn('status', $completedStatuses)->count(),
            'paid_month'     => $this->shortMoney((float) $monthAmount),
        ];

        $payments = (clone $base)
            ->with(['contract', 'recipientCompany'])
            ->latest()
            ->get()
            ->map(function (Payment $p) {
                $statusKey = $this->mapPaymentStatus($this->statusValue($p->status));
                $isPaid    = $statusKey === 'paid';
                $dueDate   = $p->approved_at ?? $p->created_at;
                $isOverdue = !$isPaid && $dueDate && $dueDate->isPast() && $dueDate->diffInDays(now()) > 7;
                $contractTotal = (float) ($p->contract?->total_amount ?? 0);
                $pct = $contractTotal > 0 ? (int) round(((float) $p->amount / $contractTotal) * 100) : 0;

                return [
                    'id'        => sprintf('PAY-2024-%04d', 187 + $p->id),
                    'method'    => __('payments.bank_transfer'),
                    'contract'  => $p->contract?->contract_number ?? '—',
                    'supplier'  => $p->recipientCompany?->name ?? '—',
                    'milestone' => $p->milestone ?? __('payments.payment'),
                    'pct'       => $pct,
                    'of'        => $contractTotal,
                    'amount'    => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                    'due'       => $this->longDate($dueDate),
                    'status'    => $statusKey,
                    'urgent'    => $isOverdue,
                    'paid'      => $isPaid,
                ];
            })
            ->toArray();

        return view('dashboard.payments.index', compact('stats', 'payments'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $p = Payment::with(['contract', 'recipientCompany', 'company', 'buyer'])->findOrFail((int) $id);

        $statusKey = $this->mapPaymentStatus($this->statusValue($p->status));
        $contractTotal = (float) ($p->contract?->total_amount ?? 0);
        $pct = $contractTotal > 0 ? (int) round(((float) $p->amount / $contractTotal) * 100) : 0;

        $payment = [
            'id'        => sprintf('PAY-2024-%04d', 187 + $p->id),
            'status'    => $statusKey,
            'paid'      => $statusKey === 'paid',
            'method'    => $p->payment_gateway ? ucfirst($p->payment_gateway) : __('payments.bank_transfer'),
            'milestone' => $p->milestone ?? '—',
            'pct'       => $pct,
            'contract'  => $p->contract?->contract_number ?? '—',
            'supplier'  => $p->recipientCompany?->name ?? '—',
            'buyer'     => $p->company?->name ?? '—',
            'amount'    => $this->money((float) $p->amount, $p->currency ?? 'AED'),
            'vat'       => $this->money((float) $p->vat_amount, $p->currency ?? 'AED'),
            'total'     => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
            'due'       => $this->longDate($p->approved_at ?? $p->created_at),
            'created'   => $this->longDate($p->created_at),
            'gateway_ref' => $p->gateway_payment_id ?? '—',
        ];

        return view('dashboard.payments.show', compact('payment'));
    }

    public function approve(string $id): RedirectResponse
    {
        $payment = Payment::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('payment.approve'), 403, 'Forbidden: missing payments.approve permission.');
        abort_unless($payment->company_id === $user->company_id, 403);

        $this->service->approve($payment->id, $user->id);

        return redirect()
            ->route('dashboard.payments')
            ->with('status', __('payments.approved_successfully'));
    }

    public function process(string $id): RedirectResponse
    {
        $payment = Payment::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('payment.process'), 403, 'Forbidden: missing payments.process permission.');
        abort_unless($payment->company_id === $user->company_id, 403);

        $result = $this->service->process($payment->id, request()->input('gateway', 'stripe'));

        if (is_string($result)) {
            return back()->withErrors(['payment' => $result]);
        }

        return redirect()
            ->route('dashboard.payments')
            ->with('status', __('payments.processed_successfully'));
    }

    private function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'completed'        => 'paid',
            'pending_approval' => 'scheduled',
            'approved',
            'processing'       => 'due_soon',
            'failed',
            'rejected',
            'cancelled'        => 'urgent',
            'refunded'         => 'paid',
            default            => 'scheduled',
        };
    }

    private function shortMoney(float $value, string $currency = 'AED'): string
    {
        if ($value >= 1_000_000) {
            return $currency . ' ' . round($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return $currency . ' ' . round($value / 1_000) . 'K';
        }

        return $currency . ' ' . number_format($value);
    }
}
