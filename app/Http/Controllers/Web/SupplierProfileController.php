<?php

namespace App\Http\Controllers\Web;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\VerificationLevel;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Category;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public-ish supplier profile page. Shown to:
 *   - Buyers evaluating a bid (linked from the bid show page).
 *   - Other suppliers researching competitors (rare but allowed).
 *
 * Reveals: company basics, average rating, total reviews, completed contracts,
 * certifications list, and a paginated review feed. Privacy: anonymous bid
 * sources are NOT exposed here — the page lives at /companies/{id} and uses
 * the company's real name.
 */
class SupplierProfileController extends Controller
{
    use FormatsForViews;

    /**
     * Phase 1 / task 1.14 — public "Browse Suppliers" landing page.
     *
     * Same data shape as the authenticated directory but with a few
     * intentional limits:
     *   - No match score (we don't have a viewer to score against).
     *   - Only Bronze+ suppliers (Unverified profiles aren't surfaced
     *     to anonymous visitors so the public face stays trustworthy).
     *   - Hard cap at 24 cards per page — this is a marketing surface,
     *     not a working tool.
     *
     * The page is reachable by anyone (no auth middleware) and serves
     * dual purposes: SEO indexing and lead generation. Each card links
     * to the regular profile page which itself requires login.
     */
    public function publicDirectory(Request $request): View
    {
        $q             = trim((string) $request->query('q', ''));
        $categoryId    = (int) $request->query('category', 0) ?: null;
        $country       = trim((string) $request->query('country', ''));
        // Phase 4 (UAE Compliance Roadmap) — minimum ICV filter. Lets
        // government-adjacent buyers narrow the directory to suppliers
        // with a usable in-country value score above a chosen threshold.
        $icvMin        = (int) $request->query('icv_min', 0);

        $base = Company::query()
            ->whereIn('type', [CompanyType::SUPPLIER->value, CompanyType::SERVICE_PROVIDER->value])
            ->where('status', CompanyStatus::ACTIVE->value)
            // Public view shows Bronze+ only — Unverified profiles stay
            // private until they prove they're real.
            ->whereIn('verification_level', [
                VerificationLevel::BRONZE->value,
                VerificationLevel::SILVER->value,
                VerificationLevel::GOLD->value,
                VerificationLevel::PLATINUM->value,
            ]);

        if ($q !== '') {
            $base->search($q, ['name', 'name_ar', 'description']);
        }
        if ($categoryId) {
            $base->whereHas('categories', fn ($c) => $c->where('categories.id', $categoryId));
        }
        if ($country !== '') {
            $base->where('country', $country);
        }

        // Phase 4 — only return companies that have at least one verified
        // non-expired ICV certificate at or above the chosen threshold.
        // We use a whereHas instead of joining + grouping so the limit
        // and ordering on the parent query stay simple.
        if ($icvMin > 0) {
            $base->whereHas('icvCertificates', function ($iq) use ($icvMin) {
                $iq->where('status', \App\Models\IcvCertificate::STATUS_VERIFIED)
                   ->where('expires_date', '>=', now()->toDateString())
                   ->where('score', '>=', $icvMin);
            });
        }

        $companies = $base->with([
                'categories:id,name',
                // Eager-load the active ICV certificates so the blade can
                // pick the highest score per company without N+1.
                'icvCertificates' => function ($q) {
                    $q->where('status', \App\Models\IcvCertificate::STATUS_VERIFIED)
                      ->where('expires_date', '>=', now()->toDateString())
                      ->orderByDesc('score');
                },
            ])
            ->orderBy('verification_level', 'desc')
            ->orderBy('name')
            ->limit(24)
            ->get();

        $categories = Category::orderBy('name')->limit(50)->get(['id', 'name']);
        $countries  = Company::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return view('public.suppliers', [
            'companies'  => $companies,
            'total'      => $companies->count(),
            'categories' => $categories,
            'countries'  => $countries,
            'filters'    => [
                'q'        => $q,
                'category' => $categoryId,
                'country'  => $country,
                'icv_min'  => $icvMin,
            ],
        ]);
    }

