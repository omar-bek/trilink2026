<?php

namespace App\Http\Controllers\Web;

use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly PurchaseRequestService $service)
    {
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('pr.view'), 403);

        $companyId = $this->currentCompanyId();

        $base = PurchaseRequest::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $stats = [
            'total'    => (clone $base)->count(),
            'pending'  => (clone $base)->where('status', PurchaseRequestStatus::PENDING_APPROVAL->value)->count(),
            'approved' => (clone $base)->where('status', PurchaseRequestStatus::APPROVED->value)->count(),
            'progress' => (clone $base)->where('status', PurchaseRequestStatus::SUBMITTED->value)->count(),
            'closed'   => (clone $base)->where('status', PurchaseRequestStatus::REJECTED->value)->count(),
        ];

        $requests = (clone $base)
            ->with(['buyer', 'category', 'rfqs.bids'])
            ->latest()
            ->get()
            ->map(function (PurchaseRequest $pr) {
                $rfqsCount = $pr->rfqs->count();
                $bidsCount = $pr->rfqs->sum(fn ($r) => $r->bids->count());

                return [
                    'id'      => sprintf('PR-2024-%04d', 1234 + $pr->id),
                    'status'  => $this->mapPrStatus($this->statusValue($pr->status)),
                    'tag'     => $pr->category?->name ?? 'General',
                    'title'   => $pr->title,
                    'desc'    => $pr->description ?? '',
                    'creator' => trim(($pr->buyer?->first_name ?? '') . ' ' . ($pr->buyer?->last_name ?? '')) ?: 'Unknown',
                    'date'    => $this->date($pr->created_at),
                    'amount'  => $this->money((float) $pr->budget, $pr->currency),
                    'rfqs'    => $rfqsCount,
                    'bids'    => $bidsCount,
                    'progress'=> $rfqsCount > 0
                        ? ['done' => $rfqsCount, 'total' => max($rfqsCount, 4)]
                        : null,
                ];
            })
            ->toArray();

        return view('dashboard.purchase-requests.index', compact('stats', 'requests'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('pr.view'), 403);

        $pr = $this->findOrFail($id);
        $pr->loadMissing(['rfqs.bids', 'rfqs.category', 'buyer', 'category']);

        $items = collect($pr->items ?? [])->values()->map(function ($item, $i) use ($pr) {
            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $price = isset($item['price']) ? (float) $item['price'] : null;
            return [
                'n'     => $i + 1,
                'name'  => $item['name'] ?? __('pr.item'),
                'desc'  => $item['spec'] ?? $item['description'] ?? '',
                'qty'   => trim($qty . ' ' . ($item['unit'] ?? __('pr.unit_default'))),
                'price' => $price !== null ? $this->money($price * max($qty, 1), $pr->currency ?? 'AED') : '—',
            ];
        })->toArray();

        $relatedRfqs = $pr->rfqs->map(function (\App\Models\Rfq $rfq) {
            $statusValue = $this->statusValue($rfq->status);
            $statusKey = match ($statusValue) {
                'open' => $rfq->deadline && $rfq->deadline->isPast() ? 'expired' : 'open',
                'closed', 'cancelled' => 'closed',
                'draft' => 'draft',
                default => 'draft',
            };

            return [
                'id'       => $rfq->rfq_number,
                'numeric_id' => $rfq->id,
                'status'   => $statusKey,
                'tag'      => $rfq->category?->name ?? __('rfq.category_general'),
                'title'    => $rfq->title,
                'created'  => $this->date($rfq->created_at),
                'deadline' => $this->date($rfq->deadline),
                'bids'     => $rfq->bids->count(),
            ];
        })->toArray();

        $timeline = $this->buildPrTimeline($pr);

        $location = $pr->delivery_location;
        if (is_array($location)) {
            $location = trim(implode(', ', array_filter([
                $location['address'] ?? null,
                $location['city'] ?? null,
                $location['country'] ?? null,
            ])));
        }

        $prData = [
            'id'                   => sprintf('PR-2024-%04d', 1234 + $pr->id),
            'numeric_id'           => $pr->id,
            'title'                => $pr->title,
            'status'               => $this->mapPrStatus($this->statusValue($pr->status)),
            'department'           => $pr->category?->name ?? __('pr.department_default'),
            'budget'               => $this->money((float) $pr->budget, $pr->currency ?? 'AED'),
            'delivery'             => $this->longDate($pr->required_date),
            'location'             => $location ?: '—',
            'items'                => $items,
            'related_rfqs'         => $relatedRfqs,
            'timeline'             => $timeline,
            'description'          => $pr->description ?? '',
            'rfq_generated'        => (bool) $pr->rfq_generated,
        ];

        return view('dashboard.purchase-requests.show', ['pr' => $prData]);
    }

    /**
     * @return array<int, array{done:bool, title:string, who:string, when:string}>
     */
    private function buildPrTimeline(PurchaseRequest $pr): array
    {
        $events = [];

        $creatorName = trim(($pr->buyer?->first_name ?? '') . ' ' . ($pr->buyer?->last_name ?? '')) ?: __('common.system');

        $events[] = [
            'done'  => true,
            'title' => __('pr.timeline_created'),
            'who'   => $creatorName,
            'when'  => $pr->created_at?->format('M j, Y g:i A') ?? '',
        ];

        foreach ((array) ($pr->approval_history ?? []) as $entry) {
            $events[] = [
                'done'  => true,
                'title' => $entry['action'] ?? __('pr.timeline_status_change'),
                'who'   => $entry['by'] ?? __('common.system'),
                'when'  => isset($entry['at']) ? \Illuminate\Support\Carbon::parse($entry['at'])->format('M j, Y g:i A') : '',
            ];
        }

        $statusValue = $this->statusValue($pr->status);
        if (in_array($statusValue, ['submitted', 'pending_approval', 'approved', 'rejected'], true)) {
            $events[] = [
                'done'  => true,
                'title' => __('pr.timeline_submitted'),
                'who'   => $creatorName,
                'when'  => $pr->updated_at?->format('M j, Y g:i A') ?? '',
            ];
        }

        if ($statusValue === 'approved') {
            $events[] = [
                'done'  => true,
                'title' => __('pr.timeline_approved'),
                'who'   => __('common.system'),
                'when'  => $pr->updated_at?->format('M j, Y g:i A') ?? '',
            ];
        }

        foreach ($pr->rfqs as $rfq) {
            $events[] = [
                'done'  => true,
                'title' => __('pr.timeline_rfq_created', ['number' => $rfq->rfq_number]),
                'who'   => __('common.system'),
                'when'  => $rfq->created_at?->format('M j, Y g:i A') ?? '',
            ];
        }

        if (!$pr->rfq_generated && in_array($statusValue, ['approved', 'submitted'], true)) {
            $events[] = [
                'done'  => false,
                'title' => __('pr.timeline_rfq_pending'),
                'who'   => __('common.system'),
                'when'  => __('common.pending'),
            ];
        }

        return $events;
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->hasPermission('pr.create'), 403);

        $categories = \App\Models\Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $currencies = ['AED', 'USD', 'EUR', 'SAR'];

        return view('dashboard.purchase-requests.create', compact('categories', 'currencies'));
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('pr.create'), 403, 'Forbidden: missing purchase-requests.create permission.');

        $pr = $this->service->create($request->toModelData(
            buyerId: $user->id,
            companyId: $user->company_id,
        ));

        return redirect()
            ->route('dashboard.purchase-requests.show', ['id' => $pr->id])
            ->with('status', __('pr.created_successfully'));
    }

    public function submit(string $id): RedirectResponse
    {
        $pr = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('pr.submit'), 403, 'Forbidden: missing purchase-requests.update permission.');
        abort_unless($pr->buyer_id === $user->id, 403);

        $pr->update(['status' => PurchaseRequestStatus::SUBMITTED]);

        return redirect()
            ->route('dashboard.purchase-requests.show', ['id' => $pr->id])
            ->with('status', __('pr.submitted_for_approval'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $pr = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('pr.delete'), 403, 'Forbidden: missing purchase-requests.delete permission.');
        abort_unless($pr->buyer_id === $user->id, 403);
        abort_unless($pr->status === PurchaseRequestStatus::DRAFT, 422);

        $pr->delete();

        return redirect()
            ->route('dashboard.purchase-requests')
            ->with('status', __('pr.deleted_successfully'));
    }

    /**
     * Find a PR by id supporting both numeric ids and "PR-2024-XXXX" display ids.
     */
    private function findOrFail(string $id): PurchaseRequest
    {
        if (preg_match('/PR-\d{4}-(\d+)/', $id, $m)) {
            $modelId = max((int) $m[1] - 1234, 1);

            return PurchaseRequest::with(['buyer', 'category'])->findOrFail($modelId);
        }

        return PurchaseRequest::with(['buyer', 'category'])->findOrFail((int) $id);
    }

    /**
     * Map enum status to the keys the status-badge component expects.
     */
    private function mapPrStatus(string $status): string
    {
        return match ($status) {
            'pending_approval' => 'pending',
            'submitted'        => 'submitted',
            'approved'         => 'approved',
            'rejected'         => 'closed',
            'draft'            => 'draft',
            default            => 'draft',
        };
    }
}
