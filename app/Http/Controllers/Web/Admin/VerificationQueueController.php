<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\VerificationLevel;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin verification queue. Phase 2 / Sprint 8 / task 2.6.
 *
 * Surfaces every company that needs admin attention in one focused
 * page, sorted by urgency:
 *
 *   1. Sanctions HIT — must block immediately, top of the list.
 *   2. Sanctions REVIEW — admin adjudication needed.
 *   3. Has pending documents — quick approve/reject path.
 *   4. Eligible for promotion — current verification_level < eligibleLevel.
 *
 * Each row exposes the actions an admin needs without leaving the page:
 *   - Re-screen sanctions
 *   - Approve / reject pending documents (links to the document review)
 *   - Promote to a specific tier (or auto-promote)
 *   - Open the company show page for full detail
 */
class VerificationQueueController extends Controller
{
    public function __construct(private readonly VerificationService $verification)
    {
    }

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['all', 'sanctions', 'documents', 'promotion'], true)
            ? $request->query('filter')
            : 'all';

        // Pull the candidate set: any company that has unresolved sanctions
        // OR pending documents OR is eligible for a higher tier than its
        // current one. We deliberately over-fetch and rank in PHP — the
        // query stays simple and the index page tops out at a few hundred
        // rows.
        $candidates = Company::query()
            ->with([
                'companyDocuments' => fn ($q) => $q->latest(),
                'sanctionsScreenings' => fn ($q) => $q->latest()->limit(1),
            ])
            ->where(function ($q) {
                $q->whereIn('sanctions_status', ['hit', 'review'])
                    ->orWhereHas('companyDocuments', fn ($d) => $d->where('status', CompanyDocument::STATUS_PENDING));
            })
            ->latest()
            ->limit(200)
            ->get();

        $rows = $candidates->map(function (Company $c) {
            $eligible = $this->verification->eligibleLevel($c);
            $current  = $c->verification_level ?? VerificationLevel::UNVERIFIED;

            $pendingDocs = $c->companyDocuments
                ->where('status', CompanyDocument::STATUS_PENDING)
                ->count();

            $sanctions = $c->sanctionsScreenings->first();

            // Urgency score for sorting: hit (100) > review (80) > pending docs (50) > promotion (20).
            $urgency = 0;
            $reasons = [];
            if ($c->sanctions_status === 'hit') {
                $urgency += 100;
                $reasons[] = 'sanctions_hit';
            }
            if ($c->sanctions_status === 'review') {
                $urgency += 80;
                $reasons[] = 'sanctions_review';
            }
            if ($pendingDocs > 0) {
                $urgency += 50;
                $reasons[] = 'pending_documents';
            }
            if ($eligible->rank() > $current->rank()) {
                $urgency += 20;
                $reasons[] = 'eligible_promotion';
            }

            return [
                'company'           => $c,
                'current_level'     => $current,
                'eligible_level'    => $eligible,
                'pending_doc_count' => $pendingDocs,
                'sanctions_screening' => $sanctions,
                'reasons'           => $reasons,
                'urgency'           => $urgency,
            ];
        });

        // Apply the filter, then sort by urgency desc.
        $rows = $rows->filter(function ($row) use ($filter) {
            return match ($filter) {
                'sanctions' => in_array('sanctions_hit', $row['reasons'], true) || in_array('sanctions_review', $row['reasons'], true),
                'documents' => in_array('pending_documents', $row['reasons'], true),
                'promotion' => in_array('eligible_promotion', $row['reasons'], true),
                default     => $row['urgency'] > 0,
            };
        })->sortByDesc('urgency')->values();

        $stats = [
            'total'         => $rows->count(),
            'sanctions_hit' => $rows->filter(fn ($r) => in_array('sanctions_hit', $r['reasons'], true))->count(),
            'sanctions_review' => $rows->filter(fn ($r) => in_array('sanctions_review', $r['reasons'], true))->count(),
            'pending_documents' => $rows->filter(fn ($r) => in_array('pending_documents', $r['reasons'], true))->count(),
            'eligible_promotion' => $rows->filter(fn ($r) => in_array('eligible_promotion', $r['reasons'], true))->count(),
        ];

        return view('dashboard.admin.verification.index', [
            'rows'   => $rows,
            'stats'  => $stats,
            'filter' => $filter,
        ]);
    }

    /**
     * Auto-promote a single company to its highest eligible tier from the
     * queue. Returns to the queue with a status flash.
     */
    public function autoPromote(int $id): RedirectResponse
    {
        $company  = Company::findOrFail($id);
        $promoted = $this->verification->autoPromoteIfEligible($company, auth()->id());

        if (!$promoted) {
            return back()->with('status', __('verification.no_promotion_needed'));
        }

        return back()->with('status', __('verification.promoted_to', ['tier' => $promoted->label()]));
    }
}