    /**
     * Phase 1 / task 1.10 — hierarchical category browser.
     *
     * Walks the categories tree by `parent_id`, optionally rooted at a
     * given segment. Each row gets a per-category "supplier count" so
     * the buyer sees, at a glance, where the platform is dense vs sparse.
     * Sourced from the company_category pivot in a single GROUP BY
     * query (no per-category lookup).
     */
    public function categoryBrowser(Request $request): View
    {
        abort_unless(auth()->check(), 403);

        $rootId = (int) $request->query('root', 0) ?: null;

        $rootCategory = $rootId ? Category::find($rootId) : null;

        // Eager-load 3 levels of children so the browser renders a true
        // tree without N+1 queries. UNSPSC has 4 levels (segment → family
        // → class → commodity); 3 nested loads cover everything we'll
        // surface in the browser since the top entry IS the root level.
        $children = Category::query()
            ->where('parent_id', $rootId)
            ->where('is_active', true)
            ->orderBy('name')
            ->withCount('companies')
            ->get();

        // Build a quick path/breadcrumb for the header — walks up from
        // the current root to the top so the user can climb back.
        $breadcrumbs = [];
        $cursor = $rootCategory;
        while ($cursor) {
            array_unshift($breadcrumbs, $cursor);
            $cursor = $cursor->parent;
        }

        return view('dashboard.categories.browse', [
            'root'        => $rootCategory,
            'breadcrumbs' => $breadcrumbs,
            'children'    => $children,
        ]);
    }

    /**
     * Phase 1 / task 1.1 — public(-ish) supplier directory page. Buyers
     * land here to discover suppliers across the platform with filters
     * for category, country, verification tier, certifications, and
     * minimum rating. Each card carries a Phase-0 match score (computed
     * relative to the viewer's company so the order is meaningful).
     */
    public function directory(Request $request): View
    {
        abort_unless(auth()->check(), 403);

        // Filter inputs (all optional). Validation is light-touch — invalid
        // values just degrade to the unfiltered list.
        $q             = trim((string) $request->query('q', ''));
        $categoryId    = (int) $request->query('category', 0) ?: null;
        $country       = trim((string) $request->query('country', ''));
        $verification  = trim((string) $request->query('verification', ''));
        $minRating     = (float) $request->query('rating', 0);
        $hasCerts      = $request->boolean('has_certs');

        $base = Company::query()
            ->whereIn('type', [CompanyType::SUPPLIER->value, CompanyType::SERVICE_PROVIDER->value])
            ->where('status', CompanyStatus::ACTIVE->value);

        if ($q !== '') {
            $base->search($q, ['name', 'name_ar', 'description']);
        }
        if ($categoryId) {
            $base->whereHas('categories', fn ($c) => $c->where('categories.id', $categoryId));
        }
        if ($country !== '') {
            $base->where('country', $country);
        }
        if ($verification !== '' && in_array($verification, array_map(fn ($v) => $v->value, VerificationLevel::cases()), true)) {
            $base->where('verification_level', $verification);
        }
        if ($hasCerts) {
            // JSON column — "has any cert" means "non-empty array"
            $base->whereNotNull('certifications')
                 ->whereJsonLength('certifications', '>', 0);
        }

        // Eager-load categories for the match score and to render the
        // category chips on each card without N+1 queries.
        $companies = $base->with('categories:id,parent_id,name')
            ->orderBy('verification_level', 'desc')
            ->orderBy('name')
            ->limit(60)
            ->get();

        // Aggregate ratings per company in one query so we can apply the
        // min-rating filter and surface star counts on each card.
        $ratings = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('feedback') && $companies->isNotEmpty()) {
            $ratings = \DB::table('feedback')
                ->whereIn('target_company_id', $companies->pluck('id'))
                ->groupBy('target_company_id')
                ->selectRaw('target_company_id, AVG(rating) as avg, COUNT(*) as cnt')
                ->get()
                ->mapWithKeys(fn ($r) => [(int) $r->target_company_id => [
                    'rating' => round((float) $r->avg, 1),
                    'count'  => (int) $r->cnt,
                ]])
                ->all();
        }

        // The viewer's company is used as the "from" side for the match
        // score so the cards are sorted by relevance to who's browsing.
        $viewer = auth()->user();
        $viewerCompany = $viewer?->company_id
            ? Company::with('categories:id,parent_id')->find($viewer->company_id)
            : null;

