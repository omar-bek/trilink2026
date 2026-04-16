<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ExportsCsv;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\Bid\StoreBidRequest;
use App\Models\Bid;
use App\Models\CompanySupplier;
use App\Models\Contract;
use App\Models\NegotiationMessage;
use App\Models\Rfq;
use App\Notifications\BidAcceptedNotification;
use App\Notifications\BidRejectedNotification;
use App\Notifications\LosingBidNotification;
use App\Services\BidService;
use App\Services\ContractService;
use App\Services\NegotiationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BidController extends Controller
{
    use ExportsCsv, FormatsForViews;

    public function __construct(
        private readonly BidService $service,
        private readonly ContractService $contracts,
        private readonly NegotiationService $negotiations,
    ) {}

    /**
     * Display label for a bid. We don't have a `bid_number` column yet, so
     * format `BID-{year}-{id}` consistently. Replaces the old
     * `$this->bidDisplayNumber($bid)` hack which baked in 2024
     * and added a meaningless +5421 offset.
     */
    private function bidDisplayNumber(Bid $bid): string
    {
        $year = $bid->created_at?->format('Y') ?? date('Y');

        return sprintf('BID-%s-%04d', $year, $bid->id);
    }

    /**
     * Build the structured negotiation rounds payload for the bid show view.
     * Each entry mirrors what the new "Negotiation" tab needs to render the
     * timeline + the action bar (counter / accept / reject) for the latest
     * open round. Pure read — no state changes.
     */
    private function negotiationViewModel(Bid $bid): array
    {
        $timeline = $this->negotiations->timeline($bid)->map(function (NegotiationMessage $m) use ($bid) {
            $offer = $m->offer ?? null;

            return [
                'id' => $m->id,
                'kind' => $m->kind,
                'side' => $m->sender_side,
                'sender' => $m->sender?->full_name ?? '—',
                'body' => $m->body,
                'round' => $m->round_number,
                'round_status' => $m->round_status,
                'when' => $m->created_at?->format('M j, Y g:i A') ?? '—',
                'offer' => $offer ? [
                    'amount' => isset($offer['amount']) ? $this->money((float) $offer['amount'], $bid->currency ?? 'AED') : null,
                    'amount_raw' => isset($offer['amount']) ? (float) $offer['amount'] : null,
                    'currency' => $offer['currency'] ?? $bid->currency ?? 'AED',
                    'delivery_days' => $offer['delivery_days'] ?? null,
                    'payment_terms' => $offer['payment_terms'] ?? null,
                    'reason' => $offer['reason'] ?? null,
                ] : null,
            ];
        })->toArray();

        $latestOpen = $this->negotiations->latestOpenRound($bid);
        $user = auth()->user();
        $userSide = $user && $user->company_id === $bid->company_id ? 'supplier' : 'buyer';

        $canAct = false;
        if ($latestOpen) {
            // The party that is allowed to respond is the OPPOSITE side of
            // whoever opened the round. Same-side users can post follow-up
            // counters but not accept/reject their own offer.
            $canAct = $user
                && $user->company_id
                && ($user->company_id === $bid->company_id || $user->company_id === $bid->rfq?->company_id)
                && $userSide !== $latestOpen->sender_side;
        }

        return [
            'timeline' => $timeline,
            'has_open' => (bool) $latestOpen,
            'open_round' => $latestOpen?->round_number,
            'open_amount' => $latestOpen ? $this->money((float) ($latestOpen->offer['amount'] ?? 0), $bid->currency ?? 'AED') : null,
            'can_act' => $canAct,
            'user_side' => $userSide,
            'next_round' => ($latestOpen?->round_number ?? 0) + 1,
        ];
    }

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('bid.view'), 403);

        $companyId = $this->currentCompanyId();
        $query = trim((string) $request->query('q', ''));
        $statusFilter = $request->query('status');

        // Company-centric tabs. The same company can have bids it RECEIVED
        // (other companies bidding on its RFQs) AND bids it SUBMITTED (its
        // own employees bidding on other companies' RFQs). The user can
        // pivot between the two views:
        //   - tab=received  → bids on OUR company's RFQs
        //   - tab=submitted → bids OUR company submitted on other RFQs
        //
        // Default tab is whichever side the company has more activity on,
        // so a dual-role tenant lands on the side that's "busier" but can
        // always switch with one click. The previous role-based dispatch
        // (isSupplierSideUser) hid the other side from cross-cutting roles
        // attached to dual-role companies — now both sides are always
        // reachable from a single, unified index page.
        $tabCounts = [
            'received' => $companyId
                ? Bid::query()->whereHas('rfq', fn ($q) => $q->where('company_id', $companyId))->count()
                : Bid::query()->count(),
            'submitted' => $companyId
                ? Bid::query()->where('company_id', $companyId)->count()
                : 0,
        ];
        $tab = $request->query('tab');
        if (! in_array($tab, ['received', 'submitted'], true)) {
            $tab = $tabCounts['submitted'] > $tabCounts['received'] ? 'submitted' : 'received';
        }

        if ($tab === 'submitted') {
            return $this->supplierIndex($companyId, $query, $statusFilter, $request, $tabCounts);
        }

        // Bids that belong to the current company's RFQs (buyer view)
        $base = Bid::query()->whereHas('rfq', function ($q) use ($companyId) {
            $q->when($companyId, fn ($qq) => $qq->where('company_id', $companyId));
        });

        if ($query !== '') {
            $base->whereHas('rfq', fn ($q) => $q->search($query, ['title', 'rfq_number']));
        }
        if ($statusFilter) {
            $base->where('status', $statusFilter);
        }

        // CSV export hook (Phase 0 / task 0.9).
        if ($this->isCsvExport($request)) {
            $rows = (clone $base)->with(['rfq', 'company'])->latest()->get()
                ->map(fn (Bid $bid) => [
                    'id' => $bid->id,
                    'bid_number' => $this->bidDisplayNumber($bid),
                    'rfq_number' => $bid->rfq?->rfq_number ?? '',
                    'rfq_title' => $bid->rfq?->title ?? '',
                    'supplier' => $bid->company?->name ?? '',
                    'price' => (float) $bid->price,
                    'currency' => $bid->currency,
                    'delivery_days' => (int) ($bid->delivery_time_days ?? 0),
                    'status' => $this->statusValue($bid->status),
                    'submitted_at' => $bid->created_at?->toDateTimeString(),
                ]);

            return $this->streamCsv($rows, 'bids');
        }

        $stats = [
            'total' => (clone $base)->count(),
            'under_review' => (clone $base)->where('status', BidStatus::UNDER_REVIEW->value)->count(),
            'shortlisted' => (clone $base)->where('status', BidStatus::SUBMITTED->value)->count(),
            'accepted' => (clone $base)->where('status', BidStatus::ACCEPTED->value)->count(),
            'rejected' => (clone $base)->where('status', BidStatus::REJECTED->value)->count(),
        ];

        // Eager-load rfq.bids so the per-row "received" count uses the
        // already-loaded collection (`->bids->count()`) instead of firing a
        // fresh query per bid. Same idea for the supplier rating: prefetch
        // averages for every distinct company in one query (Phase 0 / 0.5).
        $bidRows = (clone $base)->with(['rfq.bids', 'company'])->latest()->get();
        $ratings = $this->batchResolveSupplierRatings($bidRows->pluck('company_id')->filter()->unique()->all());

        $bids = $bidRows
            ->map(function (Bid $bid) use ($ratings) {
                $rfqBudget = (float) ($bid->rfq?->budget ?? $bid->price);
                $diff = $rfqBudget > 0 ? round((($rfqBudget - (float) $bid->price) / $rfqBudget) * 100, 1) : 0;
                $statusKey = $this->mapBidStatus($this->statusValue($bid->status));

                return [
                    'id' => $this->bidDisplayNumber($bid),
                    'numeric_id' => $bid->id,
                    'status' => $statusKey,
                    'shortlisted' => in_array($statusKey, ['submitted', 'shortlisted', 'accepted'], true),
                    'rfq' => $bid->rfq?->rfq_number ?? '—',
                    'rfq_title' => $bid->rfq?->title ?? '—',
                    // Anonymise the supplier name on the buyer-facing list
                    // until the bid is accepted; we surface the full company
                    // name on the show page only.
                    'supplier' => $bid->company?->name ?? '—',
                    // Phase 2 / Sprint 8 / task 2.8 — verification tier shown
                    // next to the supplier name on the bid card.
                    'verification_level' => $bid->company?->verification_level,
                    'rating' => $ratings[$bid->company_id] ?? null,
                    'received' => $bid->rfq?->bids->count() ?? 0,
                    'submitted' => $this->date($bid->created_at),
                    'expires' => $this->date($bid->validity_date),
                    'amount' => $this->money((float) $bid->price, $bid->currency),
                    'old_amount' => $this->money($rfqBudget, $bid->currency),
                    'diff' => abs($diff),
                    'days' => (int) ($bid->delivery_time_days ?? 0),
                    'terms' => $bid->payment_terms ?? '—',
                    'show_actions' => $statusKey === 'submitted',
                    'price_up' => $diff < 0,
                ];
            })
            ->toArray();

        $activeTab = 'received';

        return view('dashboard.bids.index', compact('stats', 'bids', 'tabCounts', 'activeTab'));
    }

    /**
     * Supplier-side "My Bids" index. Partitions bids by outcome into three
     * tabs (Active / Won / Lost) so the supplier can quickly scan status,
     * and surfaces the 5 KPIs the Figma shows (active count, won, lost,
     * total value, win rate).
     *
     * @param  array{received:int,submitted:int}|null  $tabCounts  Headline counts
     *                                                             for the company-centric view-switcher tabs at the top of the page.
     *                                                             Computed in index() so both branches stay consistent.
     */
    private function supplierIndex(?int $companyId, string $query = '', ?string $statusFilter = null, ?Request $request = null, ?array $tabCounts = null): View|StreamedResponse
    {
        $base = Bid::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        if ($query !== '') {
            $base->whereHas('rfq', function ($q) use ($query) {
                $q->search($query, ['title', 'rfq_number'])
                    ->orWhereHas('company', fn ($c) => $c->search($query, ['name']));
            });
        }
        if ($statusFilter) {
            $base->where('status', $statusFilter);
        }

        // CSV export hook (Phase 0 / task 0.9). Same scope as the page.
        if ($request && $this->isCsvExport($request)) {
            $rows = (clone $base)->with(['rfq.company'])->latest()->get()
                ->map(fn (Bid $bid) => [
                    'id' => $bid->id,
                    'bid_number' => $this->bidDisplayNumber($bid),
                    'rfq_number' => $bid->rfq?->rfq_number ?? '',
                    'rfq_title' => $bid->rfq?->title ?? '',
                    'buyer' => $bid->rfq?->company?->name ?? '',
                    'price' => (float) $bid->price,
                    'currency' => $bid->currency,
                    'delivery_days' => (int) ($bid->delivery_time_days ?? 0),
                    'status' => $this->statusValue($bid->status),
                    'submitted_at' => $bid->created_at?->toDateTimeString(),
                ]);

            return $this->streamCsv($rows, 'my-bids');
        }

        $totalCount = (clone $base)->count();
        $wonCount = (clone $base)->where('status', BidStatus::ACCEPTED->value)->count();
        $lostCount = (clone $base)->where('status', BidStatus::REJECTED->value)->count();
        $activeCount = (clone $base)->whereIn('status', [
            BidStatus::SUBMITTED->value,
            BidStatus::UNDER_REVIEW->value,
        ])->count();
        // Drafts + withdrawn live in their own tab so the supplier can find
        // and either resume them or accept that they're parked. Without a
        // dedicated bucket these rows used to vanish from the page entirely
        // — the badge said "2 bids" and the page only showed 1, which was
        // exactly the "things missing from the index" complaint.
        $draftCount = (clone $base)->whereIn('status', [
            BidStatus::DRAFT->value,
            BidStatus::WITHDRAWN->value,
        ])->count();

        $totalValue = (float) (clone $base)->sum('price');
        $winRate = $totalCount > 0 ? round(($wonCount / $totalCount) * 100, 1) : 0;

        $map = function (Bid $bid): array {
            $statusKey = $this->mapBidStatus($this->statusValue($bid->status));

            return [
                'id' => $this->bidDisplayNumber($bid),
                'numeric_id' => $bid->id,
                'status' => $statusKey,
                'rfq' => $bid->rfq?->rfq_number ?? '—',
                'rfq_title' => $bid->rfq?->title ?? '—',
                'buyer' => $bid->rfq?->is_anonymous
                    ? 'Buyer #'.str_pad((string) (($bid->rfq->company_id ?? 0) * 137 % 9999), 4, '0', STR_PAD_LEFT)
                    : ($bid->rfq?->company?->name ?? '—'),
                'amount' => $this->money((float) $bid->price, $bid->currency ?? 'AED'),
                'submitted' => $this->date($bid->created_at),
                'ago' => $bid->created_at?->diffForHumans(null, true) ?? '',
                'can_withdraw' => in_array($statusKey, ['submitted', 'under_review'], true),
            ];
        };

        $activeBids = (clone $base)
            ->whereIn('status', [BidStatus::SUBMITTED->value, BidStatus::UNDER_REVIEW->value])
            ->with(['rfq.company'])
            ->latest()
            ->get()
            ->map($map)
            ->all();

        $wonBids = (clone $base)
            ->where('status', BidStatus::ACCEPTED->value)
            ->with(['rfq.company'])
            ->latest()
            ->get()
            ->map($map)
            ->all();

        $lostBids = (clone $base)
            ->where('status', BidStatus::REJECTED->value)
            ->with(['rfq.company'])
            ->latest()
            ->get()
            ->map($map)
            ->all();

        // Drafts + withdrawn bids — surfaced in their own tab so they
        // don't disappear from the page. The supplier can resume a
        // draft from here or accept the withdrawal as final.
        $draftBids = (clone $base)
            ->whereIn('status', [BidStatus::DRAFT->value, BidStatus::WITHDRAWN->value])
            ->with(['rfq.company'])
            ->latest()
            ->get()
            ->map($map)
            ->all();

        return view('dashboard.bids.index', [
            // Supplier-side stats — distinct from buyer-side because the
            // numbers represent different things (Active/Won/Lost/Draft vs.
            // Total/UnderReview/Shortlisted/Accepted/Rejected). The view
            // picks the right stat block based on $activeTab.
            'stats' => [
                'active' => $activeCount,
                'won' => $wonCount,
                'lost' => $lostCount,
                'draft' => $draftCount,
                'total' => $totalCount,
                'total_value' => $this->money($totalValue, 'AED'),
                'win_rate' => $winRate,
            ],
            'active_bids' => $activeBids,
            'won_bids' => $wonBids,
            'lost_bids' => $lostBids,
            'draft_bids' => $draftBids,
            // Use the same variable names as the buyer-side index() so the
            // unified blade can read them without conditionally aliasing.
            'tabCounts' => $tabCounts,
            'activeTab' => 'submitted',
        ]);
    }

    /**
     * Build a uniform "payment schedule" array for display. Prefers the real
     * `payment_schedule` JSON stored on the bid; falls back to a 2-row schedule
     * derived from the legacy `payment_terms` string ("30% advance, 70% delivery")
     * so older bids still render a visible table.
     *
     * Returns null when there's no rating history yet — the view shows
     * a "no reviews" empty state instead of a fabricated number.
     */
    private function resolveSupplierRating(?int $companyId): ?float
    {
        if (! $companyId || ! Schema::hasTable('feedback')) {
            return null;
        }
        $avg = \DB::table('feedback')
            ->where('target_company_id', $companyId)
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    /**
     * Batch variant of resolveSupplierRating(): one query for an entire list
     * of company ids, returned as [company_id => rating]. Eliminates the
     * per-row N+1 the index pages used to suffer.
     *
     * @param  array<int,int>  $companyIds
     * @return array<int,float>
     */
    private function batchResolveSupplierRatings(array $companyIds): array
    {
        if ($companyIds === [] || ! Schema::hasTable('feedback')) {
            return [];
        }

        return \DB::table('feedback')
            ->whereIn('target_company_id', $companyIds)
            ->groupBy('target_company_id')
            ->selectRaw('target_company_id, AVG(rating) as avg_rating')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->target_company_id => round((float) $r->avg_rating, 1)])
            ->all();
    }

    /**
     * @return array<int, array{milestone:string, percentage:float, amount:string, stage:string}>
     */
    private function buildPaymentSchedule($bid, float $price): array
    {
        $currency = $bid->currency ?? 'AED';
        $stored = $bid->payment_schedule ?? [];

        if (is_array($stored) && ! empty($stored)) {
            return collect($stored)->map(function ($row) use ($price, $currency) {
                $pct = (float) ($row['percentage'] ?? 0);

                return [
                    'milestone' => (string) ($row['milestone'] ?? ''),
                    'percentage' => $pct,
                    'amount' => $this->money(
                        isset($row['amount']) && (float) $row['amount'] > 0
                            ? (float) $row['amount']
                            : round($price * $pct / 100, 2),
                        $currency
                    ),
                    'stage' => $this->milestoneStage((string) ($row['milestone'] ?? '')),
                ];
            })->values()->all();
        }

        // Legacy fallback: parse "30% advance, 70% on delivery".
        $pcts = [];
        if (preg_match_all('/(\d+)\s*%/', (string) $bid->payment_terms, $m)) {
            $pcts = array_map('intval', $m[1]);
        }
        if (empty($pcts)) {
            $pcts = [30, 70];
        }
        $labels = ['Advance Payment', 'On Production', 'On Delivery', 'Final Settlement', 'Retention'];
        $out = [];
        foreach ($pcts as $i => $pct) {
            $label = $labels[$i] ?? ('Milestone '.($i + 1));
            $out[] = [
                'milestone' => $label,
                'percentage' => (float) $pct,
                'amount' => $this->money(round($price * $pct / 100, 2), $currency),
                'stage' => $this->milestoneStage($label),
            ];
        }

        return $out;
    }

    /**
     * Classify a milestone name into a rendering "stage" so the view can
     * pick the right icon/color without hard-coding a mapping per-screen.
     */
    private function milestoneStage(string $name): string
    {
        $n = strtolower($name);

        return match (true) {
            str_contains($n, 'advance') || str_contains($n, 'upfront') => 'advance',
            str_contains($n, 'production') || str_contains($n, 'manufactur') => 'production',
            str_contains($n, 'deliver') || str_contains($n, 'shipment') => 'delivery',
            str_contains($n, 'final') || str_contains($n, 'settlement') || str_contains($n, 'completion') => 'final',
            default => 'milestone',
        };
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('bid.view'), 403);

        $bid = $this->findOrFail($id)->load(['rfq.category', 'rfq.company', 'company', 'provider']);

        // IDOR guard: a bid is only visible to (a) the supplier company that
        // submitted it, (b) the buyer company that owns the parent RFQ, or
        // (c) admin / government. Without this any user with bid.view could
        // enumerate competitor bid prices, payment terms and attachments.
        $this->authorizeBidParticipant($bid);

        // Per-bid role resolution (replaces the old isSupplierSideUser
        // dispatch). The same company can play both buyer + supplier roles
        // across different bids: we are the supplier when WE submitted the
        // bid, the buyer when WE own the parent RFQ. Buyer wins when both
        // are true (a company bidding on its own RFQ — defensive only).
        $myCompanyId = auth()->user()?->company_id;
        $isMyReceivedBid = $myCompanyId && $bid->rfq && (int) $bid->rfq->company_id === (int) $myCompanyId;
        $isMySubmittedBid = $myCompanyId && (int) $bid->company_id === (int) $myCompanyId;
        $myRole = $isMyReceivedBid ? 'buyer' : ($isMySubmittedBid ? 'supplier' : 'buyer');
        $direction = $myRole === 'buyer' ? 'incoming' : 'outgoing';

        $rfqBudget = (float) ($bid->rfq?->budget ?? $bid->price);
        $diff = $rfqBudget > 0 ? round((($rfqBudget - (float) $bid->price) / $rfqBudget) * 100, 1) : 0;
        $price = (float) $bid->price;

        // --- Payment terms split (Terms & Conditions tab) --------------------
        // `payment_terms` is usually a short label like "30% advance, 70% on
        // delivery". We derive the split from the first two numbers we find,
        // falling back to 30/70.
        $advancePct = 30;
        $finalPct = 70;
        if (preg_match_all('/(\d+)\s*%/', (string) $bid->payment_terms, $m)) {
            $advancePct = (int) ($m[1][0] ?? 30);
            $finalPct = (int) ($m[1][1] ?? (100 - $advancePct));
        }

        // --- Documents (Documents tab) ---------------------------------------
        // Bid.attachments is a JSON array of {name, path, size, mime}.
        // Legacy rows used {name, url} — handle both shapes.
        $documents = collect($bid->attachments ?? [])->values()->map(function ($doc, $idx) use ($bid) {
            $name = is_array($doc) ? ($doc['name'] ?? $doc['filename'] ?? 'document') : (string) $doc;
            $size = is_array($doc) ? ($doc['size'] ?? null) : null;
            // Newer rows have a `path` and we stream them through the download
            // action; older rows may carry a pre-baked URL.
            $url = is_array($doc)
                ? ($doc['url'] ?? route('dashboard.bids.attachment.download', ['id' => $bid->id, 'idx' => $idx]))
                : '#';

            return [
                'name' => $name,
                'size' => $size ? (is_numeric($size) ? round($size / 1024 / 1024, 1).' MB' : $size) : '—',
                'uploaded' => '—',
                'url' => $url,
            ];
        })->toArray();

        // --- History events (History tab) ------------------------------------
        // Derived from bid timestamps + status transitions. No new migration
        // needed — this is a "best effort" timeline from existing columns.
        $history = [];
        $history[] = [
            'title' => __('bids.history.submitted'),
            'who' => $bid->company?->name ?? '—',
            'desc' => __('bids.history.submitted_desc'),
            'when' => $bid->created_at?->format('M j, Y g:i A') ?? '—',
        ];
        if ($bid->updated_at && $bid->updated_at->gt($bid->created_at)) {
            $statusLabel = match ($this->statusValue($bid->status)) {
                'under_review' => __('bids.history.reviewed'),
                'accepted' => __('bids.history.accepted'),
                'rejected' => __('bids.history.rejected'),
                'withdrawn' => __('bids.history.withdrawn'),
                default => __('bids.history.updated'),
            };
            $history[] = [
                'title' => $statusLabel,
                'who' => __('bids.history.buyer_team'),
                'desc' => __('bids.history.status_change_desc'),
                'when' => $bid->updated_at->format('M j, Y g:i A'),
            ];
        }

        // --- Supplier summary (Bid Details tab) ------------------------------
        // Pulls real values where the schema supports it; only falls back to a
        // computed default when there is genuinely no data (e.g. brand-new
        // supplier with no completed contracts).
        $supplier = $bid->company;

        // Real "completed" count: contracts where this supplier is a
        // party AND the status is completed/active. Indexed lookup
        // through the contract_parties junction.
        $completedCount = 0;
        if ($supplier) {
            $completedCount = Contract::query()
                ->forCompany($supplier->id)
                ->whereIn('status', ['completed', 'active', 'signed'])
                ->count();
        }

        // Real rating: average of feedback rows targeting this supplier, when
        // the table exists. Returns null if there's no history yet — view
        // handles the "no reviews" empty state.
        $rating = null;
        $reviewCount = 0;
        if ($supplier && Schema::hasTable('feedback')) {
            $row = \DB::table('feedback')
                ->where('target_company_id', $supplier->id)
                ->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')
                ->first();
            $rating = $row && $row->avg ? round((float) $row->avg, 1) : null;
            $reviewCount = (int) ($row->cnt ?? 0);
        }

        // Certifications come from the new JSON column. Each row is
        // {name, issuer?, expires_at?, document_path?} — we just need names
        // for the bid card.
        $certs = collect($supplier?->certifications ?? [])
            ->map(fn ($c) => is_array($c) ? ($c['name'] ?? null) : (string) $c)
            ->filter()
            ->values()
            ->all();

        $supplierInfo = [
            'id' => $supplier?->id,
            'name' => $supplier?->name ?? '—',
            'location' => trim(($supplier?->city ?? '').($supplier?->country ? ', '.$supplier->country : '')) ?: '—',
            'registration' => $supplier?->registration_number ?? '—',
            'rating' => $rating,
            'reviews' => $reviewCount,
            'completed' => $completedCount,
            'years' => $supplier?->created_at ? max(1, (int) $supplier->created_at->diffInYears(now())) : 0,
            'certifications' => $certs,
            'description' => $supplier?->description ?? '—',
            // Phase 2 / Sprint 8 / task 2.8 — surface the trust tier so the
            // bid show header (and the comparison view) can render the
            // verification badge inline with the supplier name.
            'verification_level' => $supplier?->verification_level,
        ];

        $statusKey = $this->statusValue($bid->status);

        // Phase 2 — VAT breakdown + trade fields. Use the bid's stored
        // snapshot when present, fall back to legacy single-price for
        // historical bids.
        $hasVatSnapshot = $bid->subtotal_excl_tax !== null;
        $vat = [
            'treatment' => $bid->tax_treatment ?? 'exclusive',
            'rate' => $hasVatSnapshot ? (float) $bid->tax_rate_snapshot : 0.0,
            'subtotal' => $hasVatSnapshot ? (float) $bid->subtotal_excl_tax : $price,
            'tax_amount' => $hasVatSnapshot ? (float) $bid->tax_amount : 0.0,
            'total' => $hasVatSnapshot ? (float) $bid->total_incl_tax : $price,
            'subtotal_fmt' => $this->money($hasVatSnapshot ? (float) $bid->subtotal_excl_tax : $price, $bid->currency),
            'tax_amount_fmt' => $this->money($hasVatSnapshot ? (float) $bid->tax_amount : 0.0, $bid->currency),
            'total_fmt' => $this->money($hasVatSnapshot ? (float) $bid->total_incl_tax : $price, $bid->currency),
            'exemption_reason' => $bid->tax_exemption_reason,
        ];

        $countryList = config('countries.list', []);
        $countryOfOriginName = $bid->country_of_origin
            ? ($countryList[$bid->country_of_origin] ?? $bid->country_of_origin)
            : null;

        // Insurance badges — pull active, verified policies for the supplier
        // company. We surface them on the bid show page so the buyer sees
        // the trust signal without having to click into the supplier
        // profile. Falls back to an empty array if the supplier hasn't
        // uploaded any policies (or hasn't been verified yet).
        $insurancePolicies = [];
        if ($supplier && Schema::hasTable('company_insurances')) {
            $insurancePolicies = \DB::table('company_insurances')
                ->where('company_id', $supplier->id)
                ->where('status', 'verified')
                ->where('expires_at', '>=', now()->toDateString())
                ->whereNull('deleted_at')
                ->orderBy('expires_at', 'desc')
                ->get(['type', 'insurer', 'coverage_amount', 'currency', 'expires_at'])
                ->map(fn ($row) => [
                    'type' => $row->type,
                    'insurer' => $row->insurer,
                    'coverage' => $this->money((float) $row->coverage_amount, $row->currency ?? 'AED'),
                    'expires' => $this->longDate(Carbon::parse($row->expires_at)),
                ])
                ->all();
        }

        // ----- Supplier-side merge fields ---------------------------------
        // The unified show page also needs the data the legacy supplierShow()
        // built (timeline, competition, buyer info, can_withdraw) so that
        // when the current company is the bid SUBMITTER the view can render
        // the same supplier-side sections without dispatching elsewhere.
        $supplierStatusKey = $this->mapBidStatus($statusKey);
        $statusValueRaw = $this->statusValue($bid->status);

        $timeline = [
            [
                'title' => __('bids.history.submitted'),
                'when' => $bid->created_at?->format('M j, Y · g:i A') ?? '—',
                'done' => true,
            ],
            [
                'title' => __('bids.history.reviewed'),
                'when' => $bid->updated_at?->format('M j, Y · g:i A') ?? '—',
                'done' => in_array($statusValueRaw, ['under_review', 'accepted', 'rejected'], true),
            ],
            [
                'title' => match ($statusValueRaw) {
                    'accepted' => __('bids.history.accepted'),
                    'rejected' => __('bids.history.rejected'),
                    default => __('bids.awaiting_decision') ?? 'Pending Decision',
                },
                'when' => match ($statusValueRaw) {
                    'accepted', 'rejected' => $bid->updated_at?->format('M j, Y · g:i A') ?? '—',
                    default => __('bids.awaiting_buyer_response') ?? 'Awaiting buyer response',
                },
                'done' => in_array($statusValueRaw, ['accepted', 'rejected'], true),
            ],
        ];

        // Competition: peer bids on the same RFQ. Same data both sides see.
        $sibling = $bid->rfq ? $bid->rfq->bids()->get() : collect();
        $prices = $sibling->map(fn ($b) => (float) $b->price)->filter();
        $sortedSib = $sibling->sortBy('price')->values();
        $myPosition = $sortedSib->search(fn ($b) => $b->id === $bid->id);
        $myPosition = $myPosition !== false ? $myPosition + 1 : null;
        $competition = [
            'count' => $sibling->count(),
            'lowest' => $prices->isNotEmpty() ? $this->money($prices->min(), $bid->currency) : '—',
            'average' => $prices->isNotEmpty() ? $this->money($prices->avg(), $bid->currency) : '—',
            'my_position' => $myPosition,
            'my_bid' => $this->money($price, $bid->currency),
        ];

        // Buyer info (used when current viewer is the supplier).
        $buyerInfo = [
            'name' => $bid->rfq?->is_anonymous
                ? 'Buyer #'.str_pad((string) (($bid->rfq->company_id ?? 0) * 137 % 9999), 4, '0', STR_PAD_LEFT)
                : ($bid->rfq?->company?->name ?? '—'),
            'category' => $bid->rfq?->category?->name ?? '—',
            'rfq_ref' => '#'.($bid->rfq?->rfq_number ?? '—'),
        ];

        $bidData = [
            'id' => $this->bidDisplayNumber($bid),
            'numeric_id' => $bid->id,
            'status' => $this->mapBidStatus($statusKey),
            // Per-bid role-aware fields used by the unified view to flip
            // between buyer-side and supplier-side sections without a
            // separate template.
            'my_role' => $myRole,
            'direction' => $direction,
            // The "shortlisted" star pill on the bid show header is only true
            // when the bid is in an active, decision-pending state. Anything
            // already accepted/rejected/withdrawn isn't on the shortlist.
            'shortlisted' => in_array($statusKey, ['submitted', 'shortlisted'], true),
            'rfq' => $bid->rfq?->rfq_number ?? '—',
            'rfq_numeric_id' => $bid->rfq?->id,
            'rfq_title' => $bid->rfq?->title ?? '—',
            'rfq_category' => $bid->rfq?->category?->name ?? '—',
            'supplier' => $supplier?->name ?? '—',
            'supplier_info' => $supplierInfo,
            // Phase 2 — supplier's TRN, surfaced on the bid header so the
            // buyer can verify the tax invoice party.
            'supplier_trn' => $supplier?->tax_number,
            'amount' => $this->money($price, $bid->currency),
            'amount_raw' => $price,
            'currency' => $bid->currency ?? 'AED',
            'old_amount' => $this->money($rfqBudget, $bid->currency),
            'diff' => abs($diff),
            'savings' => $this->money(max(0, $rfqBudget - $price), $bid->currency),
            'price_up' => $diff < 0,
            'days' => (int) ($bid->delivery_time_days ?? 0),
            'estimated_delivery' => $bid->created_at
                ? $this->longDate($bid->created_at->copy()->addDays((int) ($bid->delivery_time_days ?? 0)))
                : '—',
            // Phase 2 — trade fidelity fields take precedence over the
            // legacy items[0] fallbacks.
            'incoterm' => $bid->incoterm,
            'country_of_origin' => $bid->country_of_origin,
            'country_of_origin_name' => $countryOfOriginName,
            'hs_code' => $bid->hs_code,
            'vat' => $vat,
            'insurance_policies' => $insurancePolicies,
            // Logistics + commercial terms come from the bid's items JSON if
            // the supplier provided them, otherwise from the RFQ-level
            // delivery terms. Defaults to a dash so the UI never shows a
            // fabricated value.
            'shipping_method' => $bid->items[0]['shipping_method'] ?? $bid->rfq?->items[0]['shipping_method'] ?? '—',
            'incoterms' => $bid->incoterm ?? $bid->items[0]['incoterms'] ?? $bid->rfq?->items[0]['delivery_terms'] ?? '—',
            'warranty' => $bid->items[0]['warranty'] ?? '—',
            'packaging' => $bid->items[0]['packaging'] ?? '—',
            'quality_certs' => is_array($bid->items[0]['quality_certs'] ?? null)
                ? $bid->items[0]['quality_certs']
                : (is_array($supplier?->certifications) ? collect($supplier->certifications)->map(fn ($c) => is_array($c) ? ($c['name'] ?? null) : $c)->filter()->values()->all() : []),
            'tech_spec' => $bid->rfq?->description ?? '—',
            'terms' => $bid->payment_terms ?? '—',
            'advance_pct' => $advancePct,
            'final_pct' => $finalPct,
            'advance_amount' => $this->money($price * $advancePct / 100, $bid->currency),
            'final_amount' => $this->money($price * $finalPct / 100, $bid->currency),
            'payment_schedule' => $this->buildPaymentSchedule($bid, $price),
            // Available payment rails on the platform — sourced from config
            // so a manager can add/remove options without a code change.
            'payment_methods' => config('procurement.payment_methods', ['Bank Transfer', 'Letter of Credit', 'Trade Finance']),
            'validity' => $this->longDate($bid->validity_date),
            'submitted' => $this->longDate($bid->created_at),
            'expires' => $this->longDate($bid->validity_date),
            'notes' => $bid->notes ?? '',
            'items' => collect($bid->items ?? [])->values()->map(function ($it, $i) use ($bid) {
                return [
                    'n' => $i + 1,
                    'name' => $it['name'] ?? 'Item',
                    'qty' => (int) ($it['qty'] ?? 0),
                    'unit' => $it['unit'] ?? '',
                    'unit_price' => $this->money((float) ($it['unit_price'] ?? 0), $bid->currency),
                ];
            })->toArray(),
            'documents' => $documents,
            'history' => $history,
            'ai_score' => $bid->ai_score['overall'] ?? $bid->ai_score['score'] ?? null,
            'negotiation' => $this->negotiationViewModel($bid),
            // ----- Supplier-side fields (rendered by the unified view
            // when $bid['my_role'] === 'supplier') ----------------------
            'delivery_days' => (int) ($bid->delivery_time_days ?? 0),
            'valid_until' => $bid->validity_date ? $bid->validity_date->format('M j, Y') : '—',
            'timeline' => $timeline,
            'competition' => $competition,
            'buyer' => $buyerInfo,
            'can_withdraw' => $myRole === 'supplier' && in_array($statusKey, ['submitted', 'under_review'], true),
            // True when the bidder is in the buyer's registered-supplier list.
            // Soft signal — no longer blocks the bid, but the buyer sees a
            // badge and the supplier saw a notice at submit time.
            'is_registered_supplier' => $bid->rfq && $bid->company_id
                ? CompanySupplier::isLocked((int) $bid->company_id, (int) $bid->rfq->company_id)
                : false,
        ];

        return view('dashboard.bids.show', ['bid' => $bidData]);
    }

    /**
     * Legacy supplier-side data builder kept as a private helper. Since the
     * unified show() now renders both sides from the same template, this
     * method is no longer routed to from show() and exists only as a
     * reference for the data shape that the supplier branch used to need.
     * Safe to delete in a follow-up cleanup pass.
     */
    private function supplierShow(Bid $bid): View
    {
        $price = (float) $bid->price;
        $currency = $bid->currency ?? 'AED';
        $statusKey = $this->mapBidStatus($this->statusValue($bid->status));

        // Status timeline — 3 stages that reflect the current bid state.
        $statusValue = $this->statusValue($bid->status);
        $timeline = [
            [
                'title' => 'Bid Submitted',
                'when' => $bid->created_at?->format('M j, Y · g:i A') ?? '—',
                'done' => true,
            ],
            [
                'title' => 'Under Review',
                'when' => $bid->updated_at?->format('M j, Y · g:i A') ?? '—',
                'done' => in_array($statusValue, ['under_review', 'accepted', 'rejected'], true),
            ],
            [
                'title' => match ($statusValue) {
                    'accepted' => 'Bid Accepted',
                    'rejected' => 'Bid Rejected',
                    default => 'Pending Decision',
                },
                'when' => match ($statusValue) {
                    'accepted', 'rejected' => $bid->updated_at?->format('M j, Y · g:i A') ?? '—',
                    default => 'Awaiting buyer response',
                },
                'done' => in_array($statusValue, ['accepted', 'rejected'], true),
            ],
        ];

        // Competition: other bids on the same RFQ.
        $sibling = $bid->rfq ? $bid->rfq->bids()->get() : collect();
        $prices = $sibling->map(fn ($b) => (float) $b->price)->filter();
        $sorted = $sibling->sortBy('price')->values();
        $myPosition = $sorted->search(fn ($b) => $b->id === $bid->id);
        $myPosition = $myPosition !== false ? $myPosition + 1 : null;

        // Attached documents — same format as the buyer view but the URL
        // routes through the download controller action so streams are gated
        // by permission.
        $documents = collect($bid->attachments ?? [])->values()->map(function ($doc, $idx) use ($bid) {
            $name = is_array($doc) ? ($doc['name'] ?? $doc['filename'] ?? 'document') : (string) $doc;
            $size = is_array($doc) ? ($doc['size'] ?? null) : null;
            $mime = is_array($doc) ? ($doc['mime'] ?? 'application/octet-stream') : 'application/octet-stream';
            $url = is_array($doc)
                ? ($doc['url'] ?? route('dashboard.bids.attachment.download', ['id' => $bid->id, 'idx' => $idx]))
                : '#';

            return [
                'name' => $name,
                'type' => strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                'size' => $size ? (is_numeric($size) ? round($size / 1024 / 1024, 1).' MB' : $size) : '—',
                'url' => $url,
            ];
        })->all();

        // Payment-terms split for the Terms & Conditions card.
        $advancePct = 30;
        $finalPct = 70;
        if (preg_match_all('/(\d+)\s*%/', (string) $bid->payment_terms, $m)) {
            $advancePct = (int) ($m[1][0] ?? 30);
            $finalPct = (int) ($m[1][1] ?? (100 - $advancePct));
        }

        $lineItems = collect($bid->items ?? [])->values()->map(function ($it) use ($currency) {
            $qty = (float) ($it['qty'] ?? 0);
            $price = (float) ($it['unit_price'] ?? 0);

            return [
                'name' => $it['name'] ?? 'Item',
                'qty' => $qty,
                'unit' => $it['unit'] ?? '',
                'unit_price' => $this->money($price, $currency),
                'total' => $this->money($price * max($qty, 1), $currency),
            ];
        })->all();

        // Phase 2 — VAT breakdown + trade terms.
        // Use the bid's stored snapshot when present; null fields mean a
        // legacy bid submitted before the migration, fall back gracefully.
        $hasVatSnapshot = $bid->subtotal_excl_tax !== null;
        $vat = [
            'treatment' => $bid->tax_treatment ?? 'exclusive',
            'rate' => $hasVatSnapshot ? (float) $bid->tax_rate_snapshot : 0.0,
            'subtotal' => $hasVatSnapshot ? (float) $bid->subtotal_excl_tax : $price,
            'tax_amount' => $hasVatSnapshot ? (float) $bid->tax_amount : 0.0,
            'total' => $hasVatSnapshot ? (float) $bid->total_incl_tax : $price,
            'subtotal_fmt' => $this->money($hasVatSnapshot ? (float) $bid->subtotal_excl_tax : $price, $currency),
            'tax_amount_fmt' => $this->money($hasVatSnapshot ? (float) $bid->tax_amount : 0.0, $currency),
            'total_fmt' => $this->money($hasVatSnapshot ? (float) $bid->total_incl_tax : $price, $currency),
            'exemption_reason' => $bid->tax_exemption_reason,
        ];

        // Country-of-origin display name (falls back to the code).
        $countryList = config('countries.list', []);
        $countryOfOriginName = $bid->country_of_origin
            ? ($countryList[$bid->country_of_origin] ?? $bid->country_of_origin)
            : null;

        // Insurance badges — pull supplier's own active, verified policies
        // so the supplier can confirm what coverage is being shown to
        // buyers alongside their bid.
        $insurancePolicies = [];
        if ($bid->company_id && Schema::hasTable('company_insurances')) {
            $insurancePolicies = \DB::table('company_insurances')
                ->where('company_id', $bid->company_id)
                ->where('status', 'verified')
                ->where('expires_at', '>=', now()->toDateString())
                ->whereNull('deleted_at')
                ->orderBy('expires_at', 'desc')
                ->get(['type', 'insurer', 'coverage_amount', 'currency', 'expires_at'])
                ->map(fn ($row) => [
                    'type' => $row->type,
                    'insurer' => $row->insurer,
                    'coverage' => $this->money((float) $row->coverage_amount, $row->currency ?? 'AED'),
                    'expires' => $this->longDate(Carbon::parse($row->expires_at)),
                ])
                ->all();
        }

        $data = [
            'id' => $this->bidDisplayNumber($bid),
            'numeric_id' => $bid->id,
            'status' => $statusKey,
            'rfq' => $bid->rfq?->rfq_number ?? '—',
            'rfq_title' => $bid->rfq?->title ?? '—',
            'rfq_numeric_id' => $bid->rfq?->id,
            'rfq_category' => $bid->rfq?->category?->name ?? '—',
            'amount' => $this->money($price, $currency),
            'submitted' => $bid->created_at?->format('M j, Y') ?? '—',
            'valid_until' => $bid->validity_date ? $bid->validity_date->format('M j, Y') : '—',
            'items' => $lineItems,
            'delivery_days' => (int) ($bid->delivery_time_days ?? 0),
            'terms' => $bid->payment_terms ?? '—',
            'advance_pct' => $advancePct,
            'final_pct' => $finalPct,
            'payment_schedule' => $this->buildPaymentSchedule($bid, $price),
            // Pull warranty from the bid's items JSON if the supplier added
            // it; nothing fabricated.
            'warranty' => $bid->items[0]['warranty'] ?? '—',
            'notes' => $bid->notes ?? '',
            'documents' => $documents,
            'timeline' => $timeline,
            // Phase 2 — trade fidelity fields.
            'incoterm' => $bid->incoterm,
            'country_of_origin' => $bid->country_of_origin,
            'country_of_origin_name' => $countryOfOriginName,
            'hs_code' => $bid->hs_code,
            'vat' => $vat,
            'insurance_policies' => $insurancePolicies,
            'currency' => $currency,
            'buyer' => [
                'name' => $bid->rfq?->is_anonymous ? 'Buyer #'.str_pad((string) (($bid->rfq->company_id ?? 0) * 137 % 9999), 4, '0', STR_PAD_LEFT) : ($bid->rfq?->company?->name ?? '—'),
                'category' => $bid->rfq?->category?->name ?? '—',
                'rfq_ref' => '#'.($bid->rfq?->rfq_number ?? '—'),
            ],
            'competition' => [
                'count' => $sibling->count(),
                'lowest' => $prices->isNotEmpty() ? $this->money($prices->min(), $currency) : '—',
                'my_position' => $myPosition,
                'my_bid' => $this->money($price, $currency),
            ],
            'can_withdraw' => in_array($statusKey, ['submitted', 'under_review'], true),
            'negotiation' => $this->negotiationViewModel($bid),

            // Buyer-view-compatible keys. The unified show.blade.php was
            // written for the buyer surface first, so we populate every
            // key it reads with safe defaults — the view still renders
            // correctly for the supplier because the accept / reject
            // controls are policy-gated (@can) and hide automatically.
            'supplier' => $bid->company?->name ?? '—',
            'supplier_info' => [
                'id' => $bid->company_id,
                'rating' => null,
                'reviews' => 0,
                'verification_level' => $bid->company?->verification_level?->value ?? null,
                'category' => $bid->rfq?->category?->name ?? '—',
            ],
            'old_amount' => $this->money($price, $currency),
            'savings' => $this->money(0, $currency),
            'diff' => 0,
            'price_up' => false,
            'shortlisted' => false,
        ];

        return view('dashboard.bids.show', ['bid' => $data]);
    }

    public function store(StoreBidRequest $request, int $rfq): RedirectResponse
    {
        $rfqModel = Rfq::findOrFail($rfq);
        $user = $request->user();

        abort_unless($user?->hasPermission('bid.submit'), 403, 'Forbidden: missing bids.create permission.');
        abort_if($user->isAdmin() || ! $user->company_id, 403, 'Only supplier/buyer accounts can submit bids.');

        // Normalize payment schedule: compute each milestone's amount from the
        // total price + percentage so downstream consumers (contract creation,
        // payment tracking) don't have to recompute it later.
        $price = (float) $request->input('price');
        $schedule = collect($request->input('payment_schedule', []))
            ->map(fn ($row) => [
                'milestone' => $row['milestone'] ?? '',
                'percentage' => (float) ($row['percentage'] ?? 0),
                'amount' => round($price * ((float) ($row['percentage'] ?? 0)) / 100, 2),
            ])
            ->values()
            ->all();

        // Pre-stage uploads BEFORE opening the transaction: file writes to the
        // local disk aren't rolled back by DB::rollBack, so if we wrote them
        // inside the transaction and a later DB error triggered a rollback,
        // the bid row would vanish but the files would leak on disk. Staging
        // first and only persisting paths after the DB commit keeps both
        // sides consistent, and on failure we clean up the staged files.
        $stagedAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }
                $path = $file->store('bid-attachments/staging', 'local');
                $stagedAttachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
        }

        try {
            $result = DB::transaction(function () use ($request, $rfqModel, $user, $price, $schedule, $stagedAttachments) {
                $created = $this->service->create([
                    'rfq_id' => $rfqModel->id,
                    'company_id' => $user->company_id,
                    'provider_id' => $user->id,
                    'status' => BidStatus::SUBMITTED,
                    'price' => $price,
                    'currency' => $request->input('currency', $rfqModel->currency ?? 'AED'),
                    'delivery_time_days' => $request->input('delivery_time_days'),
                    'payment_terms' => $request->input('payment_terms'),
                    'payment_schedule' => $schedule,
                    'validity_date' => $request->input('validity_date'),
                    'notes' => $request->input('notes'),
                    'items' => $request->input('items', []),
                    // Phase 2 trade fields. The StoreBidRequest::prepareForValidation
                    // already computed and merged subtotal/tax/total based on the
                    // supplier's chosen treatment, so we just pass them through.
                    'incoterm' => $request->input('incoterm'),
                    'country_of_origin' => $request->input('country_of_origin'),
                    'hs_code' => $request->input('hs_code'),
                    'tax_treatment' => $request->input('tax_treatment'),
                    'tax_exemption_reason' => $request->input('tax_exemption_reason'),
                    'tax_rate_snapshot' => $request->input('tax_rate_snapshot'),
                    'subtotal_excl_tax' => $request->input('subtotal_excl_tax'),
                    'tax_amount' => $request->input('tax_amount'),
                    'total_incl_tax' => $request->input('total_incl_tax'),
                ]);

                // Business rule violation from the service — abort the
                // transaction so the caller can surface the string error.
                if (is_string($created)) {
                    return $created;
                }

                if (! empty($stagedAttachments)) {
                    $moved = [];
                    foreach ($stagedAttachments as $att) {
                        $finalPath = "bid-attachments/{$created->id}/".basename($att['path']);
                        Storage::disk('local')->move($att['path'], $finalPath);
                        $moved[] = array_merge($att, ['path' => $finalPath]);
                    }
                    $created->update(['attachments' => $moved]);
                }

                return $created;
            });
        } catch (\Throwable $e) {
            // DB rolled back; staged files never made it to a bid folder.
            foreach ($stagedAttachments as $att) {
                Storage::disk('local')->delete($att['path']);
            }
            throw $e;
        }

        if (is_string($result)) {
            foreach ($stagedAttachments as $att) {
                Storage::disk('local')->delete($att['path']);
            }

            return back()->withErrors(['bid' => $result])->withInput();
        }

        $redirect = redirect()
            ->route('dashboard.rfqs.show', ['id' => $rfqModel->id])
            ->with('status', __('bids.submitted_successfully'));

        // Soft note: if the bidder is a registered supplier of the buyer's
        // company, the bid is still accepted but we flag it so the supplier
        // knows the buyer will see a "registered supplier" badge on their bid.
        if (CompanySupplier::isLocked((int) $user->company_id, (int) $rfqModel->company_id)) {
            $redirect->with('warning', __('bids.registered_supplier_notice'));
        }

        return $redirect;
    }

    public function accept(string $id): RedirectResponse
    {
        $bid = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('bid.accept'), 403, 'Forbidden: missing bids.evaluate permission.');
        // Verify the buyer owns the RFQ.
        abort_unless($bid->rfq?->company_id === $user->company_id, 403);

        // The accept action is the moment a contract gets generated. We do
        // the bid status flip + the contract creation in one transaction so
        // a half-accepted bid never ends up without a contract attached.
        //
        // Concurrency safety: re-fetch the bid AND its RFQ under row locks
        // before flipping status. Two simultaneous accept clicks (e.g. two
        // buyer-side users racing to win a bid) would otherwise both pass
        // the status check and create duplicate contracts. The lockForUpdate
        // serialises them: the second click sees status === ACCEPTED and
        // bails out with a 409 instead of double-creating.
        [$contract, $rejectedBids] = DB::transaction(function () use ($bid) {
            $lockedBid = Bid::with('rfq')
                ->whereKey($bid->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBid) {
                abort(404);
            }

            // Lock the RFQ too — the createFromBid() side flips the RFQ
            // status to AWARDED and we don't want a sibling accept to
            // re-award the same RFQ in parallel.
            Rfq::whereKey($lockedBid->rfq_id)->lockForUpdate()->first();

            $currentStatus = $lockedBid->status instanceof \BackedEnum
                ? $lockedBid->status->value
                : (string) $lockedBid->status;

            // Allowable starting states for an accept: SUBMITTED or
            // UNDER_REVIEW. Anything else (already accepted, rejected,
            // withdrawn) means the race lost — return a 409 so the buyer
            // sees a clear error instead of a confusing "second contract".
            if (! in_array($currentStatus, [BidStatus::SUBMITTED->value, BidStatus::UNDER_REVIEW->value], true)) {
                abort(409, 'This bid is no longer in an acceptable state — it may already have been accepted or withdrawn.');
            }

            $lockedBid->update(['status' => BidStatus::ACCEPTED]);

            // Snapshot siblings BEFORE we flip their status so we can notify
            // each one individually — once they're updated, a collection query
            // would still return them but we want the models with their
            // providers loaded for Notification::send().
            $siblings = Bid::with('provider')
                ->where('rfq_id', $lockedBid->rfq_id)
                ->where('id', '!=', $lockedBid->id)
                ->get();

            Bid::where('rfq_id', $lockedBid->rfq_id)
                ->where('id', '!=', $lockedBid->id)
                ->update(['status' => BidStatus::REJECTED->value]);

            return [
                $this->contracts->createFromBid($lockedBid->fresh(['rfq', 'company'])),
                $siblings,
            ];
        });

        // Notify the winner (outside the transaction so a mailer failure
        // doesn't roll back the accept).
        $winningBid = $bid->fresh(['rfq.category', 'company', 'provider']);
        if ($winningBid->provider) {
            $winningBid->provider->notify(new BidAcceptedNotification($winningBid, $contract));
        }

        // Notify rejected submitters with the procurement-grade "losing
        // bid" notice. This replaces the older per-bid BidRejected ping
        // — the new wording explicitly says "the buyer chose someone
        // else for this RFQ" instead of the harsher "your bid was
        // rejected", which is what mature procurement platforms do.
        foreach ($rejectedBids as $rejected) {
            if ($rejected->provider) {
                $rejected->provider->notify(new LosingBidNotification($rejected));
            }
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.created_from_bid'));
    }

    /**
     * Buyer-side bulk reject — flip a list of UNDER_REVIEW / SUBMITTED bids
     * to REJECTED in one action. Authorisation is checked per row (only the
     * RFQ owner can reject) so a single bad id won't kill the whole batch.
     * Used by the "Reject Selected" button on the buyer's bid list.
     */
    public function bulkReject(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('bid.accept'), 403, 'Forbidden: missing bids.evaluate permission.');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $rejected = 0;
        $skipped = 0;

        $bids = Bid::with(['rfq', 'provider'])->whereIn('id', $data['ids'])->get();

        foreach ($bids as $bid) {
            // Same authorisation rule as the single-bid action: must own the RFQ.
            if ($bid->rfq?->company_id !== $user->company_id) {
                $skipped++;

                continue;
            }

            // Only bids still in an open state can be rejected.
            $statusValue = $this->statusValue($bid->status);
            if (! in_array($statusValue, ['submitted', 'under_review'], true)) {
                $skipped++;

                continue;
            }

            $bid->update(['status' => BidStatus::REJECTED->value]);
            $rejected++;

            if ($bid->provider) {
                $bid->provider->notify(new BidRejectedNotification($bid));
            }
        }

        return back()->with('status', __('bids.bulk_reject_summary', [
            'rejected' => $rejected,
            'skipped' => $skipped,
        ]));
    }

    public function withdraw(string $id): RedirectResponse
    {
        $bid = $this->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('bid.withdraw'), 403, 'Forbidden: missing bids.withdraw permission.');
        abort_unless($bid->provider_id === $user->id, 403);

        $result = $this->service->withdraw($bid->id);

        if (! $result) {
            return back()->withErrors(['bid' => __('bids.cannot_withdraw')]);
        }

        return redirect()
            ->route('dashboard.bids')
            ->with('status', __('bids.withdrawn_successfully'));
    }

    /**
     * Stream a bid attachment back to an authorized user. Only the bid's own
     * supplier or the RFQ buyer's company can download. Index is 0-based
     * against the `attachments` JSON array stored on the bid.
     */
    public function downloadAttachment(string $id, int $idx): StreamedResponse
    {
        $bid = $this->findOrFail($id)->load('rfq');
        abort_unless(auth()->user()?->hasPermission('bid.view'), 403);
        $this->authorizeBidParticipant($bid);

        $attachments = (array) ($bid->attachments ?? []);
        $entry = $attachments[$idx] ?? null;
        abort_unless(is_array($entry) && isset($entry['path']), 404);
        abort_unless(Storage::disk('local')->exists($entry['path']), 404);

        return Storage::disk('local')->download(
            $entry['path'],
            $entry['name'] ?? basename($entry['path'])
        );
    }

    /**
     * Generate a PDF summary of the bid (used by the "Download" action on
     * the supplier bid detail page). Mirrors ContractController::pdf().
     */
    public function download(string $id): Response
    {
        $bid = $this->findOrFail($id)->load(['rfq.company', 'rfq.category', 'company']);
        abort_unless(auth()->user()?->hasPermission('bid.view'), 403);
        $this->authorizeBidParticipant($bid);

        $price = (float) $bid->price;
        $pdf = Pdf::loadView('dashboard.bids.pdf', [
            'bid' => $bid,
            'schedule' => $this->buildPaymentSchedule($bid, $price),
        ]);

        return $pdf->download($this->bidDisplayNumber($bid).'.pdf');
    }

    private function findOrFail(string $id): Bid
    {
        if (preg_match('/BID-\d{4}-(\d+)/', $id, $m)) {
            return Bid::findOrFail((int) $m[1]);
        }

        return Bid::findOrFail((int) $id);
    }

    /**
     * IDOR guard for bid show / download paths. A bid is only accessible to
     * (a) the supplier company that submitted it, (b) the buyer company that
     * owns the parent RFQ, or (c) admin / government users. Aborts 404
     * (not 403) so the existence of the bid is not leaked to a probing
     * attacker enumerating IDs.
     */
    private function authorizeBidParticipant(Bid $bid): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(404);
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }
        if (method_exists($user, 'isGovernment') && $user->isGovernment()) {
            return;
        }
        $userCompanyId = (int) ($user->company_id ?? 0);
        if ($userCompanyId === 0) {
            abort(404);
        }
        $isSupplier = $userCompanyId === (int) $bid->company_id;
        $isBuyer = $userCompanyId === (int) ($bid->rfq?->company_id ?? 0);
        if (! $isSupplier && ! $isBuyer) {
            abort(404);
        }
    }

    private function mapBidStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'draft',
            'submitted' => 'submitted',
            'under_review' => 'under_review',
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'withdrawn' => 'closed',
            default => 'draft',
        };
    }
}
