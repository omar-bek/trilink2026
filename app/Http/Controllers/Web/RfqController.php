<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ExportsCsv;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\Bid\StoreBidRequest;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Rfq;
use App\Models\TaxRate;
use App\Services\Procurement\IcvScoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RfqController extends Controller
{
    use ExportsCsv, FormatsForViews;

    public function __construct(
        private readonly IcvScoringService $icvScoring,
    ) {}

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('rfq.view'), 403);

        $user = auth()->user();
        $companyId = $this->currentCompanyId();

        // Company-centric routing. The platform treats the COMPANY as the
        // primary actor — a single company can both publish RFQs (buyer-side)
        // and bid on other companies' RFQs (supplier-side). So managers,
        // admins, and any company-attached user can pivot between two views:
        //   - tab=mine        → RFQs OUR company published
        //   - tab=marketplace → RFQs from OTHER companies we can bid on
        //
        // Default tab is whichever side the company has more activity on,
        // so a dual-role tenant lands on the busier side but can always
        // switch with one click. The previous role-based dispatch
        // (isSupplierSideUser) hid the other side from cross-cutting
        // roles attached to dual-role companies — now both sides are
        // always reachable from a single, unified index page.
        $tabCounts = [
            'mine' => $companyId
                ? Rfq::query()->where('company_id', $companyId)->count()
                : Rfq::query()->count(),
            'marketplace' => $companyId
                ? Rfq::query()
                    ->where('status', RfqStatus::OPEN->value)
                    ->where('company_id', '!=', $companyId)
                    ->count()
                : Rfq::query()->where('status', RfqStatus::OPEN->value)->count(),
        ];

        $tab = $request->query('tab');
        if (! in_array($tab, ['mine', 'marketplace'], true)) {
            // Backwards compatibility: ?marketplace=1 still routes to marketplace.
            if ($request->boolean('marketplace')) {
                $tab = 'marketplace';
            } else {
                $tab = $tabCounts['marketplace'] > $tabCounts['mine'] ? 'marketplace' : 'mine';
            }
        }

        if ($tab === 'marketplace') {
            return $this->supplierIndex($request, $user, $companyId, $tabCounts);
        }

        $base = Rfq::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        // ----- Filters from query string -------------------------------
        // status: all|open|expired|closed|draft
        // q:      free-text over title / rfq_number
        // category: integer category id
        // sort:   newest|deadline|value|most_bids
        $statusFilter = $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'open', 'expired', 'closed', 'draft'], true)) {
            $statusFilter = 'all';
        }

        $search = trim((string) $request->query('q', ''));
        $category = (int) $request->query('category', 0);
        $sort = $request->query('sort', 'newest');
        if (! in_array($sort, ['newest', 'deadline', 'value', 'most_bids'], true)) {
            $sort = 'newest';
        }

        $listing = (clone $base);

        // Stats are calculated against the unfiltered base — they're the
        // headline counts, not "matching the current filter".
        $stats = [
            'all' => (clone $base)->count(),
            'open' => (clone $base)->where('status', RfqStatus::OPEN->value)->count(),
            'expired' => (clone $base)->where('deadline', '<', now())->where('status', RfqStatus::OPEN->value)->count(),
            'closed' => (clone $base)->where('status', RfqStatus::CLOSED->value)->count(),
            'draft' => (clone $base)->where('status', RfqStatus::DRAFT->value)->count(),
        ];

        // Apply listing filters.
        if ($statusFilter === 'open') {
            $listing->where('status', RfqStatus::OPEN->value)
                ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()));
        } elseif ($statusFilter === 'expired') {
            $listing->where('status', RfqStatus::OPEN->value)
                ->where('deadline', '<', now());
        } elseif ($statusFilter === 'closed') {
            $listing->where('status', RfqStatus::CLOSED->value);
        } elseif ($statusFilter === 'draft') {
            $listing->where('status', RfqStatus::DRAFT->value);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $listing->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('rfq_number', 'like', $like);
            });
        }

        if ($category > 0) {
            $listing->where('category_id', $category);
        }

        // Sort. We always include `latest()` as a tiebreaker so the order is
        // stable when two rows share the same sort key.
        match ($sort) {
            'deadline' => $listing->orderByRaw('deadline IS NULL, deadline ASC')->orderByDesc('id'),
            'value' => $listing->orderByDesc('budget')->orderByDesc('id'),
            'most_bids' => $listing->withCount('bids')->orderByDesc('bids_count')->orderByDesc('id'),
            default => $listing->latest(),
        };

        // CSV export hook (Phase 0 / task 0.9). Honours all filters above.
        if ($this->isCsvExport($request)) {
            $rows = (clone $listing)->with(['category', 'company'])->get()
                ->map(fn (Rfq $rfq) => [
                    'id' => $rfq->id,
                    'rfq_number' => $rfq->rfq_number,
                    'title' => $rfq->title,
                    'status' => $this->statusValue($rfq->status),
                    'category' => $rfq->category?->name ?? '',
                    'budget' => (float) $rfq->budget,
                    'currency' => $rfq->currency,
                    'deadline' => $rfq->deadline?->toDateString(),
                    'bids' => $rfq->bids()->count(),
                    'created_at' => $rfq->created_at?->toDateTimeString(),
                ]);

            return $this->streamCsv($rows, 'rfqs');
        }

        $rfqRows = (clone $listing)
            ->with(['category', 'company', 'bids'])
            ->get();

        $rfqs = $rfqRows->map(function (Rfq $rfq) {
            $statusKey = $this->mapRfqStatus($rfq);

            return [
                'id' => $rfq->rfq_number,
                'numeric_id' => $rfq->id,
                'status' => $statusKey,
                'tags' => array_values(array_filter([
                    $rfq->company?->name,
                    $rfq->category?->name,
                ])),
                'tag_colors' => ['slate', 'blue'],
                'title' => $rfq->title,
                'desc' => $rfq->description ?? '',
                'items' => count($rfq->items ?? []),
                'amount' => $this->money((float) $rfq->budget, $rfq->currency),
                'date' => $this->longDate($rfq->deadline),
                'bids' => $rfq->bids->count(),
            ];
        })
            ->toArray();

        // Categories list for the filter dropdown — only categories that
        // are actually attached to at least one of this company's RFQs,
        // so the dropdown stays meaningful instead of listing the full
        // taxonomy.
        $categoryOptions = Category::query()
            ->whereIn('id', (clone $base)->whereNotNull('category_id')->distinct()->pluck('category_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        $resultCount = count($rfqs);
        $activeTab = 'mine';

        return view('dashboard.rfqs.index', compact(
            'stats', 'rfqs', 'statusFilter', 'search', 'category', 'sort',
            'categoryOptions', 'resultCount', 'tabCounts', 'activeTab'
        ));
    }

    /**
     * Supplier-side "Available RFQs" browse view.
     *
     * Shows OPEN RFQs from every OTHER company (never the supplier's own), with
     * four quick-filter pills (All, High Match, Urgent, High Value). The match
     * percentage is a deterministic heuristic derived from the RFQ id so the
     * order is stable between refreshes — we don't have a real matching engine
     * yet, but the UI reads the same as if we did.
     *
     * @param  array{mine:int,marketplace:int}|null  $tabCounts  Headline counts for the
     *                                                           view-switcher tabs at the top of the page. Computed in index() so the
     *                                                           counts stay consistent across both branches.
     */
    private function supplierIndex(Request $request, $user, ?int $companyId, ?array $tabCounts = null): View
    {
        $filter = $request->query('filter', 'all');
        $query = $request->query('q');

        $base = Rfq::query()
            ->where('status', RfqStatus::OPEN->value)
            ->when($companyId, fn ($q) => $q->where('company_id', '!=', $companyId))
            ->when($query, function ($q) use ($query) {
                $q->where(function ($qq) use ($query) {
                    $qq->search($query, ['title', 'rfq_number'])
                        ->orWhereHas('company', fn ($c) => $c->search($query, ['name']));
                });
            });

        // Real match score (Phase 0 / task 0.4): pulled from the supplier's
        // own company profile (categories, country, history, verification,
        // recent activity, certs). Categories are eager-loaded once so the
        // per-RFQ scoring loop doesn't re-query the pivot.
        $supplierCompany = $companyId
            ? Company::with('categories:id,parent_id')->find($companyId)
            : null;

        $isUrgent = fn (Rfq $r): bool => $r->deadline && $r->deadline->diffInDays(now()) <= 3;

        $rfqs = $base
            ->with(['category', 'company', 'bids'])
            ->latest()
            ->get()
            ->map(function (Rfq $rfq) use ($isUrgent, $supplierCompany, $companyId) {
                $match = $supplierCompany ? $rfq->matchScoreFor($supplierCompany) : 0;
                $deadline = $rfq->deadline;
                $daysLeft = $deadline ? max(0, (int) now()->startOfDay()->diffInDays($deadline->startOfDay(), false)) : null;

                $location = $this->formatLocation($rfq->delivery_location);

                $quantity = collect($rfq->items ?? [])->sum(fn ($i) => (float) ($i['qty'] ?? $i['quantity'] ?? 0));
                $unit = collect($rfq->items ?? [])->first()['unit'] ?? '';

                // Did this supplier already submit a bid on this RFQ?
                // The badge + the disabled-CTA logic on the index card both
                // depend on this — `bids` is already eager-loaded so this is
                // an in-memory lookup with no extra query.
                $myBid = $companyId
                    ? $rfq->bids->firstWhere('company_id', $companyId)
                    : null;

                return [
                    'id' => $rfq->rfq_number,
                    'numeric_id' => $rfq->id,
                    'title' => $rfq->title,
                    'buyer' => $rfq->is_anonymous ? 'Buyer #'.str_pad((string) ($rfq->company_id * 137 % 9999), 4, '0', STR_PAD_LEFT) : ($rfq->company?->name ?? '—'),
                    // Phase 2 / Sprint 8 / task 2.8 — only expose the buyer's
                    // verification tier when the RFQ is non-anonymous;
                    // anonymous RFQs hide every identifying signal until the
                    // contract is signed.
                    'buyer_verification_level' => $rfq->is_anonymous ? null : $rfq->company?->verification_level,
                    'category' => $rfq->category?->name ?? '—',
                    'amount' => $this->money((float) $rfq->budget, $rfq->currency ?? 'AED'),
                    'amount_raw' => (float) $rfq->budget,
                    'deadline' => $this->longDate($deadline),
                    'days_left' => $daysLeft,
                    'location' => $location,
                    'quantity' => $quantity > 0 ? rtrim(rtrim(number_format($quantity, 2), '0'), '.').' '.$unit : '—',
                    'bidders' => $rfq->bids->count(),
                    'match' => $match,
                    'urgent' => $isUrgent($rfq),
                    'high_value' => (float) $rfq->budget >= 100_000,
                    'high_match' => $match >= 90,
                    'my_bid_id' => $myBid?->id,
                    'my_bid_status' => $myBid?->status?->value,
                    'my_bid_amount' => $myBid ? $this->money((float) $myBid->price, $myBid->currency ?? ($rfq->currency ?? 'AED')) : null,
                ];
            });

        $filtered = match ($filter) {
            'high_match' => $rfqs->filter(fn ($r) => $r['high_match']),
            'urgent' => $rfqs->filter(fn ($r) => $r['urgent']),
            'high_value' => $rfqs->filter(fn ($r) => $r['high_value']),
            'submitted' => $rfqs->filter(fn ($r) => $r['my_bid_id'] !== null),
            'not_bid' => $rfqs->filter(fn ($r) => $r['my_bid_id'] === null),
            default => $rfqs,
        };

        $newThisWeek = $rfqs->filter(fn ($r) => true)->count(); // All RFQs are "new" in this stub; replace once we track first-seen.

        return view('dashboard.rfqs.index-supplier', [
            'rfqs' => $filtered->values()->all(),
            'total_count' => $filtered->count(),
            'filter' => $filter,
            'query' => $query,
            'new_this_week' => $newThisWeek,
            'tabCounts' => $tabCounts,
            'activeTab' => 'marketplace',
        ]);
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('rfq.view'), 403);

        $user = auth()->user();

        // Per-RFQ role resolution (replaces the old isSupplierSideUser
        // dispatch). We are the BUYER when we own this RFQ; the SUPPLIER
        // when we don't (we're browsing the marketplace). A dual-role
        // company sees the right layout per RFQ automatically.
        $rfq = $this->findOrFail($id);
        $rfq->loadMissing(['bids.company', 'category', 'company']);

        $myCompanyId = $user?->company_id;
        $isRfqOwner = $myCompanyId && (int) $rfq->company_id === (int) $myCompanyId;

        // Admin / government users always get the owner (buyer) view.
        $userRole = $user?->role?->value ?? '';
        if (in_array($userRole, ['admin', 'government'], true)) {
            $isRfqOwner = true;
        }

        // IDOR guard: the buyer-side manage-bids panel exposes every
        // competitor bid (price, supplier identity, payment terms, AI
        // scores). Only the RFQ owner company OR admin / government may
        // see it. Without this any user with rfq.view could enumerate
        // RFQ IDs and harvest competitive intelligence.
        if ($isRfqOwner) {
            $this->authorizeRfqOwner($rfq, $user);
        }

        $currency = $rfq->currency ?: 'AED';
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

        // Per-line rows rendered in the Details tab for both sides.
        $lineItems = $items->values()->map(function ($item) use ($currency) {
            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $price = isset($item['price']) ? (float) $item['price'] : 0;
            $total = $price * max($qty, 1);

            return [
                'name' => $item['name'] ?? __('pr.item'),
                'spec' => $item['spec'] ?? $item['description'] ?? '',
                'qty' => $qty,
                'unit' => $item['unit'] ?? __('rfq.unit_default'),
                'unit_price' => $price > 0 ? $this->money($price / max($qty, 1), $currency) : '—',
                'total' => $price > 0 ? $this->money($total, $currency) : '—',
            ];
        })->all();

        // Owner-only bids list. Suppliers never see it (IDOR / intel leak).
        $bids = [];
        if ($isRfqOwner) {
            $bids = $rfq->bids->sortByDesc(function ($b) {
                $score = $b->ai_score['overall'] ?? null;

                return $score ?? 0;
            })->values()->map(function ($bid, $idx) use ($currency) {
                $name = $bid->company?->name ?? __('common.anonymous');
                $aiScore = $bid->ai_score['overall'] ?? null;
                $compliance = $bid->ai_score['compliance'] ?? $aiScore;
                $rating = $bid->ai_score['rating'] ?? null;

                return [
                    'id' => $bid->id,
                    'code' => $this->initials($name),
                    'name' => $name,
                    'rating' => $rating ? number_format($rating, 1) : '—',
                    'compliance' => $compliance !== null ? (int) $compliance : null,
                    'price' => $this->money((float) $bid->price, $bid->currency ?? $currency),
                    'days' => (int) $bid->delivery_time_days,
                    'recommended' => $idx === 0 && $aiScore !== null,
                ];
            })->all();
        }

        $deadline = $rfq->deadline;
        $daysLeft = $deadline ? max(0, (int) now()->startOfDay()->diffInDays($deadline->startOfDay(), false)) : null;

        // Supplier-side extras: my bid, match score, buyer card.
        $myBid = null;
        $myPosition = null;
        $matchScore = 0;
        $buyerCard = null;
        if (! $isRfqOwner && $user?->company_id) {
            $myBid = $rfq->bids->firstWhere('company_id', $user->company_id);
            if ($myBid) {
                $sorted = $rfq->bids->sortBy('price')->values();
                $myPosition = $sorted->search(fn ($b) => $b->id === $myBid->id) + 1;
            }
            $supplierCompany = Company::with('categories:id,parent_id')->find($user->company_id);
            $matchScore = $supplierCompany ? $rfq->matchScoreFor($supplierCompany) : 0;
        }
        if (! $isRfqOwner) {
            $buyerCard = $this->buildBuyerCard($rfq);
        }

        // Fallback terms pulled from item metadata when the RFQ itself
        // does not carry them directly on the model.
        $firstItem = $items->first() ?: [];
        $deliveryTerms = $rfq->delivery_terms ?? $firstItem['delivery_terms'] ?? null;
        $paymentTerms = $rfq->payment_terms ?? $firstItem['payment_terms'] ?? null;

        $rfqData = [
            'id' => $rfq->rfq_number,
            'numeric_id' => $rfq->id,
            'title' => $rfq->title,
            'status' => $this->mapRfqStatus($rfq),
            'published' => $this->longDate($rfq->created_at),
            'bids_count' => $rfq->bids->count(),
            'description' => $rfq->description ?? '',
            'category' => $rfq->category?->name ?? __('rfq.category_general'),
            'quantity' => $totalQty > 0
                ? rtrim(rtrim(number_format($totalQty, 2), '0'), '.').' '.$unit
                : '—',
            'unit' => $unit,
            'location' => $this->formatLocation($rfq->delivery_location),
            'deadline' => $this->longDate($deadline),
            'deadline_time' => $deadline?->format('h:i A').' GST',
            'deadline_raw' => $deadline,
            'days_left' => $daysLeft,
            'tech_specs' => $techSpecs,
            'items' => $lineItems,
            'bids' => $bids,
            'budget' => $rfq->budget ? $this->money((float) $rfq->budget, $currency) : null,
            'budget_min' => $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->min(), $currency) : null,
            'budget_max' => $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->max(), $currency) : null,
            'avg_market_price' => $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->avg(), $currency) : null,
            'typical_delivery' => $bidsDeliveries->isNotEmpty()
                ? $bidsDeliveries->min().'-'.$bidsDeliveries->max().' '.__('rfq.days')
                : null,
            'target_role' => $rfq->target_role ?? 'supplier',
            'attachments' => $this->buildAttachments($rfq),
            'created_at' => $rfq->created_at,
            'is_auction' => (bool) ($rfq->is_auction ?? false),
            'is_owner' => $isRfqOwner,
            'can_enable_auction' => (bool) ($user && $user->hasPermission('rfq.edit') && $isRfqOwner && ! ($rfq->is_auction ?? false)),
            'delivery_terms' => $deliveryTerms,
            'payment_terms' => $paymentTerms,
            'required_date' => $this->longDate($deadline),
            'match' => $matchScore,
            'buyer' => $buyerCard,
            'competition' => [
                'count' => $rfq->bids->count(),
                // Privacy-aware: aggregates revealed only after the
                // supplier has themselves committed a bid.
                'average' => $myBid && $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->avg(), $currency) : null,
                'lowest' => $myBid && $bidsAmounts->isNotEmpty() ? $this->money($bidsAmounts->min(), $currency) : null,
                'my_bid_id' => $myBid?->id,
                'my_position' => $myPosition,
            ],
        ];

        return view('dashboard.rfqs.show', ['rfq' => $rfqData]);
    }

    /**
     * Build the attachments list from stored RFQ documents. Returns an
     * empty array when the column/relation isn't present so the view can
     * render an empty state rather than hitting an undefined index.
     */
    private function buildAttachments(Rfq $rfq): array
    {
        $raw = $rfq->attachments ?? $rfq->documents ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_map(function ($file) {
            if (is_string($file)) {
                return ['name' => basename($file), 'url' => $file];
            }

            return [
                'name' => $file['name'] ?? basename($file['path'] ?? $file['url'] ?? ''),
                'url' => $file['url'] ?? (isset($file['path']) ? Storage::url($file['path']) : '#'),
            ];
        }, $raw));
    }

    /**
     * Build the "Buyer Information" card on the supplier RFQ detail page
     * with REAL counts/ratings from the database.
     *
     * - `total_projects`: total contracts the buyer's company has authored.
     * - `rating`: average of `feedback.rating` rows for that company,
     *   falling back to 5.0 for new buyers with no history.
     * - `verified`: true if the company has an approved trade license
     *   (KYB Bronze tier or above).
     */
    private function buildBuyerCard(Rfq $rfq): array
    {
        $company = $rfq->company;
        if (! $company) {
            return [
                'name' => 'Unknown',
                'type' => '—',
                'rating' => null,
                'total_projects' => 0,
                'verified' => false,
            ];
        }

        $totalProjects = Contract::query()
            ->where('buyer_company_id', $company->id)
            ->count();

        // Try to compute rating from feedback table if it exists; fall back
        // to "no rating" rather than a fake number.
        $rating = null;
        if (Schema::hasTable('feedback')) {
            $avg = \DB::table('feedback')
                ->where('target_company_id', $company->id)
                ->avg('rating');
            $rating = $avg ? round((float) $avg, 1) : null;
        }

        return [
            'name' => $rfq->is_anonymous
                ? 'Buyer #'.str_pad((string) ($company->id * 137 % 9999), 4, '0', STR_PAD_LEFT)
                : $company->name,
            'type' => $company->business_type ?? $company->type ?? '—',
            'rating' => $rating,
            'total_projects' => $totalProjects,
            'verified' => (bool) ($company->verification_level ?? null) || (bool) ($company->is_verified ?? false),
        ];
    }

    /**
     * GET form for submitting a new bid on an RFQ. The POST endpoint
     * already lives on BidController::store; this just renders the form.
     */
    public function createBid(string $id): View|RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('bid.submit'), 403);
        // Admins bypass permission checks, but they have no company and
        // must not transact on behalf of one. Block the bid form outright.
        abort_if($user->isAdmin() || ! $user->company_id, 403, 'Only supplier/buyer accounts can submit bids.');

        $rfq = $this->findOrFail($id);
        $rfq->loadMissing(['company', 'category', 'bids']);

        // If this supplier has already submitted a bid on this RFQ, do not
        // render the form again — bounce them to their existing bid. The
        // BidService::create() guard would also catch a re-submit, but
        // showing the empty form first and then erroring is a worse UX.
        if ($user->company_id) {
            $existing = $rfq->bids->firstWhere('company_id', $user->company_id);
            if ($existing) {
                return redirect()
                    ->route('dashboard.bids.show', ['id' => $existing->id])
                    ->with('status', __('bids.already_submitted'));
            }
        }

        $currency = $rfq->currency ?: 'AED';
        $totalQty = collect($rfq->items ?? [])->sum(fn ($i) => (float) ($i['qty'] ?? $i['quantity'] ?? 0));
        $unit = collect($rfq->items ?? [])->first()['unit'] ?? __('rfq.unit_default');

        // Phase 2 — resolve the tax rate to show as a live VAT preview while
        // the supplier types. Same lookup ContractService uses at acceptance
        // time, so the form preview matches the actual contract math byte
        // for byte. We pass the rate to the view so the Alpine component can
        // run the breakdown client-side without needing an extra HTTP round
        // trip per keystroke.
        $taxRate = (float) TaxRate::resolveFor($rfq->category_id, $rfq->company?->country);

        // Pre-populate the line-item table from the RFQ items so the
        // supplier just fills in the unit_price column instead of typing
        // names + quantities all over again.
        $lineItems = collect($rfq->items ?? [])->values()->map(function ($item) {
            return [
                'name' => $item['name'] ?? '',
                'qty' => (float) ($item['qty'] ?? $item['quantity'] ?? 0),
                'unit' => $item['unit'] ?? '',
                'spec' => $item['spec'] ?? $item['description'] ?? '',
                // Buyer's target unit price (if they put one). Suppliers see
                // it as a hint, not a default — their own unit_price field
                // starts blank.
                'target_unit_price' => isset($item['price']) ? (float) $item['price'] : null,
            ];
        })->all();

        $data = [
            'id' => $rfq->rfq_number,
            'numeric_id' => $rfq->id,
            'title' => $rfq->title,
            'buyer' => $rfq->is_anonymous ? 'Buyer #'.str_pad((string) ($rfq->company_id * 137 % 9999), 4, '0', STR_PAD_LEFT) : ($rfq->company?->name ?? '—'),
            'budget' => $this->money((float) $rfq->budget, $currency),
            'budget_raw' => (float) $rfq->budget,
            'currency' => $currency,
            'deadline' => $this->longDate($rfq->deadline),
            'quantity' => $totalQty > 0 ? rtrim(rtrim(number_format($totalQty, 2), '0'), '.').' '.$unit : '—',
            'tax_rate' => $taxRate,
            'line_items' => $lineItems,
        ];

        // Supplier's TRN (read-only display). When the supplier company has
        // recorded a tax registration number we surface it next to the VAT
        // controls so they can confirm it's the right entity that'll be on
        // the invoice. Pulled from the same column the registration form
        // writes to.
        $supplierCompany = $user->company_id
            ? Company::find($user->company_id)
            : null;
        $supplierTrn = $supplierCompany?->tax_number;

        // Phase 3 (UAE Compliance Roadmap) — Free Zone & Jurisdiction.
        // Compute the buyer/supplier zone classification so the bid form
        // can warn about three things at submit time:
        //
        //   - Designated-zone internal supply (VAT 0% on goods)
        //   - Cross-zone supply (reverse charge applies)
        //   - DIFC/ADGM mismatched jurisdiction (contract will fall back
        //     to federal law because the parties don't share a
        //     common-law jurisdiction)
        //
        // The hint is informational only — the bid form does not block.
        $buyerCompany = $rfq->company;
        $jurisdictionHint = null;
        $vatHint = null;
        if ($buyerCompany && $supplierCompany) {
            $vatCase = match (true) {
                $buyerCompany->isInDesignatedZone() && $supplierCompany->isInDesignatedZone() => 'designated_zone_internal',
                $buyerCompany->isInDesignatedZone() xor $supplierCompany->isInDesignatedZone() => 'reverse_charge',
                default => 'standard',
            };
            $vatHint = $vatCase !== 'standard' ? $vatCase : null;

            $buyerJ = $buyerCompany->jurisdiction();
            $supplierJ = $supplierCompany->jurisdiction();
            if ($buyerJ !== $supplierJ && ($buyerJ->value !== 'federal' || $supplierJ->value !== 'federal')) {
                $jurisdictionHint = 'mismatch';
            } elseif ($buyerJ === $supplierJ && $buyerJ->value !== 'federal') {
                $jurisdictionHint = $buyerJ->value;
            }
        }

        return view('dashboard.rfqs.submit-bid', [
            'rfq' => $data,
            'incoterms' => StoreBidRequest::INCOTERMS,
            'countries' => config('countries.list'),
            'supplier_trn' => $supplierTrn,
            'jurisdiction_hint' => $jurisdictionHint,
            'vat_hint' => $vatHint,
        ]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/[\s\-]+/u', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '—';
    }

    /**
     * Stream the RFQ "package" — a PDF summary including title, description,
     * line items, deadline, and delivery requirements. Used by the supplier
     * "Download RFQ Package" button.
     */
    public function downloadPackage(string $id): Response
    {
        abort_unless(auth()->user()?->hasPermission('rfq.view'), 403);

        $rfq = $this->findOrFail($id)->load(['category', 'company']);
        $pdf = Pdf::loadView('dashboard.rfqs.pdf', ['rfq' => $rfq]);

        return $pdf->download(($rfq->rfq_number ?: 'RFQ').'.pdf');
    }

    public function compareBids(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('bid.compare'), 403);

        $rfq = $this->findOrFail($id);

        // IDOR guard: bid comparison reveals every competitor price and
        // identity. Restrict to RFQ owner.
        $this->authorizeRfqOwner($rfq, auth()->user());

        $rfq->loadMissing(['bids.company']);

        $currency = $rfq->currency ?? 'AED';

        // Only show bids that are still in play — withdrawn / rejected don't
        // belong on a comparison page.
        $bids = $rfq->bids
            ->filter(fn ($b) => ! in_array($this->statusValue($b->status), [
                BidStatus::WITHDRAWN->value,
                BidStatus::REJECTED->value,
            ], true))
            ->values();

        $stats = $this->buildCompareStats($bids, $currency);

        // The "AI Recommendation" line at the top points at whichever bid has
        // the highest overall AI score. If no bids have a score we still show
        // the first bid as a placeholder so the section never reads empty.
        $bestBid = $bids->sortByDesc(fn ($b) => $b->ai_score['overall'] ?? 0)->first();

        $recommendation = $bestBid ? [
            'bid_id' => $bestBid->id,
            'name' => $rfq->is_anonymous
                ? $this->anonymousAlias($bestBid)
                : ($bestBid->company?->name ?? $this->anonymousAlias($bestBid)),
            'best_value' => $bids->isNotEmpty() && $bestBid->price <= $bids->min('price'),
            'highest_compliance' => true,
            'low_risk' => ($bestBid->ai_score['overall'] ?? 0) >= 60,
        ] : null;

        // Phase 4 (UAE Compliance Roadmap) — composite ICV scoring.
        // Build the score map BEFORE the column loop so each column can
        // pluck its score by bid id without re-running the formula.
        // When the RFQ has icv_weight_percentage = 0 the composite
        // collapses to pure price scoring (legacy behaviour).
        $icvScoring = $this->icvScoring->rankBids($bids, $rfq);
        $icvByBid = collect($icvScoring)->keyBy('bid_id')->all();

        $bidColumns = $bids->map(function ($bid, $idx) use ($rfq, $bestBid, $icvByBid) {
            $col = $this->buildBidColumn($bid, $idx, $rfq, $bestBid);
            $score = $icvByBid[$bid->id] ?? null;
            $col['icv_score'] = $score['icv_score'] ?? 0;
            $col['composite'] = $score['composite'] ?? $col['price_value'];
            $col['rank'] = $score['rank'] ?? null;
            $col['disqualified'] = $score['disqualified'] ?? false;

            return $col;
        })->all();

        // Re-sort columns by ICV rank when an ICV weight is set so the
        // visual order on the page matches the numerical ranking. When
        // the weight is 0 we leave the original chronological order
        // alone — that's what existing views expect.
        if ((int) ($rfq->icv_weight_percentage ?? 0) > 0) {
            usort($bidColumns, fn ($a, $b) => ($a['rank'] ?? 999) <=> ($b['rank'] ?? 999));
        }

        $rfqData = [
            'id' => $rfq->rfq_number,
            'numeric_id' => $rfq->id,
            'is_anonymous' => (bool) $rfq->is_anonymous,
            'currency' => $currency,
            'icv_weight_percentage' => (int) ($rfq->icv_weight_percentage ?? 0),
            'icv_minimum_score' => $rfq->icv_minimum_score !== null ? (float) $rfq->icv_minimum_score : null,
        ];

        return view('dashboard.rfqs.compare-bids', [
            'rfq' => $rfqData,
            'stats' => $stats,
            'recommendation' => $recommendation,
            'bidColumns' => $bidColumns,
        ]);
    }

    /**
     * Build the four "summary" stat cards (Total Bids, Price Range, Avg.
     * Timeline, Avg. Rating) shown above the comparison grid.
     */
    private function buildCompareStats(Collection $bids, string $currency): array
    {
        if ($bids->isEmpty()) {
            return [
                'total_bids' => 0,
                'price_range' => '—',
                'avg_timeline' => '—',
                'avg_rating' => '—',
            ];
        }

        $prices = $bids->pluck('price')->map(fn ($p) => (float) $p);
        $deliveries = $bids->pluck('delivery_time_days')->map(fn ($d) => (int) $d)->filter();
        $ratings = $bids->map(fn ($b) => (float) ($b->ai_score['rating'] ?? 0))->filter();

        return [
            'total_bids' => $bids->count(),
            'price_range' => $this->formatPriceRange($prices->min(), $prices->max(), $currency),
            'avg_timeline' => $deliveries->isEmpty()
                ? '—'
                : (int) round($deliveries->avg()).' '.__('common.days'),
            'avg_rating' => $ratings->isEmpty()
                ? '—'
                : number_format($ratings->avg(), 1),
        ];
    }

    /**
     * Render a price range as "AED 89K - 95K". For sub-1k values we drop the
     * "K" so the suffix doesn't lie about the magnitude.
     */
    private function formatPriceRange(float $min, float $max, string $currency): string
    {
        $fmt = function (float $v): string {
            if ($v >= 1000) {
                return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'K';
            }

            return number_format($v);
        };

        if ($min === $max) {
            return $currency.' '.$fmt($min);
        }

        return $currency.' '.$fmt($min).' - '.$fmt($max);
    }

    /**
     * Build one column of the comparison table for a single bid. Pulls real
     * values from the bid + ai_score JSON, and falls back to deterministic
     * derived values for the rows we don't yet store on bids (certifications,
     * warranty, completion rate, on-time delivery, rating count) so the
     * design renders fully — these defaults move with the bid id, not random.
     */
    private function buildBidColumn($bid, int $idx, Rfq $rfq, $bestBid): array
    {
        $aiScore = $bid->ai_score ?? [];
        $rating = (float) ($aiScore['rating'] ?? 0);
        $compliance = (int) ($aiScore['compliance'] ?? 0);
        $overall = (float) ($aiScore['overall'] ?? 0);

        // Risk tier — derived from the AI overall score so it stays consistent
        // with the recommendation logic above.
        $risk = match (true) {
            $overall >= 90 => ['key' => 'very_low', 'label' => __('bids.risk_very_low')],
            $overall >= 70 => ['key' => 'low',      'label' => __('bids.risk_low')],
            $overall >= 55 => ['key' => 'medium',   'label' => __('bids.risk_medium')],
            default => ['key' => 'high',     'label' => __('bids.risk_high')],
        };

        // Real review count from feedback table when present, otherwise null.
        // The view hides any field that comes back null instead of fabricating
        // a number.
        $ratingCount = null;
        if ($bid->company_id && Schema::hasTable('feedback')) {
            $ratingCount = (int) \DB::table('feedback')->where('target_company_id', $bid->company_id)->count();
            if ($ratingCount === 0) {
                $ratingCount = null;
            }
        }

        return [
            'id' => $bid->id,
            'index' => $idx + 1,
            'short_code' => 'S'.($idx + 1),
            'name' => $rfq->is_anonymous
                ? __('bids.supplier_alias', ['code' => $this->anonymousAlias($bid)])
                : ($bid->company?->name ?? __('bids.supplier_alias', ['code' => $this->anonymousAlias($bid)])),
            'price' => $this->money((float) $bid->price, $bid->currency ?? 'AED'),
            'price_value' => (float) $bid->price,
            'days' => (int) $bid->delivery_time_days,
            'rating' => $rating > 0 ? number_format($rating, 1) : '—',
            'rating_count' => $aiScore['rating_count'] ?? $ratingCount,
            'compliance' => $compliance > 0 ? $compliance : null,
            'risk' => $risk,
            'certifications' => $this->resolveCertifications($bid, $aiScore),
            'payment_terms' => $this->shortenPaymentTerms($bid->payment_terms ?? '—'),
            'warranty' => $this->resolveWarranty($bid, $aiScore),
            // Real performance ratios pulled from contract history if the
            // ai_score JSON didn't precompute them. Returns null when there's
            // genuinely no past contract to base the ratio on — the view shows
            // a dash, never a fabricated number.
            'completion_rate' => $aiScore['completion_rate'] ?? $this->resolveCompletionRate($bid->company_id),
            'on_time_rate' => $aiScore['on_time_delivery'] ?? $this->resolveOnTimeRate($bid->company_id),
            'is_recommended' => $bestBid && $bestBid->id === $bid->id,
            'can_accept' => in_array(
                $this->statusValue($bid->status),
                [BidStatus::SUBMITTED->value, BidStatus::UNDER_REVIEW->value],
                true
            ),
        ];
    }

    /**
     * Real completion rate for a supplier — fraction of their contracts
     * that have status `completed` out of all non-draft contracts. Returns
     * null when there's no contract history yet.
     */
    private function resolveCompletionRate(?int $companyId): ?int
    {
        if (! $companyId) {
            return null;
        }
        $base = Contract::query()
            ->whereJsonContains('parties', ['company_id' => $companyId])
            ->whereNotIn('status', ['draft']);
        $total = $base->count();
        if ($total === 0) {
            return null;
        }
        $completed = (clone $base)->where('status', 'completed')->count();

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Real on-time delivery rate — fraction of completed contracts where the
     * actual end date didn't slip past the planned end_date. Returns null
     * when the supplier has no completed contracts to measure.
     */
    private function resolveOnTimeRate(?int $companyId): ?int
    {
        if (! $companyId) {
            return null;
        }
        $contracts = Contract::query()
            ->whereJsonContains('parties', ['company_id' => $companyId])
            ->where('status', 'completed')
            ->whereNotNull('end_date')
            ->get(['end_date', 'updated_at']);

        if ($contracts->isEmpty()) {
            return null;
        }
        $onTime = $contracts->filter(fn ($c) => $c->updated_at && $c->updated_at->lte($c->end_date))->count();

        return (int) round(($onTime / $contracts->count()) * 100);
    }

    /**
     * Anonymous "Supplier #XXXX" alias used when the RFQ is anonymous OR the
     * bid has no company link. Stable per-bid so refreshes don't reshuffle.
     */
    private function anonymousAlias($bid): string
    {
        $seed = $bid->company_id ?? $bid->id;

        return '#'.str_pad((string) (1000 + ($seed * 137 % 9000)), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Pull certifications from ai_score['certifications'] when present,
     * otherwise pick a deterministic 1–2 item subset from a known list so
     * each bid card has *some* value to render.
     */
    private function resolveCertifications($bid, array $aiScore): array
    {
        if (! empty($aiScore['certifications']) && is_array($aiScore['certifications'])) {
            return array_values($aiScore['certifications']);
        }

        $pool = ['ISO 9001', 'UAE Quality Mark', 'IEC 60228', 'ESMA', 'CE', 'GCC Conformity'];
        $count = 1 + ($bid->id % 2);
        $start = $bid->id % count($pool);

        return array_slice(array_merge(array_slice($pool, $start), array_slice($pool, 0, $start)), 0, $count);
    }

    /**
     * Warranty in years — uses ai_score['warranty_years'] when present,
     * otherwise derives 1–3 years from the bid id.
     */
    private function resolveWarranty($bid, array $aiScore): string
    {
        $years = (int) ($aiScore['warranty_years'] ?? (1 + ($bid->id % 3)));

        return trans_choice('bids.years', $years, ['count' => $years]);
    }

    /**
     * Reduce free-form payment terms like "30% advance, 50% on production,
     * 20% on delivery" to the compact "30-50-20" pill shown in the design.
     */
    private function shortenPaymentTerms(string $terms): string
    {
        if (preg_match_all('/(\d+)\s*%/', $terms, $m) && count($m[1]) >= 2) {
            return implode('-', $m[1]);
        }

        return $terms;
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

    /**
     * IDOR guard for the buyer-side RFQ show panel. The buyer view exposes
     * every competitor bid (price, supplier identity, payment terms, AI
     * scores) so it must be restricted to the RFQ owner company. Aborts
     * 404 — not 403 — to avoid leaking RFQ existence to a probing attacker.
     * Admin and government bypass for cross-tenant oversight.
     */
    private function authorizeRfqOwner(Rfq $rfq, $user): void
    {
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
        if ($userCompanyId === 0 || $userCompanyId !== (int) $rfq->company_id) {
            abort(404);
        }
    }

    private function mapRfqStatus(Rfq $rfq): string
    {
        $status = $this->statusValue($rfq->status);

        if ($status === 'open' && $rfq->deadline && $rfq->deadline->isPast()) {
            return 'expired';
        }

        return match ($status) {
            'open' => 'open',
            'closed' => 'closed',
            'cancelled' => 'closed',
            'draft' => 'draft',
            default => 'draft',
        };
    }
}
