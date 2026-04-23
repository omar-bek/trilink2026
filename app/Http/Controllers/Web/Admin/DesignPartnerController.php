<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Rfq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-side Design Partner tracker.
 *
 * Trilink is rolling out a pre-launch design-partner cohort: ~10 hand-
 * picked suppliers and ~3 buyers who use the platform end-to-end while
 * the product is still iterating. This controller is the single pane of
 * glass that tells the admin team where each partner sits in their
 * onboarding journey and which milestone is the current blocker.
 *
 * The milestones aren't a new table — they're derived on-the-fly from
 * existing facts in the database. A partner has "reached" a milestone as
 * soon as the corresponding row exists:
 *
 *   1. Activated         — companies.status = active
 *   2. Documents verified — any CompanyDocument with status = verified
 *   3. First RFQ        (buyers)    — Rfq row owned by the company
 *      First bid        (suppliers) — Bid row owned by the company
 *   4. First contract    — Contract row where the company is a party
 *   5. First payment     — Payment row linked through a contract
 *
 * Design notes:
 * - No new migration for milestone rows — the derivation keeps the
 *   tracker always-correct even if admin back-fills historical data.
 * - "Time to milestone" is measured against design_partner_started_at
 *   rather than created_at so companies that were already on the
 *   platform before joining the cohort don't start with a negative lag.
 * - The toggle action is idempotent: calling enroll() on an already-
 *   enrolled partner refreshes the role/notes without resetting the
 *   started_at clock.
 */
class DesignPartnerController extends Controller
{
    public function index(Request $request): View
    {
        $roleFilter = in_array($request->query('role'), ['buyer', 'supplier', 'all'], true)
            ? $request->query('role')
            : 'all';

        $partners = Company::query()
            ->where('is_design_partner', true)
            ->when($roleFilter !== 'all', fn ($q) => $q->where('design_partner_role', $roleFilter))
            ->orderBy('design_partner_started_at')
            ->orderBy('name')
            ->get();

        $rows = $partners->map(fn (Company $c) => $this->buildRow($c))->values();

        $stats = [
            'suppliers'        => $partners->where('design_partner_role', 'supplier')->count(),
            'suppliers_target' => 10,
            'buyers'           => $partners->where('design_partner_role', 'buyer')->count(),
            'buyers_target'    => 3,
            'fully_onboarded'  => $rows->where('completion', 100)->count(),
            'blocked'          => $rows->where('blocked', true)->count(),
        ];

        return view('dashboard.admin.design-partners.index', [
            'rows'       => $rows,
            'stats'      => $stats,
            'roleFilter' => $roleFilter,
        ]);
    }

    /**
     * Enroll (or update) a company in the cohort.
     */
    public function enroll(Request $request, int $companyId): RedirectResponse
    {
        $data = $request->validate([
            'role'  => ['required', Rule::in(['buyer', 'supplier'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = Company::findOrFail($companyId);

        $company->forceFill([
            'is_design_partner'         => true,
            'design_partner_role'       => $data['role'],
            'design_partner_started_at' => $company->design_partner_started_at ?? now(),
            'design_partner_notes'      => $data['notes'] ?? $company->design_partner_notes,
        ])->save();

        return back()->with('status', __('design_partners.enrolled', ['name' => $company->name]));
    }

    /**
     * Remove a company from the cohort. History (start date, notes) is
     * cleared so re-enrolment starts a fresh clock.
     */
    public function unenroll(int $companyId): RedirectResponse
    {
        $company = Company::findOrFail($companyId);

        $company->forceFill([
            'is_design_partner'         => false,
            'design_partner_role'       => null,
            'design_partner_started_at' => null,
            'design_partner_notes'      => null,
        ])->save();

        return back()->with('status', __('design_partners.unenrolled', ['name' => $company->name]));
    }

    /**
     * Derive the onboarding row for a single partner. Milestones are
     * shared across roles; the "first offer" milestone interprets
     * differently per role (RFQ for buyers, Bid for suppliers) so the
     * rest of the flow is symmetric.
     *
     * @return array<string, mixed>
     */
    private function buildRow(Company $company): array
    {
        $role = $company->design_partner_role ?? 'supplier';

        $docsVerified = CompanyDocument::query()
            ->where('company_id', $company->id)
            ->where('status', 'verified')
            ->exists();

        $firstOfferAt = $role === 'buyer'
            ? Rfq::query()->where('company_id', $company->id)->min('created_at')
            : Bid::query()->where('company_id', $company->id)->min('created_at');

        // Contracts: a company can appear as buyer_company_id OR as a
        // supplier via the ContractParty join table. Either side counts.
        $firstContractAt = Contract::query()
            ->where(function ($q) use ($company) {
                $q->where('buyer_company_id', $company->id)
                    ->orWhereIn('id', function ($sub) use ($company) {
                        $sub->select('contract_id')
                            ->from('contract_parties')
                            ->where('company_id', $company->id);
                    });
            })
            ->min('created_at');

        // Payments are linked via contracts, so we look for any Payment
        // row whose contract_id is in the set we just derived.
        $firstPaymentAt = null;
        if ($firstContractAt !== null) {
            $firstPaymentAt = Payment::query()
                ->whereIn('contract_id', function ($sub) use ($company) {
                    $sub->select('id')
                        ->from('contracts')
                        ->where('buyer_company_id', $company->id)
                        ->union(
                            DB::table('contract_parties')
                                ->select('contract_id as id')
                                ->where('company_id', $company->id)
                        );
                })
                ->min('created_at');
        }

        $milestones = [
            ['key' => 'activated',         'done' => $company->status?->value === 'active',  'at' => $company->verified_at],
            ['key' => 'documents_verified','done' => $docsVerified,                          'at' => null],
            ['key' => 'first_offer',       'done' => $firstOfferAt !== null,                 'at' => $firstOfferAt],
            ['key' => 'first_contract',    'done' => $firstContractAt !== null,              'at' => $firstContractAt],
            ['key' => 'first_payment',     'done' => $firstPaymentAt !== null,               'at' => $firstPaymentAt],
        ];

        $completed = collect($milestones)->where('done', true)->count();
        $completion = (int) round(($completed / count($milestones)) * 100);

        // "Blocked" = started ≥ 14 days ago AND next milestone is
        // documents_verified or earlier AND it's not done yet. That's
        // the signal the admin team needs to chase the partner.
        $daysSinceStart = $company->design_partner_started_at
            ? (int) $company->design_partner_started_at->diffInDays(now())
            : 0;
        $nextPending = collect($milestones)->firstWhere('done', false);
        $blocked = $daysSinceStart >= 14
            && $nextPending !== null
            && in_array($nextPending['key'], ['activated', 'documents_verified'], true);

        return [
            'company'        => $company,
            'role'           => $role,
            'started_at'     => $company->design_partner_started_at,
            'days_in'        => $daysSinceStart,
            'milestones'     => $milestones,
            'next_milestone' => $nextPending['key'] ?? null,
            'completion'     => $completion,
            'blocked'        => $blocked,
            'notes'          => $company->design_partner_notes,
        ];
    }
}
