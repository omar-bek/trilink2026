<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\Bid\StoreBidRequest;
use App\Models\Bid;
use App\Models\Rfq;
use App\Services\BidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BidController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly BidService $service)
    {
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('bid.view'), 403);

        $companyId = $this->currentCompanyId();

        // Bids that belong to the current company's RFQs (buyer view)
        $base = Bid::query()->whereHas('rfq', function ($q) use ($companyId) {
            $q->when($companyId, fn ($qq) => $qq->where('company_id', $companyId));
        });

        $stats = [
            'total'        => (clone $base)->count(),
            'under_review' => (clone $base)->where('status', BidStatus::UNDER_REVIEW->value)->count(),
            'shortlisted'  => (clone $base)->where('status', BidStatus::SUBMITTED->value)->count(),
            'accepted'     => (clone $base)->where('status', BidStatus::ACCEPTED->value)->count(),
            'rejected'     => (clone $base)->where('status', BidStatus::REJECTED->value)->count(),
        ];

        $bids = (clone $base)
            ->with(['rfq', 'company'])
            ->latest()
            ->get()
            ->map(function (Bid $bid) {
                $rfqBudget = (float) ($bid->rfq?->budget ?? $bid->price);
                $diff      = $rfqBudget > 0 ? round((($rfqBudget - (float) $bid->price) / $rfqBudget) * 100, 1) : 0;
                $statusKey = $this->mapBidStatus($this->statusValue($bid->status));

                return [
                    'id'           => sprintf('BID-2024-%04d', 5421 + $bid->id),
                    'numeric_id'   => $bid->id,
                    'status'       => $statusKey,
                    'shortlisted'  => in_array($statusKey, ['submitted', 'shortlisted', 'accepted'], true),
                    'rfq'          => $bid->rfq?->rfq_number ?? '—',
                    'rfq_title'    => $bid->rfq?->title ?? '—',
                    'supplier'     => '#' . str_pad((string) ($bid->company_id * 1000), 4, '0', STR_PAD_LEFT),
                    'rating'       => round(4.0 + ($bid->id % 10) / 10, 1),
                    'received'     => $bid->rfq?->bids()->count() ?? 0,
                    'submitted'    => $this->date($bid->created_at),
                    'expires'      => $this->date($bid->validity_date),
                    'amount'       => $this->money((float) $bid->price, $bid->currency),
                    'old_amount'   => $this->money($rfqBudget, $bid->currency),
                    'diff'         => abs($diff),
                    'days'         => (int) ($bid->delivery_time_days ?? 0),
                    'terms'        => $bid->payment_terms ?? '—',
                    'show_actions' => $statusKey === 'submitted',
                    'price_up'     => $diff < 0,
                ];
            })
            ->toArray();

        return view('dashboard.bids.index', compact('stats', 'bids'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('bid.view'), 403);

        $bid = $this->findOrFail($id)->load(['rfq.category', 'company']);

        $rfqBudget = (float) ($bid->rfq?->budget ?? $bid->price);
        $diff      = $rfqBudget > 0 ? round((($rfqBudget - (float) $bid->price) / $rfqBudget) * 100, 1) : 0;

        $bidData = [
            'id'             => sprintf('BID-2024-%04d', 5421 + $bid->id),
            'numeric_id'     => $bid->id,
            'status'         => $this->mapBidStatus($this->statusValue($bid->status)),
            'rfq'            => $bid->rfq?->rfq_number ?? '—',
            'rfq_numeric_id' => $bid->rfq?->id,
            'rfq_title'      => $bid->rfq?->title ?? '—',
            'supplier'   => $bid->company?->name ?? '—',
            'amount'     => $this->money((float) $bid->price, $bid->currency),
            'old_amount' => $this->money($rfqBudget, $bid->currency),
            'diff'       => abs($diff),
            'price_up'   => $diff < 0,
            'days'       => (int) ($bid->delivery_time_days ?? 0),
            'terms'      => $bid->payment_terms ?? '—',
            'submitted'  => $this->longDate($bid->created_at),
            'expires'    => $this->longDate($bid->validity_date),
            'notes'      => $bid->notes ?? '',
            'items'      => collect($bid->items ?? [])->values()->map(function ($it, $i) use ($bid) {
                return [
                    'n'         => $i + 1,
                    'name'      => $it['name'] ?? 'Item',
                    'qty'       => (int) ($it['qty'] ?? 0),
                    'unit_price'=> $this->money((float) ($it['unit_price'] ?? 0), $bid->currency),
                ];
            })->toArray(),
            'ai_score'   => $bid->ai_score['overall'] ?? $bid->ai_score['score'] ?? null,
        ];

        return view('dashboard.bids.show', ['bid' => $bidData]);
    }

    public function store(StoreBidRequest $request, int $rfq): RedirectResponse
    {
        $rfqModel = Rfq::findOrFail($rfq);
        $user     = $request->user();

        abort_unless($user?->hasPermission('bid.submit'), 403, 'Forbidden: missing bids.create permission.');

        $result = $this->service->create([
            'rfq_id'             => $rfqModel->id,
            'company_id'         => $user->company_id,
            'provider_id'        => $user->id,
            'status'             => BidStatus::SUBMITTED,
            'price'              => $request->input('price'),
            'currency'           => $request->input('currency', 'AED'),
            'delivery_time_days' => $request->input('delivery_time_days'),
            'payment_terms'      => $request->input('payment_terms'),
            'validity_date'      => $request->input('validity_date'),
            'notes'              => $request->input('notes'),
            'items'              => $request->input('items', []),
        ]);

        if (is_string($result)) {
            return back()->withErrors(['bid' => $result])->withInput();
        }

        return redirect()
            ->route('dashboard.rfqs.show', ['id' => $rfqModel->id])
            ->with('status', __('bids.submitted_successfully'));
    }

    public function accept(string $id): RedirectResponse
    {
        $bid = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('bid.accept'), 403, 'Forbidden: missing bids.evaluate permission.');
        // Verify the buyer owns the RFQ.
        abort_unless($bid->rfq?->company_id === $user->company_id, 403);

        $bid->update(['status' => BidStatus::ACCEPTED]);

        // Reject all other bids on the same RFQ.
        Bid::where('rfq_id', $bid->rfq_id)
            ->where('id', '!=', $bid->id)
            ->update(['status' => BidStatus::REJECTED->value]);

        return redirect()
            ->route('dashboard.bids.show', ['id' => $bid->id])
            ->with('status', __('bids.accepted_successfully'));
    }

    public function withdraw(string $id): RedirectResponse
    {
        $bid = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('bid.withdraw'), 403, 'Forbidden: missing bids.withdraw permission.');
        abort_unless($bid->provider_id === $user->id, 403);

        $result = $this->service->withdraw($bid->id);

        if (!$result) {
            return back()->withErrors(['bid' => __('bids.cannot_withdraw')]);
        }

        return redirect()
            ->route('dashboard.bids')
            ->with('status', __('bids.withdrawn_successfully'));
    }

    private function findOrFail(string $id): Bid
    {
        if (preg_match('/BID-\d{4}-(\d+)/', $id, $m)) {
            $modelId = max((int) $m[1] - 5421, 1);

            return Bid::findOrFail($modelId);
        }

        return Bid::findOrFail((int) $id);
    }

    private function mapBidStatus(string $status): string
    {
        return match ($status) {
            'draft'        => 'draft',
            'submitted'    => 'submitted',
            'under_review' => 'under_review',
            'accepted'     => 'accepted',
            'rejected'     => 'rejected',
            'withdrawn'    => 'closed',
            default        => 'draft',
        };
    }
}
