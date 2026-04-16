<?php

namespace App\Http\Controllers\Web;

use App\Enums\PurchaseRequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ExportsCsv;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\Category;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\PurchaseRequestApprovedNotification;
use App\Notifications\PurchaseRequestRejectedNotification;
use App\Notifications\PurchaseRequestSubmittedNotification;
use App\Services\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseRequestController extends Controller
{
    use ExportsCsv, FormatsForViews;

    public function __construct(private readonly PurchaseRequestService $service) {}

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('pr.view'), 403);

        $user = auth()->user();
        $companyId = $this->currentCompanyId();

        // Optional ?status= filter — clicking a sidebar shortcut like
        // "Pending Requests" lands here with status=pending_approval.
        $statusFilter = $request->query('status');
        $allowedStatuses = array_map(fn ($c) => $c->value, PurchaseRequestStatus::cases());
        $statusFilter = in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : null;

        $base = PurchaseRequest::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            // Branch managers see only PRs from their own branch — the company
            // manager (and any non-branch role) sees everything in the company.
            ->when($user?->isBranchManager() && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter));

        // CSV export hook (Phase 0 / task 0.9). Streams the same scoped
        // rows as the page, plus the buyer + category names, so the
        // download mirrors what the user sees on screen.
        if ($this->isCsvExport($request)) {
            $rows = (clone $base)->with(['buyer', 'category'])->latest()->get()
                ->map(fn (PurchaseRequest $pr) => [
                    'id' => $pr->id,
                    'pr_number' => $this->prDisplayNumber($pr),
                    'title' => $pr->title,
                    'status' => $this->statusValue($pr->status),
                    'category' => $pr->category?->name ?? '',
                    'budget' => (float) $pr->budget,
                    'currency' => $pr->currency,
                    'buyer' => trim(($pr->buyer?->first_name ?? '').' '.($pr->buyer?->last_name ?? '')),
                    'created_at' => $pr->created_at?->toDateTimeString(),
                ]);

            return $this->streamCsv($rows, 'purchase-requests');
        }

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', PurchaseRequestStatus::PENDING_APPROVAL->value)->count(),
            'approved' => (clone $base)->where('status', PurchaseRequestStatus::APPROVED->value)->count(),
            'progress' => (clone $base)->where('status', PurchaseRequestStatus::SUBMITTED->value)->count(),
            'closed' => (clone $base)->where('status', PurchaseRequestStatus::REJECTED->value)->count(),
        ];

        $requests = (clone $base)
            ->with(['buyer', 'category', 'rfqs.bids'])
            ->latest()
            ->get()
            ->map(function (PurchaseRequest $pr) {
                $rfqsCount = $pr->rfqs->count();
                $bidsCount = $pr->rfqs->sum(fn ($r) => $r->bids->count());

                return [
                    'id' => $this->prDisplayNumber($pr),
                    'numeric_id' => $pr->id,
                    'status' => $this->mapPrStatus($this->statusValue($pr->status)),
                    'tag' => $pr->category?->name ?? 'General',
                    'title' => $pr->title,
                    'desc' => $pr->description ?? '',
                    'creator' => trim(($pr->buyer?->first_name ?? '').' '.($pr->buyer?->last_name ?? '')) ?: 'Unknown',
                    'date' => $this->date($pr->created_at),
                    'amount' => $this->money((float) $pr->budget, $pr->currency),
                    'rfqs' => $rfqsCount,
                    'bids' => $bidsCount,
                    'progress' => $rfqsCount > 0
                        ? ['done' => $rfqsCount, 'total' => max($rfqsCount, 4)]
                        : null,
                ];
            })
            ->toArray();

        return view('dashboard.purchase-requests.index', compact('stats', 'requests', 'statusFilter'));
    }

    /**
     * Display label for a PR. We don't have a `pr_number` column yet, so this
     * formats `PR-{year}-{id}` consistently across index/show/CSV. Replaces
     * the old `sprintf('PR-2024-%04d', 1234 + $pr->id)` hack which both
     * baked in 2024 and forced views to do `preg_replace` to recover the id.
     */
    private function prDisplayNumber(PurchaseRequest $pr): string
    {
        $year = $pr->created_at?->format('Y') ?? date('Y');

        return sprintf('PR-%s-%04d', $year, $pr->id);
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('pr.view'), 403);

        $pr = $this->findOrFail($id);
        $pr->loadMissing(['rfqs.bids', 'rfqs.category', 'buyer', 'category']);

        $currency = $pr->currency ?? 'AED';

        $items = collect($pr->items ?? [])->values()->map(function ($item, $i) use ($currency) {
            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $unit = $item['unit'] ?? __('pr.unit_default');
            $price = isset($item['price']) ? (float) $item['price'] : null;

            return [
                'n' => $i + 1,
                'name' => $item['name'] ?? __('pr.item'),
                'desc' => $item['spec'] ?? $item['description'] ?? '',
                'qty' => trim($qty.' '.$unit),
                'qty_value' => $qty,
                'unit' => $unit,
                'price' => $price !== null ? $this->money($price * max($qty, 1), $currency) : '—',
                'has_price' => $price !== null,
            ];
        })->toArray();

        $relatedRfqs = $pr->rfqs->map(function (Rfq $rfq) {
            $statusValue = $this->statusValue($rfq->status);
            $statusKey = match ($statusValue) {
                'open' => $rfq->deadline && $rfq->deadline->isPast() ? 'expired' : 'open',
                'closed', 'cancelled' => 'closed',
                'draft' => 'draft',
                default => 'draft',
            };

            return [
                'id' => $rfq->rfq_number,
                'numeric_id' => $rfq->id,
                'status' => $statusKey,
                'tag' => $this->mapRfqTypeLabel($this->statusValue($rfq->type)),
                'title' => $rfq->title,
                'created' => $this->date($rfq->created_at),
                'deadline' => $this->date($rfq->deadline),
                'bids' => $rfq->bids->count(),
            ];
        })->toArray();

        $timeline = $this->buildPrTimeline($pr);

        $location = $this->formatLocation($pr->delivery_location);

        // Priority is derived from how soon the buyer needs the items.
        // We don't store priority separately (the create form has the field
        // but no column), so urgency-based derivation keeps it dynamic.
        $priority = $this->derivePriority($pr->required_date);

        // Additional services flags reflect which RFQ types have actually been
        // generated for this PR — if a logistics RFQ exists, logistics is
        // marked Required, otherwise Not Required. Same for customs clearance.
        $rfqTypes = $pr->rfqs->map(fn ($r) => $this->statusValue($r->type))->all();
        $additionalServices = [
            'logistics' => in_array('logistics', $rfqTypes, true),
            'clearance' => in_array('clearance', $rfqTypes, true),
        ];

        $user = auth()->user();
        $isOwner = $user && $pr->buyer_id === $user->id;
        $statusValue = $this->statusValue($pr->status);

        $bidCount = $pr->rfqs->sum(fn ($r) => $r->bids->count());

        // A PR is approvable when it is awaiting a manager decision and the
        // viewer holds pr.approve permission inside the same company. The
        // owner cannot approve their own request UNLESS they're the only
        // manager in the company (solo-manager fallback).
        $isSelfRequest = $user && $user->id === $pr->buyer_id;
        $soloManager = $isSelfRequest && ! User::where('company_id', $pr->company_id)
            ->where('id', '!=', $user->id)
            ->where('role', UserRole::COMPANY_MANAGER->value)
            ->exists();

        $canApprove = $user
            && $user->hasPermission('pr.approve')
            && $user->company_id === $pr->company_id
            && (! $isSelfRequest || $soloManager)
            && in_array($statusValue, ['pending_approval', 'submitted'], true);

        $prData = [
            'id' => $this->prDisplayNumber($pr),
            'numeric_id' => $pr->id,
            'title' => $pr->title,
            'status' => $this->mapPrStatus($statusValue),
            'priority' => $priority,
            'priority_label' => __('pr.priority_'.$priority),
            'created_by' => trim(($pr->buyer?->first_name ?? '').' '.($pr->buyer?->last_name ?? '')) ?: __('common.system'),
            'created_date' => $this->date($pr->created_at),
            'department' => $pr->category?->name ?? __('pr.department_default'),
            'budget' => $this->money((float) $pr->budget, $currency),
            'delivery' => $this->longDate($pr->required_date),
            'location' => $location ?: '—',
            'items' => $items,
            'related_rfqs' => $relatedRfqs,
            'rfq_count' => count($relatedRfqs),
            'bid_count' => $bidCount,
            'timeline' => $timeline,
            'description' => $pr->description ?? '',
            'rfq_generated' => (bool) $pr->rfq_generated,
            'additional_services' => $additionalServices,
            'can_delete' => $isOwner && $statusValue === 'draft' && $user?->hasPermission('pr.delete'),
            'can_edit' => $isOwner && $statusValue === 'draft',
            'can_approve' => $canApprove,
        ];

        return view('dashboard.purchase-requests.show', ['pr' => $prData]);
    }

    /**
     * Map an RFQ type to a human-readable label used as the colored tag on
     * the related-RFQs cards.
     */
    private function mapRfqTypeLabel(string $type): string
    {
        return match ($type) {
            'supplier' => __('pr.rfq_type_products'),
            'logistics' => __('pr.rfq_type_logistics'),
            'clearance' => __('pr.rfq_type_clearance'),
            'service_provider' => __('pr.rfq_type_services'),
            default => __('pr.rfq_type_general'),
        };
    }

    /**
     * Derive priority from how close the required delivery date is.
     * Within a week = high, within a month = medium, otherwise standard.
     */
    private function derivePriority($requiredDate): string
    {
        if (! $requiredDate) {
            return 'standard';
        }

        $days = Carbon::parse($requiredDate)->startOfDay()
            ->diffInDays(now()->startOfDay(), false) * -1;

        if ($days <= 7) {
            return 'high';
        }

        if ($days <= 30) {
            return 'medium';
        }

        return 'standard';
    }

    /**
     * @return array<int, array{done:bool, title:string, who:string, when:string}>
     */
    private function buildPrTimeline(PurchaseRequest $pr): array
    {
        $events = [];

        $creatorName = trim(($pr->buyer?->first_name ?? '').' '.($pr->buyer?->last_name ?? '')) ?: __('common.system');

        $events[] = [
            'done' => true,
            'title' => __('pr.timeline_created'),
            'who' => $creatorName,
            'when' => $pr->created_at?->format('M j, Y g:i A') ?? '',
        ];

        foreach ((array) ($pr->approval_history ?? []) as $entry) {
            $events[] = [
                'done' => true,
                'title' => $entry['action'] ?? __('pr.timeline_status_change'),
                'who' => $entry['by'] ?? __('common.system'),
                'when' => isset($entry['at']) ? Carbon::parse($entry['at'])->format('M j, Y g:i A') : '',
            ];
        }

        $statusValue = $this->statusValue($pr->status);
        if (in_array($statusValue, ['submitted', 'pending_approval', 'approved', 'rejected'], true)) {
            $events[] = [
                'done' => true,
                'title' => __('pr.timeline_submitted'),
                'who' => $creatorName,
                'when' => $pr->updated_at?->format('M j, Y g:i A') ?? '',
            ];
        }

        if ($statusValue === 'approved') {
            $events[] = [
                'done' => true,
                'title' => __('pr.timeline_approved'),
                'who' => __('common.system'),
                'when' => $pr->updated_at?->format('M j, Y g:i A') ?? '',
            ];
        }

        foreach ($pr->rfqs as $rfq) {
            $events[] = [
                'done' => true,
                'title' => __('pr.timeline_rfq_created', ['number' => $rfq->rfq_number]),
                'who' => __('common.system'),
                'when' => $rfq->created_at?->format('M j, Y g:i A') ?? '',
            ];
        }

        if (! $pr->rfq_generated && in_array($statusValue, ['approved', 'submitted'], true)) {
            $events[] = [
                'done' => false,
                'title' => __('pr.timeline_rfq_pending'),
                'who' => __('common.system'),
                'when' => __('common.pending'),
            ];
        }

        return $events;
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->hasPermission('pr.create'), 403);

        $categories = Category::query()
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

        $data = $request->toModelData(
            buyerId: $user->id,
            companyId: $user->company_id,
        );

        // Inherit the buyer's branch so branch managers see only their own
        // PRs in the approval queue. Null-safe — companies that don't use
        // branches still work because branch_id is nullable.
        $data['branch_id'] = $user->branch_id;

        // Buyers don't have a draft-edit step in this app — every submission
        // goes straight to the manager's approval inbox, so create the PR in
        // PENDING_APPROVAL state and notify the company managers immediately.
        $data['status'] = PurchaseRequestStatus::PENDING_APPROVAL;

        $pr = $this->service->create($data);

        $this->notifyManagersOfPendingApproval($pr);

        return redirect()
            ->route('dashboard.purchase-requests.success', ['id' => $pr->id])
            ->with('status', __('pr.created_successfully'));
    }

    /**
     * Send a database + mail notification to every company manager in the
     * buyer's company so they know there's a new PR waiting for approval.
     */
    private function notifyManagersOfPendingApproval(PurchaseRequest $pr): void
    {
        if (! $pr->company_id) {
            return;
        }

        $managers = User::query()
            ->where('company_id', $pr->company_id)
            ->where('role', UserRole::COMPANY_MANAGER->value)
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        Notification::send($managers, new PurchaseRequestSubmittedNotification($pr));
    }

    public function showSuccess(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('pr.view'), 403);

        $pr = $this->findOrFail($id);

        return view('dashboard.purchase-requests.success', [
            'pr' => [
                'id' => $this->prDisplayNumber($pr),
                'numeric_id' => $pr->id,
                'title' => $pr->title,
            ],
        ]);
    }

    public function submit(string $id): RedirectResponse
    {
        $pr = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('pr.submit'), 403, 'Forbidden: missing purchase-requests.update permission.');
        abort_unless($pr->buyer_id === $user->id, 403);

        $pr->update(['status' => PurchaseRequestStatus::PENDING_APPROVAL]);

        $this->notifyManagersOfPendingApproval($pr->fresh(['buyer']));

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
     * Manager approves a pending PR. The service transitions the PR to
     * APPROVED inside a transaction and auto-creates an RFQ from it.
     */
    public function approve(string $id): RedirectResponse
    {
        $pr = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('pr.approve'), 403, 'Forbidden: missing pr.approve permission.');
        abort_unless($user->company_id === $pr->company_id, 403, 'Cannot approve PRs of another company.');
        // Branch managers can only approve PRs that belong to their own branch.
        abort_if($user->isBranchManager() && $user->branch_id !== $pr->branch_id, 403, 'Cannot approve PRs from another branch.');
        // Self-approval ban: the requester can't approve their own PR.
        // Exception: solo-manager companies where there's no other approver.
        if ($user->id === $pr->buyer_id) {
            $otherApprovers = User::where('company_id', $pr->company_id)
                ->where('id', '!=', $user->id)
                ->where('role', UserRole::COMPANY_MANAGER->value)
                ->exists();
            abort_if($otherApprovers, 422, 'You cannot approve your own purchase request — another manager in your company should review it.');
        }

        $approved = $this->service->approve($pr->id, $user->id);

        if (! $approved) {
            return back()->withErrors(['status' => __('pr.approve_failed_status')]);
        }

        // Notify the original buyer so they know the decision (and that an
        // RFQ has been created for their request).
        $pr->loadMissing('buyer');
        if ($pr->buyer) {
            $pr->buyer->notify(new PurchaseRequestApprovedNotification($pr));
        }

        return redirect()
            ->route('dashboard.purchase-requests.show', ['id' => $pr->id])
            ->with('status', __('pr.approved_and_rfq_created'));
    }

    /**
     * Manager rejects a pending PR with an optional reason.
     */
    public function reject(string $id, Request $request): RedirectResponse
    {
        $pr = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('pr.approve'), 403, 'Forbidden: missing pr.approve permission.');
        abort_unless($user->company_id === $pr->company_id, 403, 'Cannot reject PRs of another company.');
        abort_if($user->isBranchManager() && $user->branch_id !== $pr->branch_id, 403, 'Cannot reject PRs from another branch.');
        abort_if($user->id === $pr->buyer_id, 422, 'You cannot reject your own purchase request.');

        $reason = (string) $request->input('reason', '');
        $rejected = $this->service->reject($pr->id, $user->id, $reason !== '' ? $reason : null);

        if (! $rejected) {
            return back()->withErrors(['status' => __('pr.reject_failed_status')]);
        }

        $pr->loadMissing('buyer');
        if ($pr->buyer) {
            $pr->buyer->notify(new PurchaseRequestRejectedNotification($pr, $reason !== '' ? $reason : null));
        }

        return redirect()
            ->route('dashboard.purchase-requests.show', ['id' => $pr->id])
            ->with('status', __('pr.rejected_successfully'));
    }

    /**
     * Bulk-approve a list of pending PRs in a single action. Each row is
     * authorised independently — any unauthorised id is silently skipped so
     * a single bad row doesn't kill the whole batch. Returns a status
     * summary on the redirect.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('pr.approve'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $approved = 0;
        $skipped = 0;
        $errors = 0;

        $prs = PurchaseRequest::whereIn('id', $data['ids'])->get();

        foreach ($prs as $pr) {
            // Same authorisation rules as the single-item approve action.
            if ($user->company_id !== $pr->company_id) {
                $skipped++;

                continue;
            }
            if ($user->isBranchManager() && $user->branch_id !== $pr->branch_id) {
                $skipped++;

                continue;
            }
            if ($user->id === $pr->buyer_id) {
                // Solo-manager exception: allow self-approval only when
                // there's no other manager in the company who could review.
                $otherApprovers = User::where('company_id', $pr->company_id)
                    ->where('id', '!=', $user->id)
                    ->where('role', UserRole::COMPANY_MANAGER->value)
                    ->exists();
                if ($otherApprovers) {
                    $skipped++;

                    continue;
                }
            }
            // Only PRs awaiting approval are eligible.
            if ($this->statusValue($pr->status) !== 'pending_approval') {
                $skipped++;

                continue;
            }

            try {
                $ok = $this->service->approve($pr->id, $user->id);
                if ($ok) {
                    $approved++;
                    $pr->loadMissing('buyer');
                    if ($pr->buyer) {
                        $pr->buyer->notify(new PurchaseRequestApprovedNotification($pr));
                    }
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return back()->with('status', __('pr.bulk_approve_summary', [
            'approved' => $approved,
            'skipped' => $skipped,
            'errors' => $errors,
        ]));
    }

    /**
     * Find a PR by id supporting both numeric ids and "PR-{YEAR}-{id}" display
     * labels. The number after the year is the real DB id (no +1234 offset).
     */
    private function findOrFail(string $id): PurchaseRequest
    {
        if (preg_match('/PR-\d{4}-(\d+)/', $id, $m)) {
            return PurchaseRequest::with(['buyer', 'category'])->findOrFail((int) $m[1]);
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
            'submitted' => 'submitted',
            'approved' => 'approved',
            'rejected' => 'closed',
            'draft' => 'draft',
            default => 'draft',
        };
    }
}