        $cards = $companies
            ->map(function (Company $supplier) use ($ratings, $viewerCompany) {
                $rating = $ratings[$supplier->id] ?? null;
                $score  = null;
                if ($viewerCompany) {
                    // Reuse the same matchScoreFor logic from Phase 0 but
                    // applied at company-level instead of per RFQ. We
                    // approximate by averaging the deterministic factors
                    // (category overlap, country, verification, certs).
                    $score = $this->companyMatchScore($supplier, $viewerCompany);
                }

                return [
                    'id'            => $supplier->id,
                    'name'          => $supplier->name,
                    'name_ar'       => $supplier->name_ar,
                    'country'       => $supplier->country,
                    'description'   => $supplier->description,
                    'verification'  => $supplier->verification_level?->value,
                    'verification_label' => $supplier->verification_level?->label(),
                    'categories'    => $supplier->categories->take(3)->pluck('name')->all(),
                    'category_count' => $supplier->categories->count(),
                    'rating'        => $rating['rating'] ?? null,
                    'review_count'  => $rating['count']  ?? 0,
                    'has_certs'     => is_array($supplier->certifications) && count($supplier->certifications) > 0,
                    'match_score'   => $score,
                ];
            })
            ->when($minRating > 0, fn ($c) => $c->filter(fn ($row) => ($row['rating'] ?? 0) >= $minRating))
            ->sortByDesc('match_score')
            ->values()
            ->all();

