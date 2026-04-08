<?php

namespace App\Http\Controllers\Web;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\Dispute\StoreDisputeRequest;
use App\Models\Dispute;
use App\Services\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisputeController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly DisputeService $service)
    {
    }

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasPermission('dispute.view'), 403);

        $companyId = $this->currentCompanyId();

        $base = Dispute::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        // Filters from query string.
        $statusFilter = $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'open', 'in_mediation', 'resolved'], true)) {
            $statusFilter = 'all';
        }
        $search = trim((string) $request->query('q', ''));

        $total    = (clone $base)->count();
        $resolved = (clone $base)->where('status', DisputeStatus::RESOLVED->value)->count();

        $stats = [
            'open'            => (clone $base)->where('status', DisputeStatus::OPEN->value)->count(),
            'in_mediation'    => (clone $base)->whereIn('status', [DisputeStatus::UNDER_REVIEW->value, DisputeStatus::ESCALATED->value])->count(),
            'resolved'        => $resolved,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100) . '%' : '0%',
        ];

        $listing = (clone $base);

        match ($statusFilter) {
            'open'         => $listing->where('status', DisputeStatus::OPEN->value),
            'in_mediation' => $listing->whereIn('status', [DisputeStatus::UNDER_REVIEW->value, DisputeStatus::ESCALATED->value]),
            'resolved'     => $listing->where('status', DisputeStatus::RESOLVED->value),
            default        => null,
        };

        if ($search !== '') {
            $like = '%' . $search . '%';
            $listing->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('contract', fn ($c) => $c->where('contract_number', 'like', $like));
            });
        }

        $disputes = $listing
            ->with(['contract', 'againstCompany', 'assignedTo'])
            ->latest()
            ->get()
            ->map(function (Dispute $d) {
                $statusKey = $this->mapDisputeStatus($this->statusValue($d->status));
                $resolvedAt = $d->resolved_at;

                return [
                    'id'          => $this->disputeDisplayNumber($d),
                    'numeric_id'  => $d->id,
                    'status'      => $statusKey,
                    'priority'    => $this->priorityFor($d),
                    'title'       => $d->title,
                    'desc'        => $d->description ?? '',
                    'contract'    => $d->contract?->contract_number ?? '—',
                    'supplier'    => $d->againstCompany?->name ?? '—',
                    'type'        => $this->typeLabel($this->statusValue($d->type)),
                    'opened'      => $this->longDate($d->created_at),
                    'mediator'    => $d->assignedTo
                        ? trim(($d->assignedTo->first_name ?? '') . ' ' . ($d->assignedTo->last_name ?? ''))
                        : null,
                    // No `dispute_messages` table yet — surface null so the
                    // view's @if hides the badge instead of showing "0 msgs".
                    'messages'    => null,
                    'amount'      => $this->money((float) ($d->contract?->total_amount ?? 0), $d->contract?->currency ?? 'AED'),
                    'resolved_at' => $resolvedAt ? $this->longDate($resolvedAt) : null,
                    'resolution'  => $d->resolution,
                ];
            })
            ->toArray();

        $resultCount = count($disputes);

        // Pre-load the user's signed contracts for the "Open new dispute"
        // modal. We surface the contract number, the counterparty company id,
        // and the counterparty name so the form can submit `contract_id` and
        // `against_company_id` directly without an extra round-trip.
        $disputableContracts = [];
        if ($companyId) {
            $contracts = \App\Models\Contract::query()
                ->where(function ($q) use ($companyId) {
                    $q->whereJsonContains('parties', ['company_id' => $companyId])
                      ->orWhere('buyer_company_id', $companyId);
                })
                ->whereIn('status', [
                    \App\Enums\ContractStatus::ACTIVE->value,
                    \App\Enums\ContractStatus::SIGNED->value,
                    \App\Enums\ContractStatus::COMPLETED->value,
                ])
                ->latest()
                ->limit(50)
                ->get(['id', 'contract_number', 'title', 'parties', 'buyer_company_id']);

            $partyIds = $contracts
                ->flatMap(fn ($c) => collect($c->parties ?? [])->pluck('company_id'))
                ->push(...$contracts->pluck('buyer_company_id'))
                ->filter()
                ->unique();
            $partyNames = \App\Models\Company::whereIn('id', $partyIds)->pluck('name', 'id');

            foreach ($contracts as $c) {
                // Pick the OTHER party (not the current company) as the
                // default `against_company_id` candidate.
                $against = collect($c->parties ?? [])
                    ->pluck('company_id')
                    ->push($c->buyer_company_id)
                    ->filter(fn ($id) => $id && $id !== $companyId)
                    ->first();

                $disputableContracts[] = [
                    'id'                 => $c->id,
                    'contract_number'    => $c->contract_number,
                    'title'              => $c->title,
                    'against_company_id' => $against,
                    'against_name'       => $against ? ($partyNames[$against] ?? '—') : '—',
                ];
            }
        }

        return view('dashboard.disputes.index', compact('stats', 'disputes', 'statusFilter', 'search', 'resultCount', 'disputableContracts'));
    }

    /**
     * Display label for a dispute. Format `DIS-{year}-{id}` consistently.
     * Replaces the old `sprintf('DIS-2024-%04d', 3 + $d->id)` hack.
     */
    private function disputeDisplayNumber(Dispute $d): string
    {
        $year = $d->created_at?->format('Y') ?? date('Y');

        return sprintf('DIS-%s-%04d', $year, $d->id);
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('dispute.view'), 403);

        $d = Dispute::with(['contract', 'againstCompany', 'company', 'raisedByUser', 'assignedTo'])
            ->findOrFail((int) $id);

        $statusKey = $this->mapDisputeStatus($this->statusValue($d->status));

        $dispute = [
            'id'          => $this->disputeDisplayNumber($d),
            'numeric_id'  => $d->id,
            'status'      => $statusKey,
            'priority'    => $this->priorityFor($d),
            'title'       => $d->title,
            'desc'        => $d->description ?? '',
            'contract'    => $d->contract?->contract_number ?? '—',
            'amount'      => $this->money((float) ($d->contract?->total_amount ?? 0), $d->contract?->currency ?? 'AED'),
            'type'        => $this->typeLabel($this->statusValue($d->type)),
            'opened'      => $this->longDate($d->created_at),
            'opened_by'   => $d->raisedByUser
                ? trim(($d->raisedByUser->first_name ?? '') . ' ' . ($d->raisedByUser->last_name ?? ''))
                : '—',
            'against'     => $d->againstCompany?->name ?? '—',
            'mediator'    => $d->assignedTo
                ? trim(($d->assignedTo->first_name ?? '') . ' ' . ($d->assignedTo->last_name ?? ''))
                : null,
            'sla_due'     => $d->sla_due_date ? $this->longDate($d->sla_due_date) : null,
            'escalated'   => (bool) $d->escalated_to_government,
            'resolution'  => $d->resolution,
            'resolved_at' => $d->resolved_at ? $this->longDate($d->resolved_at) : null,
        ];

        return view('dashboard.disputes.show', compact('dispute'));
    }

    public function store(StoreDisputeRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('dispute.open'), 403, 'Forbidden: missing disputes.create permission.');

        $dispute = $this->service->create([
            'contract_id'        => $request->input('contract_id'),
            'company_id'         => $user->company_id,
            'raised_by'          => $user->id,
            'against_company_id' => $request->input('against_company_id'),
            'type'               => $request->input('type'),
            'status'             => DisputeStatus::OPEN,
            'title'              => $request->input('title'),
            'description'        => $request->input('description'),
        ]);

        return redirect()
            ->route('dashboard.disputes')
            ->with('status', __('disputes.opened_successfully'));
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

    public function resolve(string $id): RedirectResponse
    {
        $dispute = Dispute::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('dispute.resolve'), 403, 'Forbidden: missing disputes.resolve permission.');

        $resolution = request()->validate([
            'resolution' => ['required', 'string', 'max:2000'],
        ])['resolution'];

        $this->service->resolve($dispute->id, $resolution);

        return redirect()
            ->route('dashboard.disputes')
            ->with('status', __('disputes.resolved_successfully'));
    }

    private function mapDisputeStatus(string $status): string
    {
        return match ($status) {
            'open'         => 'open',
            'under_review' => 'in_mediation',
            'escalated'    => 'in_mediation',
            'resolved'     => 'resolved',
            default        => 'open',
        };
    }

    private function priorityFor(Dispute $d): string
    {
        if ($d->escalated_to_government) {
            return 'high';
        }

        return match ($this->statusValue($d->type)) {
            'quality', 'contract_breach' => 'high',
            'delivery'                   => 'medium',
            default                      => 'low',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'quality'         => 'Quality Issue',
            'delivery'        => 'Late Delivery',
            'payment'         => 'Payment Dispute',
            'contract_breach' => 'Contract Violation',
            default           => 'Other',
        };
    }
}
