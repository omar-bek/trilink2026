<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CarbonFootprint;
use App\Models\ConflictMineralsDeclaration;
use App\Models\ModernSlaveryStatement;
use App\Services\Esg\EsgScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 8 — single controller for the ESG dashboard, questionnaire,
 * modern slavery statements, conflict minerals declarations, and the
 * carbon footprint roll-up.
 *
 * Authorization rules:
 *   - View: any user with `esg.view` (default for buyers, suppliers,
 *     and managers).
 *   - Edit: only the company manager (esg.manage).
 *
 * The controller never lets a user see another company's data — every
 * query is scoped to the authenticated user's company_id.
 */
class EsgController extends Controller
{
    public function __construct(private readonly EsgScoringService $scoringService) {}

    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('esg.view'), 403);

        // Resolve everything off the company relationships introduced in
        // Phase 8 — keeps the controller free of repeated where(company_id)
        // boilerplate and lets the company model evolve the queries
        // (e.g. add eager loads) in one place.
        $company = $user->company;
        $questionnaire = $company?->esgQuestionnaire;
        $modernSlavery = $company?->modernSlaveryStatements()->first();
        $conflict = $company?->conflictMineralsDeclarations()->first();

        // Roll up the company's last 12 months of carbon footprint via
        // the polymorphic relationship. Sum is computed in SQL to keep
        // the dashboard fast even as entries grow.
        $cutoff = now()->subDays(365)->toDateString();
        $totalCo2 = (float) ($company
            ? $company->carbonFootprints()->where('period_start', '>=', $cutoff)->sum('co2e_kg')
            : 0);

        return view('dashboard.esg.index', [
            'questionnaire' => $questionnaire,
            'modern_slavery' => $modernSlavery,
            'conflict' => $conflict,
            'questions' => EsgScoringService::questions(),
            'total_co2' => $totalCo2,
        ]);
    }

    /**
     * Save / re-score the ESG questionnaire. Recomputes pillar scores
     * via EsgScoringService and persists the row.
     */
    public function saveQuestionnaire(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('esg.manage'), 403);

        $request->validate([
            'answers' => ['required', 'array'],
        ]);

        // Strip empty/unknown answers so we don't poison the score with
        // submission noise. The questionnaire definition is authoritative.
        $known = array_keys(EsgScoringService::questions());
        $answers = collect($request->input('answers'))
            ->only($known)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        $this->scoringService->score($user->company, $answers, $user);

        return back()->with('status', __('esg.questionnaire_saved'));
    }

    /**
     * Save the modern slavery statement for the current reporting year.
     * Replaces in place — the audit log captures the diff.
     */
    public function saveModernSlavery(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('esg.manage'), 403);

        $data = $request->validate([
            'reporting_year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'statement' => ['required', 'string', 'max:5000'],
            'controls' => ['nullable', 'array'],
            'board_approved' => ['nullable', 'boolean'],
            'signed_by_name' => ['nullable', 'string', 'max:191'],
            'signed_by_title' => ['nullable', 'string', 'max:191'],
        ]);

        ModernSlaveryStatement::updateOrCreate(
            [
                'company_id' => $user->company_id,
                'reporting_year' => $data['reporting_year'],
            ],
            [
                'statement' => $data['statement'],
                'controls' => $data['controls'] ?? [],
                'board_approved' => (bool) ($data['board_approved'] ?? false),
                'approved_at' => ! empty($data['board_approved']) ? now()->toDateString() : null,
                'signed_by_name' => $data['signed_by_name'] ?? null,
                'signed_by_title' => $data['signed_by_title'] ?? null,
            ],
        );

        return back()->with('status', __('esg.modern_slavery_saved'));
    }

    /**
     * Save the conflict minerals declaration for the current reporting year.
     */
    public function saveConflictMinerals(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('esg.manage'), 403);

        $data = $request->validate([
            'reporting_year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'tin_status' => ['required', 'string', 'in:conflict_free,in_progress,unknown'],
            'tungsten_status' => ['required', 'string', 'in:conflict_free,in_progress,unknown'],
            'tantalum_status' => ['required', 'string', 'in:conflict_free,in_progress,unknown'],
            'gold_status' => ['required', 'string', 'in:conflict_free,in_progress,unknown'],
            'smelters' => ['nullable', 'array'],
            'policy_url' => ['nullable', 'url', 'max:500'],
        ]);

        ConflictMineralsDeclaration::updateOrCreate(
            [
                'company_id' => $user->company_id,
                'reporting_year' => $data['reporting_year'],
            ],
            [
                'tin_status' => $data['tin_status'],
                'tungsten_status' => $data['tungsten_status'],
                'tantalum_status' => $data['tantalum_status'],
                'gold_status' => $data['gold_status'],
                'smelters' => $data['smelters'] ?? [],
                'policy_url' => $data['policy_url'] ?? null,
            ],
        );

        return back()->with('status', __('esg.conflict_minerals_saved'));
    }

    /**
     * Manual carbon entry. Used to log Scope 1 + 2 emissions that the
     * automated shipment calculator can't reach (corporate offices,
     * employee commuting, purchased electricity).
     */
    public function logCarbonEntry(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('esg.manage'), 403);

        $data = $request->validate([
            'scope' => ['required', 'integer', 'in:1,2,3'],
            'co2e_kg' => ['required', 'numeric', 'min:0'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        CarbonFootprint::create([
            'entity_type' => CarbonFootprint::ENTITY_COMPANY,
            'entity_id' => $user->company_id,
            'scope' => (int) $data['scope'],
            'co2e_kg' => (float) $data['co2e_kg'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'source' => 'manual_entry',
            'metadata' => ['notes' => $data['notes'] ?? null, 'logged_by' => $user->id],
        ]);

        return back()->with('status', __('esg.carbon_entry_saved'));
    }
}