        // Filter dropdown contents.
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $countries  = Company::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return view('dashboard.suppliers.directory', [
            'cards'         => $cards,
            'total'         => count($cards),
            'categories'    => $categories,
            'countries'     => $countries,
            'verifications' => VerificationLevel::cases(),
            'filters'       => [
                'q'            => $q,
                'category'     => $categoryId,
                'country'      => $country,
                'verification' => $verification,
                'rating'       => $minRating,
                'has_certs'    => $hasCerts,
            ],
        ]);
    }

    /**
     * Company-level match score: same factors as Rfq::matchScoreFor but
     * applied between two companies (the viewer and the supplier). Used
     * by the supplier directory to rank cards.
     */
    private function companyMatchScore(Company $supplier, Company $viewer): int
    {
        $score = 0;

        // Category overlap — 50 points (no specific RFQ category to match
        // against, so we credit any pivot intersection).
        $supplierCategoryIds = $supplier->relationLoaded('categories')
            ? $supplier->categories->pluck('id')->all()
            : $supplier->categories()->pluck('categories.id')->all();
        $viewerCategoryIds = $viewer->relationLoaded('categories')
            ? $viewer->categories->pluck('id')->all()
            : $viewer->categories()->pluck('categories.id')->all();
        $intersect = array_intersect($supplierCategoryIds, $viewerCategoryIds);
        if ($intersect !== []) {
            // 50 if at least one direct match, prorated by overlap size up to all of viewer's categories.
            $denom = max(1, count($viewerCategoryIds));
            $score += min(50, (int) round((count($intersect) / $denom) * 50));
        }

        // Country — 20.
        if ($supplier->country && $viewer->country
            && strcasecmp($supplier->country, $viewer->country) === 0) {
            $score += 20;
        }

        // Verification tier — up to 20 (more weight at company-level since
        // there's no per-RFQ history to credit).
        $rank = $supplier->verification_level?->rank() ?? 0;
        $score += (int) round(($rank / 4) * 20);

        // Certifications on file — 10.
        if (is_array($supplier->certifications) && count($supplier->certifications) > 0) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }

    public function show(int $id): View|\Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->check(), 403);

        $company = Company::with(['users', 'categories', 'bankDetails', 'beneficialOwners'])
            ->withCount(['purchaseRequests', 'rfqs', 'bids', 'buyerContracts', 'payments'])
            ->findOrFail($id);

        // If the viewer is looking at THEIR OWN company we redirect to
        // the manager-facing profile so they get the editable form
        // instead of the read-only public surface.
        $viewer = auth()->user();
        if ($viewer && $viewer->company_id === $company->id) {
            return redirect()->route('dashboard.company.profile');
        }

        // The unified profile blade does the rendering — same layout
        // the manager and the admin see, with the public mode hiding
        // bank details, beneficial owners, team list, payment counts
        // and any non-verified document. We additionally compute
        // reviews / ratings for the cross-company viewer because
        // they're a key trust signal when evaluating a partner.
        $data = CompanyProfileController::buildViewData($company, mode: 'public');

        // Aggregate ratings (avg + count) — the controller computes once and
        // hands the view a flat array so the template stays dumb.
        $rating = null;
        $reviewCount = 0;
        $breakdown = ['quality' => null, 'on_time' => null, 'communication' => null];

        if (\Illuminate\Support\Facades\Schema::hasTable('feedback')) {
            $row = \DB::table('feedback')
                ->where('target_company_id', $company->id)
                ->selectRaw('AVG(rating) as avg, COUNT(*) as cnt, AVG(quality_score) as q, AVG(on_time_score) as o, AVG(communication_score) as c')
                ->first();
            if ($row) {
                $rating       = $row->avg ? round((float) $row->avg, 1) : null;
                $reviewCount  = (int) ($row->cnt ?? 0);
                $breakdown    = [
                    'quality'       => $row->q ? round((float) $row->q, 1) : null,
                    'on_time'       => $row->o ? round((float) $row->o, 1) : null,
                    'communication' => $row->c ? round((float) $row->c, 1) : null,
                ];
            }
        }

        // Star distribution: how many of each rating value (1..5) the company
        // received. Drives the histogram strip in the profile header.
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        if (\Illuminate\Support\Facades\Schema::hasTable('feedback') && $reviewCount > 0) {
            $rows = \DB::table('feedback')
                ->where('target_company_id', $company->id)
                ->selectRaw('rating, COUNT(*) as cnt')
                ->groupBy('rating')
                ->get();
            foreach ($rows as $r) {
                $distribution[(int) $r->rating] = (int) $r->cnt;
            }
        }

        // Reviews feed — newest first, eager-load contract title + rater name.
        $reviews = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('feedback')) {
            $reviews = Feedback::with(['contract', 'raterCompany'])
                ->where('target_company_id', $company->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(function (Feedback $f) {
                    return [
                        'rating'        => $f->rating,
                        'comment'       => $f->comment,
                        'rater_company' => $f->raterCompany?->name ?? 'Anonymous',
                        'contract'      => $f->contract?->title ?? '—',
                        'when'          => $f->created_at?->diffForHumans() ?? '',
                    ];
                })
                ->all();
        }

        // Completed contract count where this company appears in the parties JSON.
        $completedContracts = Contract::query()
            ->where(function ($q) use ($company) {
                $q->whereJsonContains('parties', ['company_id' => $company->id])
                    ->orWhere('buyer_company_id', $company->id);
            })
            ->whereIn('status', ['completed', 'signed', 'active'])
            ->count();

        // Certifications: each entry can be a plain string or an object with
        // {name, issuer, expires_at}. Normalise so the view doesn't have to
        // care which shape was stored.
        $certifications = collect($company->certifications ?? [])->map(function ($c) {
            if (is_string($c)) {
                return ['name' => $c, 'issuer' => null, 'expires_at' => null];
            }
            return [
                'name'       => $c['name'] ?? '—',
                'issuer'     => $c['issuer'] ?? null,
                'expires_at' => $c['expires_at'] ?? null,
            ];
        })->all();

        // Render the unified Company Profile blade in PUBLIC mode and
        // merge the review/rating bundle on top — the public surface
        // shows the same identity / verified docs / branches the
        // manager and admin see, plus a reviews panel that's exclusive
        // to the cross-company viewing path.
        return view('dashboard.company.profile', array_merge($data, [
            'rating'              => $rating,
            'review_count'        => $reviewCount,
            'breakdown'           => $breakdown,
            'distribution'        => $distribution,
            'reviews'             => $reviews,
            'completed_contracts' => $completedContracts,
            'certifications'      => $certifications,
            'years_active'        => $company->created_at ? max(1, (int) $company->created_at->diffInYears(now())) : 0,
        ]));
    }
}
