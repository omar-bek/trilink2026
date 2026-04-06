<?php

namespace App\Http\Controllers\Web;

use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Rfq;
use Illuminate\View\View;

class RfqController extends Controller
{
    use FormatsForViews;

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('rfq.view'), 403);

        $companyId = $this->currentCompanyId();

        $base = Rfq::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $stats = [
            'all'     => (clone $base)->count(),
            'open'    => (clone $base)->where('status', RfqStatus::OPEN->value)->count(),
            'expired' => (clone $base)->where('deadline', '<', now())->where('status', RfqStatus::OPEN->value)->count(),
            'closed'  => (clone $base)->where('status', RfqStatus::CLOSED->value)->count(),
            'draft'   => (clone $base)->where('status', RfqStatus::DRAFT->value)->count(),
        ];

        $rfqs = (clone $base)
            ->with(['category', 'company', 'bids'])
            ->latest()
            ->get()
            ->map(function (Rfq $rfq) {
                $statusKey = $this->mapRfqStatus($rfq);

                return [
                    'id'         => $rfq->rfq_number,
                    'status'     => $statusKey,
                    'tags'       => array_filter([
                        $rfq->company?->name,
                        $rfq->category?->name,
                        'Supplier',
                    ]),
                    'tag_colors' => ['slate', 'blue', 'slate'],
                    'title'      => $rfq->title,
                    'desc'       => $rfq->description ?? '',
                    'items'      => count($rfq->items ?? []),
                    'amount'     => $this->money((float) $rfq->budget, $rfq->currency),
                    'date'       => $this->longDate($rfq->deadline),
                    'bids'       => $rfq->bids->count(),
                ];
            })
            ->toArray();

        return view('dashboard.rfqs.index', compact('stats', 'rfqs'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('rfq.view'), 403);

        $rfq = $this->findOrFail($id);
        $rfq->loadMissing(['bids.company']);

        $items = collect($rfq->items ?? []);
        $totalQty = $items->sum(fn ($i) => (float) ($i['qty'] ?? $i['quantity'] ?? 0));
        $unit = $items->first()['unit'] ?? __('rfq.unit_default');

        $techSpecs = $items->flatMap(function ($item) {
            $specs = $item['specs'] ?? $item['specifications'] ?? [];
            if (is_string($specs)) {
                return array_filter(array_map('trim', preg_split('/\r?\n/', $specs)));
            }
            return is_array($specs) ? array_values(array_filter($specs)) : [];
        })->all();

        $bidsAmounts = $rfq->bids->map(fn ($b) => (float) $b->price)->filter();
        $bidsDeliveries = $rfq->bids->map(fn ($b) => (int) $b->delivery_time_days)->filter();

        $bids = $rfq->bids->sortByDesc(function ($b) {
            $score = $b->ai_score['overall'] ?? null;
            return $score ?? 0;
        })->values()->map(function ($bid, $idx) use ($rfq) {
            $name = $bid->company?->name ?? __('common.anonymous');
            $aiScore = $bid->ai_score['overall'] ?? null;
            $compliance = $bid->ai_score['compliance'] ?? $aiScore;
            $rating = $bid->ai_score['rating'] ?? null;

            return [
                'id'          => $bid->id,
                'code'        => $this->initials($name),
                'name'        => $name,
                'rating'      => $rating ? number_format($rating, 1) : '—',
                'compliance'  => $compliance !== null ? (int) $compliance : null,
                'price'       => $this->money((float) $bid->price, $bid->currency ?? 'AED'),
                'days'        => (int) $bid->delivery_time_days,
                'recommended' => $idx === 0 && $aiScore !== null,
            ];
        })->all();

        $deadline = $rfq->deadline;

        $rfqData = [
            'id'                  => $rfq->rfq_number,
            'numeric_id'          => $rfq->id,
            'title'               => $rfq->title,
            'status'              => $this->mapRfqStatus($rfq),
            'published'           => $this->longDate($rfq->created_at),
            'bids_count'          => $rfq->bids->count(),
            'description'         => $rfq->description ?? '',
            'category'            => $rfq->category?->name ?? __('rfq.category_general'),
            'quantity'            => $totalQty > 0
                ? rtrim(rtrim(number_format($totalQty, 2), '0'), '.') . ' ' . $unit
                : '—',
            'location'            => $rfq->delivery_location ?? '—',
            'deadline'            => $this->longDate($deadline),
            'tech_specs'          => $techSpecs,
            'bids'                => $bids,
            'budget'              => $rfq->budget ? $this->money((float) $rfq->budget, $rfq->currency ?? 'AED') : null,
            'budget_min'          => $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->min(), $rfq->currency ?? 'AED') : null,
            'budget_max'          => $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->max(), $rfq->currency ?? 'AED') : null,
            'avg_market_price'    => $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->avg(), $rfq->currency ?? 'AED') : null,
            'typical_delivery'    => $bidsDeliveries->isNotEmpty()
                ? $bidsDeliveries->min() . '-' . $bidsDeliveries->max() . ' ' . __('rfq.days')
                : null,
            'target_role'         => $rfq->target_role ?? 'supplier',
            'attachments'         => [],
            'created_at'          => $rfq->created_at,
            'deadline_raw'        => $deadline,
        ];

        return view('dashboard.rfqs.show', ['rfq' => $rfqData]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/[\s\-]+/u', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) break;
        }
        return $letters !== '' ? $letters : '—';
    }

    public function compareBids(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('bid.compare'), 403);

        return view('dashboard.rfqs.compare-bids', ['rfqId' => $id]);
    }

    private function findOrFail(string $id): Rfq
    {
        // Accept either rfq_number or numeric id.
        $query = Rfq::with(['category', 'company', 'bids']);

        if (str_starts_with($id, 'RFQ-')) {
            return $query->where('rfq_number', $id)->firstOrFail();
        }

        return $query->findOrFail((int) $id);
    }

    private function mapRfqStatus(Rfq $rfq): string
    {
        $status = $this->statusValue($rfq->status);

        if ($status === 'open' && $rfq->deadline && $rfq->deadline->isPast()) {
            return 'expired';
        }

        return match ($status) {
            'open'      => 'open',
            'closed'    => 'closed',
            'cancelled' => 'closed',
            'draft'     => 'draft',
            default     => 'draft',
        };
    }
}
