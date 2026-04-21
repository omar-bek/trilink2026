<?php

namespace App\Http\Controllers\Web;

use App\Enums\ContractStatus;
use App\Enums\DisputeDecisionOutcome;
use App\Enums\DisputeRemedy;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\Dispute\StoreDisputeRequest;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\DisputeOffer;
use App\Services\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisputeController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly DisputeService $service) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasPermission('dispute.view'), 403);

        $companyId = $this->currentCompanyId();

        // Show disputes where the company is either claimant OR respondent.
        // Before, the index only showed claimant-side disputes so respondents
        // literally couldn't see cases filed against them.
        $base = Dispute::query()->when($companyId, function ($q) use ($companyId) {
            $q->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhere('against_company_id', $companyId);
            });
        });

        $statusFilter = $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'open', 'in_mediation', 'resolved', 'overdue'], true)) {
            $statusFilter = 'all';
        }
        $search = trim((string) $request->query('q', ''));

        $total = (clone $base)->count();
        $resolved = (clone $base)->where('status', DisputeStatus::RESOLVED->value)->count();

        $openStates = [DisputeStatus::OPEN->value, DisputeStatus::ACKNOWLEDGED->value];
        $mediationStates = [
            DisputeStatus::UNDER_NEGOTIATION->value,
            DisputeStatus::IN_MEDIATION->value,
            DisputeStatus::AWAITING_DECISION->value,
            DisputeStatus::ESCALATED->value,
            DisputeStatus::UNDER_REVIEW->value, // legacy
        ];

        $stats = [
            'open' => (clone $base)->whereIn('status', $openStates)->count(),
            'in_mediation' => (clone $base)->whereIn('status', $mediationStates)->count(),
            'resolved' => $resolved,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100).'%' : '0%',
        ];

        $listing = (clone $base);

        match ($statusFilter) {
            'open' => $listing->whereIn('status', $openStates),
            'in_mediation' => $listing->whereIn('status', $mediationStates),
            'resolved' => $listing->where('status', DisputeStatus::RESOLVED->value),
            'overdue' => $listing->where('sla_due_date', '<', now())
                ->whereNotIn('status', [DisputeStatus::RESOLVED->value, DisputeStatus::WITHDRAWN->value, DisputeStatus::EXPIRED->value]),
            default => null,
        };

        if ($search !== '') {
            $like = '%'.$search.'%';
            $listing->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('contract', fn ($c) => $c->where('contract_number', 'like', $like));
            });
        }

        $disputes = $listing
            ->with(['contract', 'againstCompany', 'company', 'assignedTo'])
            ->withCount(['messages' => fn ($q) => $q->where('is_system', false)->where('is_internal', false)])
            ->latest()
            ->get()
            ->map(fn (Dispute $d) => $this->rowForIndex($d, $companyId))
            ->toArray();

        $resultCount = count($disputes);

        $disputableContracts = $this->disputableContracts($companyId);

        return view('dashboard.disputes.index', compact('stats', 'disputes', 'statusFilter', 'search', 'resultCount', 'disputableContracts'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('dispute.view'), 403);

        $user = auth()->user();
        $dispute = Dispute::with([
            'contract', 'againstCompany', 'company', 'raisedByUser', 'assignedTo', 'decidedBy',
            'messages.user', 'messages.company',
            'offers.offeredByUser', 'offers.offeredByCompany', 'offers.respondedBy',
            'events.actorUser', 'events.actorCompany',
        ])->findOrFail((int) $id);

        // Access gate — either a party to the dispute or government/admin.
        $role = $user?->role?->value;
        $isParty = $dispute->isPartyCompany($user?->company_id);
        $isOversight = in_array($role, ['government', 'admin'], true);
        abort_unless($isParty || $isOversight, 403);

        $viewerCompanyId = $user?->company_id;
        $isClaimant = $dispute->company_id === $viewerCompanyId;
        $isRespondent = $dispute->against_company_id === $viewerCompanyId;

        $case = $this->buildCaseFile($dispute, $viewerCompanyId, $isClaimant, $isRespondent, $isOversight);

        return view('dashboard.disputes.show', [
            'dispute' => $case,
            'dispute_model' => $dispute,
            'remedies' => DisputeRemedy::cases(),
            'severities' => DisputeSeverity::cases(),
            'outcomes' => DisputeDecisionOutcome::cases(),
        ]);
    }

    public function store(StoreDisputeRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('dispute.open'), 403, 'Forbidden: missing disputes.create permission.');

        $this->service->create([
            'contract_id' => $request->input('contract_id'),
            'company_id' => $user->company_id,
            'raised_by' => $user->id,
            'against_company_id' => $request->input('against_company_id'),
            'type' => $request->input('type'),
            'status' => DisputeStatus::OPEN->value,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'claim_amount' => $request->input('claim_amount'),
            'claim_currency' => $request->input('claim_currency', 'AED'),
            'requested_remedy' => $request->input('requested_remedy'),
            'severity' => $request->input('severity', DisputeSeverity::MEDIUM->value),
        ]);

        return redirect()
            ->route('dashboard.disputes')
            ->with('status', __('disputes.opened_successfully'));
    }

    /**
     * Respondent acknowledges the claim. Stops the response-SLA clock.
     */
    public function acknowledge(string $id): RedirectResponse
    {
        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();
        abort_unless($user && $dispute->against_company_id === $user->company_id, 403);

        $this->service->acknowledge($dispute, $user);

        return back()->with('status', __('disputes.acknowledged_successfully'));
    }

    /**
     * Post a message into the conversation thread.
     */
    public function message(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();
        $this->service->postMessage($dispute, $user, $data['body'], (bool) ($data['internal'] ?? false));

        return back()->with('status', __('disputes.message_sent'));
    }

    /**
     * Submit a structured settlement offer.
     */
    public function submitOffer(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'remedy' => ['nullable', new \Illuminate\Validation\Rules\Enum(DisputeRemedy::class)],
            'terms' => ['required', 'string', 'max:2000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();

        $this->service->submitOffer($dispute, $user, [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'AED',
            'remedy' => $data['remedy'] ?? null,
            'terms' => $data['terms'],
            'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 7)),
        ]);

        return back()->with('status', __('disputes.offer_submitted'));
    }

    /**
     * Act on an existing offer — accept / reject / counter.
     */
    public function respondToOffer(Request $request, string $id, string $offerId): RedirectResponse
    {
        $dispute = Dispute::findOrFail((int) $id);
        $offer = DisputeOffer::where('dispute_id', $dispute->id)->findOrFail((int) $offerId);
        $user = auth()->user();

        $action = $request->input('action');

        match ($action) {
            'accept' => $this->service->acceptOffer(
                $offer, $user,
                $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null
            ),
            'reject' => $this->service->rejectOffer(
                $offer, $user,
                $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null
            ),
            'counter' => $this->service->counterOffer($offer, $user, $request->validate([
                'amount' => ['required', 'numeric', 'min:0'],
                'currency' => ['nullable', 'string', 'size:3'],
                'remedy' => ['nullable', new \Illuminate\Validation\Rules\Enum(DisputeRemedy::class)],
                'terms' => ['required', 'string', 'max:2000'],
                'note' => ['nullable', 'string', 'max:1000'],
                'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            ]) + ['expires_at' => now()->addDays((int) $request->input('expires_in_days', 7))]),
            default => abort(422, 'Invalid action'),
        };

        return back()->with('status', __('disputes.offer_updated'));
    }

    /**
     * Claimant withdraws their dispute.
     */
    public function withdraw(Request $request, string $id): RedirectResponse
    {
        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ])['reason'] ?? null;

        $this->service->withdraw($dispute, $user, $reason);

        return redirect()->route('dashboard.disputes')
            ->with('status', __('disputes.withdrawn_successfully'));
    }

    public function escalate(string $id): RedirectResponse
    {
        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('dispute.escalate'), 403, 'Forbidden: missing disputes.escalate permission.');
        abort_unless($dispute->company_id === $user->company_id, 403);

        $this->service->escalate($dispute->id);

        return redirect()
            ->route('dashboard.disputes')
            ->with('status', __('disputes.escalated_successfully'));
    }

    public function resolve(Request $request, string $id): RedirectResponse
    {
        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('dispute.resolve'), 403, 'Forbidden: missing disputes.resolve permission.');

        $data = $request->validate([
            'resolution' => ['required', 'string', 'max:2000'],
            'decision_outcome' => ['nullable', new \Illuminate\Validation\Rules\Enum(DisputeDecisionOutcome::class)],
            'decision_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->decide($dispute, $user, [
            'decision_outcome' => $data['decision_outcome'] ?? DisputeDecisionOutcome::SETTLED->value,
            'decision_amount' => $data['decision_amount'] ?? null,
            'resolution' => $data['resolution'],
        ]);

        return redirect()
            ->route('dashboard.disputes')
            ->with('status', __('disputes.resolved_successfully'));
    }

    // ──────────────────────────────────────────────────────────────
    // View data builders
    // ──────────────────────────────────────────────────────────────

    private function rowForIndex(Dispute $d, ?int $viewerCompanyId): array
    {
        $statusKey = $this->mapDisputeStatus($this->statusValue($d->status));
        $resolvedAt = $d->resolved_at;
        $role = $d->company_id === $viewerCompanyId ? 'claimant' : 'respondent';

        return [
            'id' => $this->disputeDisplayNumber($d),
            'numeric_id' => $d->id,
            'status' => $statusKey,
            'priority' => $this->priorityFor($d),
            'role' => $role,
            'title' => $d->title,
            'desc' => $d->description ?? '',
            'contract' => $d->contract?->contract_number ?? '—',
            'supplier' => $d->againstCompany?->name ?? '—',
            'type' => $this->typeLabel($this->statusValue($d->type)),
            'opened' => $this->longDate($d->created_at),
            'mediator' => $d->assignedTo
                ? trim(($d->assignedTo->first_name ?? '').' '.($d->assignedTo->last_name ?? ''))
                : null,
            'messages' => $d->messages_count ?: null,
            'claim_amount' => $d->claim_amount ? $this->money((float) $d->claim_amount, $d->claim_currency ?? 'AED') : null,
            'amount' => $this->money((float) ($d->contract?->total_amount ?? 0), $d->contract?->currency ?? 'AED'),
            'resolved_at' => $resolvedAt ? $this->longDate($resolvedAt) : null,
            'resolution' => $d->resolution,
            'sla_overdue' => $d->resolutionOverdue(),
        ];
    }

    /**
     * Hydrate the full case-file payload for the show view: header data,
     * parties, structured claim, SLA state, timeline, offers, and the
     * viewer-specific flags that drive UI affordances.
     */
    private function buildCaseFile(Dispute $d, ?int $viewerCompanyId, bool $isClaimant, bool $isRespondent, bool $isOversight): array
    {
        $statusKey = $this->mapDisputeStatus($this->statusValue($d->status));
        $latestPendingOffer = $d->offers->firstWhere(
            fn ($o) => $o->status?->value === 'pending'
        );

        return [
            'id' => $this->disputeDisplayNumber($d),
            'numeric_id' => $d->id,
            'status' => $statusKey,
            'raw_status' => $this->statusValue($d->status),
            'priority' => $this->priorityFor($d),
            'severity' => $d->severity?->value ?? 'medium',
            'title' => $d->title,
            'desc' => $d->description ?? '',
            'type' => $this->typeLabel($this->statusValue($d->type)),
            'type_raw' => $this->statusValue($d->type),
            'contract' => $d->contract?->contract_number ?? '—',
            'contract_id' => $d->contract_id,
            'amount' => $this->money((float) ($d->contract?->total_amount ?? 0), $d->contract?->currency ?? 'AED'),
            'claim_amount' => $d->claim_amount ? $this->money((float) $d->claim_amount, $d->claim_currency ?? 'AED') : null,
            'requested_remedy' => $d->requested_remedy?->value,
            'opened' => $this->longDate($d->created_at),
            'opened_raw' => $d->created_at,
            'opened_by' => $d->raisedByUser
                ? trim(($d->raisedByUser->first_name ?? '').' '.($d->raisedByUser->last_name ?? ''))
                : '—',
            'claimant' => $d->company?->name ?? '—',
            'claimant_id' => $d->company_id,
            'against' => $d->againstCompany?->name ?? '—',
            'respondent_id' => $d->against_company_id,
            'mediator' => $d->assignedTo
                ? trim(($d->assignedTo->first_name ?? '').' '.($d->assignedTo->last_name ?? ''))
                : null,
            // SLA state.
            'response_due' => $d->response_due_at ? $this->longDate($d->response_due_at) : null,
            'response_due_raw' => $d->response_due_at,
            'response_overdue' => $d->responseOverdue(),
            'sla_due' => $d->sla_due_date ? $this->longDate($d->sla_due_date) : null,
            'sla_due_raw' => $d->sla_due_date,
            'resolution_overdue' => $d->resolutionOverdue(),
            'acknowledged_at' => $d->acknowledged_at ? $this->longDate($d->acknowledged_at) : null,
            'escalated' => (bool) $d->escalated_to_government,
            // Resolution.
            'resolution' => $d->resolution,
            'resolved_at' => $d->resolved_at ? $this->longDate($d->resolved_at) : null,
            'decision_outcome' => $d->decision_outcome?->value,
            'decision_amount' => $d->decision_amount
                ? $this->money((float) $d->decision_amount, $d->claim_currency ?? 'AED')
                : null,
            'decided_by' => $d->decidedBy
                ? trim(($d->decidedBy->first_name ?? '').' '.($d->decidedBy->last_name ?? ''))
                : null,
            // Collections.
            'messages' => $d->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_internal' => $m->is_internal,
                'is_system' => $m->is_system,
                'author' => $m->user
                    ? trim(($m->user->first_name ?? '').' '.($m->user->last_name ?? ''))
                    : null,
                'company' => $m->company?->name,
                'company_id' => $m->company_id,
                'at' => $m->created_at?->diffForHumans(),
                'at_full' => $this->longDate($m->created_at),
            ])->all(),
            'offers' => $d->offers->map(fn ($o) => [
                'id' => $o->id,
                'amount' => $this->money((float) $o->amount, $o->currency ?? 'AED'),
                'amount_raw' => (float) $o->amount,
                'currency' => $o->currency,
                'remedy' => $o->remedy?->value,
                'terms' => $o->terms,
                'status' => $o->status?->value,
                'from' => $o->offeredByCompany?->name,
                'from_company_id' => $o->offered_by_company_id,
                'by' => $o->offeredByUser
                    ? trim(($o->offeredByUser->first_name ?? '').' '.($o->offeredByUser->last_name ?? ''))
                    : null,
                'at' => $this->longDate($o->created_at),
                'expires' => $o->expires_at ? $this->longDate($o->expires_at) : null,
                'expires_raw' => $o->expires_at,
                'expired' => $o->isExpired(),
                'response_note' => $o->response_note,
                'parent_offer_id' => $o->parent_offer_id,
            ])->all(),
            'timeline' => $d->events->map(fn ($e) => [
                'event' => $e->event,
                'actor' => $e->actorUser
                    ? trim(($e->actorUser->first_name ?? '').' '.($e->actorUser->last_name ?? ''))
                    : ($e->actor_user_id ? __('disputes.timeline.system') : __('disputes.timeline.platform')),
                'company' => $e->actorCompany?->name,
                'metadata' => $e->metadata,
                'at' => $e->created_at?->diffForHumans(),
                'at_full' => $this->longDate($e->created_at),
            ])->all(),
            // Viewer context — drives which buttons/panels render.
            'viewer' => [
                'is_claimant' => $isClaimant,
                'is_respondent' => $isRespondent,
                'is_party' => $isClaimant || $isRespondent,
                'is_oversight' => $isOversight,
                'can_acknowledge' => $isRespondent && $d->status === DisputeStatus::OPEN,
                'can_message' => ($isClaimant || $isRespondent || $isOversight) && ! $d->status?->isTerminal(),
                'can_offer' => ($isClaimant || $isRespondent) && $d->status?->canOffer(),
                'can_escalate' => $isClaimant && $d->status?->canEscalate() && ! $d->escalated_to_government,
                'can_withdraw' => $isClaimant && $d->status?->canWithdraw(),
                'can_decide' => $isOversight && ! $d->status?->isTerminal(),
                'respond_offer_id' => $latestPendingOffer
                    && $latestPendingOffer->offered_by_company_id !== $viewerCompanyId
                        ? $latestPendingOffer->id : null,
            ],
        ];
    }

    private function disputableContracts(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        $contracts = Contract::query()
            ->where(function ($q) use ($companyId) {
                $q->whereJsonContains('parties', ['company_id' => $companyId])
                    ->orWhere('buyer_company_id', $companyId);
            })
            ->whereIn('status', [
                ContractStatus::ACTIVE->value,
                ContractStatus::SIGNED->value,
                ContractStatus::COMPLETED->value,
            ])
            ->latest()
            ->limit(50)
            ->get(['id', 'contract_number', 'title', 'parties', 'buyer_company_id']);

        $partyIds = $contracts
            ->flatMap(fn ($c) => collect($c->parties ?? [])->pluck('company_id'))
            ->push(...$contracts->pluck('buyer_company_id'))
            ->filter()->unique();
        $partyNames = Company::whereIn('id', $partyIds)->pluck('name', 'id');

        $out = [];
        foreach ($contracts as $c) {
            $against = collect($c->parties ?? [])
                ->pluck('company_id')
                ->push($c->buyer_company_id)
                ->filter(fn ($id) => $id && $id !== $companyId)
                ->first();

            $out[] = [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'title' => $c->title,
                'against_company_id' => $against,
                'against_name' => $against ? ($partyNames[$against] ?? '—') : '—',
            ];
        }

        return $out;
    }

    private function disputeDisplayNumber(Dispute $d): string
    {
        $year = $d->created_at?->format('Y') ?? date('Y');

        return sprintf('DIS-%s-%04d', $year, $d->id);
    }

    private function mapDisputeStatus(string $status): string
    {
        return match ($status) {
            'open' => 'open',
            'acknowledged' => 'acknowledged',
            'under_review', 'under_negotiation' => 'in_mediation',
            'in_mediation' => 'in_mediation',
            'awaiting_decision' => 'in_mediation',
            'escalated' => 'in_mediation',
            'resolved' => 'resolved',
            'withdrawn' => 'withdrawn',
            'expired' => 'expired',
            default => 'open',
        };
    }

    private function priorityFor(Dispute $d): string
    {
        $sev = $d->severity?->value;
        if ($sev === 'critical' || $d->escalated_to_government) {
            return 'high';
        }
        if ($sev === 'high') {
            return 'high';
        }
        if ($sev === 'low') {
            return 'low';
        }

        return match ($this->statusValue($d->type)) {
            'quality', 'contract_breach' => 'high',
            'delivery' => 'medium',
            default => 'low',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'quality' => 'Quality Issue',
            'delivery' => 'Late Delivery',
            'payment' => 'Payment Dispute',
            'contract_breach' => 'Contract Violation',
            default => 'Other',
        };
    }
}
