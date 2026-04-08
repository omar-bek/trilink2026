<?php

namespace App\Http\Controllers\Web;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Shipment;
use App\Services\ContractService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly ContractService $service)
    {
    }

    /**
     * Streamed CSV export of the current scope of contracts. Respects the
     * same tenant scoping as index() — buyers only see their own contracts,
     * suppliers only see contracts where their company is a party. Admin
     * sees everything.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('contract.view'), 403);

        $user      = auth()->user();
        $role      = $user?->role?->value ?? 'buyer';
        $companyId = $this->currentCompanyId();
        $isSupplier = in_array($role, ['supplier', 'service_provider', 'logistics', 'clearance'], true);

        $base = Contract::query();
        if ($companyId) {
            if ($isSupplier) {
                $base->where(function ($q) use ($companyId) {
                    $q->whereJsonContains('parties', ['company_id' => $companyId])
                      ->orWhere('buyer_company_id', $companyId);
                });
            } else {
                $base->where('buyer_company_id', $companyId);
            }
        }

        $ids = (array) $request->query('ids', []);
        if (! empty($ids)) {
            $ids = array_filter(array_map('intval', $ids));
            if (! empty($ids)) {
                $base->whereIn('id', $ids);
            }
        }

        $contracts = $base->with('payments')->latest()->get();

        $partyCompanyIds = $contracts
            ->flatMap(fn (Contract $c) => collect($c->parties ?? [])->pluck('company_id'))
            ->push(...$contracts->pluck('buyer_company_id'))
            ->filter()
            ->unique()
            ->values();
        $companyNames = Company::whereIn('id', $partyCompanyIds)->pluck('name', 'id');

        $filename = 'contracts-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($contracts, $companyNames) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Contract Number',
                'Title',
                'Status',
                'Supplier',
                'Buyer',
                'Currency',
                'Total Amount',
                'Paid Amount',
                'Start Date',
                'End Date',
                'Signed',
                'Progress %',
                'Created At',
            ]);

            foreach ($contracts as $c) {
                $supplierName = $this->supplierName($c, $companyNames);
                $buyerName    = $companyNames[$c->buyer_company_id] ?? '—';
                $paid = $c->payments
                    ? $c->payments->where('status', PaymentStatus::COMPLETED->value)->sum('amount')
                    : 0;
                $signed = is_array($c->signatures) && count($c->signatures) >= 2 ? 'yes' : 'no';

                fputcsv($out, [
                    $c->contract_number,
                    $c->title,
                    $this->statusValue($c->status),
                    $supplierName,
                    $buyerName,
                    $c->currency ?? 'AED',
                    number_format((float) $c->total_amount, 2, '.', ''),
                    number_format((float) $paid, 2, '.', ''),
                    $c->start_date?->toDateString() ?? '',
                    $c->end_date?->toDateString() ?? '',
                    $signed,
                    (int) ($c->progress_percentage ?? 0),
                    $c->created_at?->toDateTimeString() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasPermission('contract.view'), 403);

        $user      = auth()->user();
        $role      = $user?->role?->value ?? 'buyer';
        $companyId = $this->currentCompanyId();
        $query     = trim((string) $request->query('q', ''));

        // Filters from query string.
        $statusFilter = $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'active', 'completed', 'draft', 'pending', 'cancelled'], true)) {
            $statusFilter = 'all';
        }

        $sort = $request->query('sort', 'newest');
        if (! in_array($sort, ['newest', 'oldest', 'value_desc', 'value_asc', 'ending_soon'], true)) {
            $sort = 'newest';
        }

        // Suppliers see contracts where THEIR company is a party (via parties JSON)
        // plus ones where the buyer_company_id matches them (legacy / fallback).
        $isSupplier = in_array($role, ['supplier', 'service_provider', 'logistics', 'clearance'], true);

        $base = Contract::query();
        if ($companyId) {
            if ($isSupplier) {
                $base->where(function ($q) use ($companyId) {
                    $q->whereJsonContains('parties', ['company_id' => $companyId])
                      ->orWhere('buyer_company_id', $companyId);
                });
            } else {
                $base->where('buyer_company_id', $companyId);
            }
        }

        // Stats are computed against the un-search/-filtered base, so the
        // headline numbers don't dance around as the user types in search.
        $statsBase  = clone $base;
        $totalValue = (clone $statsBase)->sum('total_amount');

        $stats = [
            'total'     => (clone $statsBase)->count(),
            'active'    => (clone $statsBase)->where('status', ContractStatus::ACTIVE->value)->count(),
            'completed' => (clone $statsBase)->where('status', ContractStatus::COMPLETED->value)->count(),
            'value'     => $this->shortMoney((float) $totalValue),
        ];

        // Apply listing filters AFTER computing stats so the cards stay stable.
        if ($query !== '') {
            $base->search($query, ['title', 'contract_number']);
        }

        if ($statusFilter !== 'all') {
            $base->where('status', $statusFilter);
        }

        match ($sort) {
            'oldest'      => $base->oldest(),
            'value_desc'  => $base->orderByDesc('total_amount')->orderByDesc('id'),
            'value_asc'   => $base->orderBy('total_amount')->orderByDesc('id'),
            'ending_soon' => $base->orderByRaw('end_date IS NULL, end_date ASC')->orderByDesc('id'),
            default       => $base->latest(),
        };

        // Pre-load all supplier names referenced in `parties` JSON to avoid N+1.
        // Payments are eager-loaded so the supplier card's "Received / Pending"
        // numbers are computed from real data instead of a progress estimate.
        $contractRows = (clone $base)->with('payments')->get();
        $partyCompanyIds = $contractRows
            ->flatMap(fn (Contract $c) => collect($c->parties ?? [])->pluck('company_id'))
            ->push(...$contractRows->pluck('buyer_company_id'))
            ->filter()
            ->unique()
            ->values();

        $companyNames = Company::whereIn('id', $partyCompanyIds)->pluck('name', 'id');

        if ($isSupplier) {
            return $this->supplierIndex($contractRows, $companyNames, $stats, $totalValue);
        }

        $contracts = $contractRows->map(function (Contract $c) use ($companyNames) {
            $statusKey = $this->mapContractStatus($this->statusValue($c->status));
            [$progress, $label, $color] = $this->progressFor($statusKey, $c);

            return [
                'id'             => $c->contract_number,
                'numeric_id'     => $c->id,
                'status'         => $statusKey,
                'title'          => $c->title,
                'supplier'       => $this->supplierName($c, $companyNames),
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'started'        => $this->date($c->start_date ?? $c->created_at),
                'expected'       => $this->date($c->end_date),
                'progress_label' => $label,
                'progress'       => $progress,
                'progress_color' => $color,
            ];
        })->toArray();

        return view('dashboard.contracts.index', compact('stats', 'contracts', 'statusFilter', 'sort'));
    }

    /**
     * Supplier-side "My Contracts" — same query, different framing. Splits
     * contracts by status into Active / Completed tabs, computes the average
     * progress KPI, and shows buyer names + pending payments instead of a
     * supplier column.
     */
    private function supplierIndex($contractRows, $companyNames, array $stats, float $totalValue): View
    {
        $activeContracts   = collect();
        $completedContracts = collect();

        // Batch-fetch buyer feedback ratings for every completed contract in
        // one query so the per-row loop doesn't N+1 against the feedback
        // table. Keyed by contract_id.
        $ratingsByContract = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('feedback')) {
            $completedIds = $contractRows
                ->filter(fn (Contract $c) => $this->mapContractStatus($this->statusValue($c->status)) === 'completed')
                ->pluck('id')
                ->all();
            if ($completedIds !== []) {
                $ratingsByContract = \DB::table('feedback')
                    ->whereIn('contract_id', $completedIds)
                    ->get(['contract_id', 'rater_company_id', 'rating'])
                    ->mapWithKeys(fn ($row) => [
                        $row->contract_id . ':' . $row->rater_company_id => (float) $row->rating,
                    ])
                    ->all();
            }
        }

        foreach ($contractRows as $c) {
            $statusKey = $this->mapContractStatus($this->statusValue($c->status));
            [$progress, $label] = $this->progressFor($statusKey, $c);

            $rfqRef = $c->purchase_request_id
                ? 'RFQ-' . str_pad((string) $c->purchase_request_id, 4, '0', STR_PAD_LEFT)
                : '—';

            $buyerName = $c->buyer_company_id && isset($companyNames[$c->buyer_company_id])
                ? $companyNames[$c->buyer_company_id]
                : '—';

            $daysLeft = $c->end_date
                ? max(0, (int) now()->startOfDay()->diffInDays($c->end_date->startOfDay(), false))
                : null;

            // "Received" + "pending" payment totals for this contract. Use
            // total_amount (post-VAT) since that's what was actually wired.
            $paidAmount = 0.0;
            $pendingAmount = 0.0;
            $rating = null;
            if ($c->relationLoaded('payments') && $c->payments) {
                foreach ($c->payments as $p) {
                    if (in_array($this->statusValue($p->status), ['completed', 'paid'], true)) {
                        $paidAmount += (float) $p->total_amount;
                    } else {
                        $pendingAmount += (float) $p->total_amount;
                    }
                }
            } else {
                // Rough estimate when payments relation isn't hydrated.
                $paidAmount = (float) $c->total_amount * ($progress / 100);
                $pendingAmount = (float) $c->total_amount - $paidAmount;
            }

            // Real review for this completed contract from the buyer's side
            // is sourced from the prefetched $ratingsByContract map above.
            if ($statusKey === 'completed' && $c->buyer_company_id) {
                $rating = $ratingsByContract[$c->id . ':' . $c->buyer_company_id] ?? null;
            }

            $row = [
                'id'             => $c->contract_number,
                'numeric_id'     => $c->id,
                'rfq_ref'        => '#' . $rfqRef,
                'status'         => $statusKey,
                'status_label'   => $label,
                'title'          => $c->title,
                'buyer'          => $buyerName,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress'       => $progress,
                'started'        => $this->date($c->start_date ?? $c->created_at),
                'expected'       => $this->date($c->end_date),
                'days_left'      => $daysLeft,
                'received'       => $this->money($paidAmount, $c->currency ?? 'AED'),
                'pending'        => $this->money($pendingAmount, $c->currency ?? 'AED'),
                'rating'         => $rating,
                'completed_at'   => $statusKey === 'completed' ? $this->date($c->updated_at) : null,
            ];

            if ($statusKey === 'completed') {
                $completedContracts->push($row);
            } else {
                $activeContracts->push($row);
            }
        }

        // Supplier-specific KPIs: active + completed + total value + avg progress.
        $avgProgress = $contractRows->isNotEmpty()
            ? (int) round($contractRows->avg(function (Contract $c) {
                [$progress] = $this->progressFor($this->mapContractStatus($this->statusValue($c->status)), $c);
                return $progress;
            }))
            : 0;

        return view('dashboard.contracts.index-supplier', [
            'stats' => [
                'active'       => $stats['active'],
                'completed'    => $stats['completed'],
                'total_value'  => $this->money($totalValue, 'AED'),
                'avg_progress' => $avgProgress,
            ],
            'active_contracts'    => $activeContracts->all(),
            'completed_contracts' => $completedContracts->all(),
        ]);
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('contract.view'), 403);

        $contract = $this->findOrFail($id)->load(['payments', 'shipments', 'escrowAccount.releases.triggeredByUser']);

        // Authorization (IDOR fix): contracts are only visible to the
        // companies that are parties of them — buyer + everyone in the
        // parties JSON column. Admin and government bypass this check.
        $this->authorizeContractParty($contract);

        $statusKey = $this->mapContractStatus($this->statusValue($contract->status));
        [$progress, $progressLabel, $progressColor] = $this->progressFor($statusKey, $contract);

        $currency = $contract->currency ?: 'AED';
        $totalAmount = (float) $contract->total_amount;

        $daysRemaining = null;
        if ($contract->end_date) {
            $diff = now()->startOfDay()->diffInDays($contract->end_date->startOfDay(), false);
            $daysRemaining = (int) max(0, $diff);
        }

        // Resolve party companies + their primary contact user.
        $partyCompanyIds = collect($contract->parties ?? [])->pluck('company_id')->filter()->unique();
        $companies = Company::with(['users' => fn ($q) => $q->orderBy('id')])
            ->whereIn('id', $partyCompanyIds)
            ->get()
            ->keyBy('id');

        $signaturesByCompany = collect($contract->signatures ?? [])->keyBy('company_id');

        $parties = collect($contract->parties ?? [])->values()->map(function ($party) use ($companies, $signaturesByCompany) {
            $cid = $party['company_id'] ?? null;
            $company = $cid ? $companies->get($cid) : null;
            $name = $company?->name ?? ($party['name'] ?? '—');
            $role = strtolower($party['role'] ?? '');
            $contactUser = $company?->users->first();
            $contactParts = array_filter([
                $contactUser?->name,
                $contactUser?->email ?? $company?->email,
            ]);

            $signature = $cid ? $signaturesByCompany->get($cid) : null;

            return [
                'code'      => $this->initials($name),
                'color'     => $role === 'buyer' ? 'bg-accent' : 'bg-[#10B981]',
                'name'      => $name,
                'type'      => $role === 'buyer' ? __('contracts.buyer') : __('contracts.supplier'),
                'contact'   => implode(' · ', $contactParts),
                'signed'    => (bool) $signature,
                'signed_on' => $signature ? $this->longDate($signature['signed_at'] ?? null) : null,
            ];
        })->all();

        // Map payment_schedule entries to display milestones, matching against actual Payment rows.
        $payments = $contract->payments;
        $milestones = collect($contract->payment_schedule ?? [])->map(function ($entry) use ($payments, $currency, $contract) {
            $key = strtolower((string) ($entry['milestone'] ?? ''));
            $percentage = (int) ($entry['percentage'] ?? 0);
            $amount = (float) ($entry['amount'] ?? 0);

            $payment = $payments->first(function ($p) use ($key) {
                return $key !== '' && str_contains(strtolower((string) $p->milestone), $key);
            });

            $statusValue = $payment ? $this->statusValue($payment->status) : null;
            $isPaid = in_array($statusValue, ['completed', 'paid'], true);
            $isPending = $payment && !$isPaid;

            return [
                'name'       => $this->milestoneName($key),
                'percentage' => $percentage,
                'amount'     => $this->money($amount, $currency),
                'status'     => $isPaid ? 'paid' : ($isPending ? 'pending' : 'future'),
                'due_date'   => $this->longDate($payment?->approved_at ?? $contract->end_date),
                'paid_date'  => $isPaid ? $this->longDate($payment->approved_at ?? $payment->updated_at) : null,
                'payment_id' => $isPending ? $payment->id : null,
            ];
        })->all();

        // Terms: support a JSON-structured terms field, or fall back to plain text split into bullets.
        $termsSections = $this->parseTermsSections($contract->terms);

        // Timeline derived from real contract events.
        $timeline = $this->buildTimeline($contract, $payments);

        // Documents: contract amendments + versions surface as document entries.
        $documents = $this->buildDocuments($contract);

        // Sign-button gating: the current user can sign iff their company is
        // a party of the contract that hasn't already signed, the contract is
        // still in a signable state, and they hold contract.sign permission.
        $user = auth()->user();
        $signableStatuses = [ContractStatus::DRAFT->value, ContractStatus::PENDING_SIGNATURES->value];
        $userCompanyIsParty = $user && in_array(
            $user->company_id,
            collect($contract->parties ?? [])->pluck('company_id')->all(),
            true
        );
        $userCompanyAlreadySigned = $signaturesByCompany->has($user?->company_id);
        $canSign = $user
            && $user->hasPermission('contract.sign')
            && $userCompanyIsParty
            && !$userCompanyAlreadySigned
            && in_array($this->statusValue($contract->status), $signableStatuses, true);

        // Supplier-side detail view renders a different action panel
        // (Update Progress / Upload Documents / Schedule Shipment) while
        // the buyer gets the sign-and-review layout.
        $role = $user?->role?->value ?? 'buyer';
        $isSupplier = in_array($role, ['supplier', 'service_provider', 'logistics', 'clearance'], true);

        // Buyer card for the supplier-side sidebar.
        $buyerCompanyId = $contract->buyer_company_id;
        $buyerCompany = $buyerCompanyId
            ? Company::with(['users' => fn ($q) => $q->orderBy('id')])->find($buyerCompanyId)
            : null;
        $buyerContact = [
            'name'  => $buyerCompany?->name ?? '—',
            'email' => $buyerCompany?->email ?? $buyerCompany?->users->first()?->email ?? '—',
            'phone' => $buyerCompany?->phone ?? '—',
        ];

        // Reusable payment schedule for the <x-payment-schedule> component.
        // Reads the contract's `payment_schedule` JSON (the same structure
        // bids store under that column) and resolves each row to a labelled
        // milestone + percentage + formatted amount + display "stage".
        $paymentSchedule = collect($contract->payment_schedule ?? [])->map(function ($entry) use ($currency, $totalAmount) {
            $pct  = (float) ($entry['percentage'] ?? 0);
            $amt  = (float) ($entry['amount'] ?? round($totalAmount * $pct / 100, 2));
            $name = $this->milestoneName(strtolower((string) ($entry['milestone'] ?? '')));
            $n = strtolower($name);
            $stage = match (true) {
                str_contains($n, 'advance')                                    => 'advance',
                str_contains($n, 'production')                                 => 'production',
                str_contains($n, 'deliver') || str_contains($n, 'shipment')    => 'delivery',
                str_contains($n, 'final') || str_contains($n, 'settlement')    => 'final',
                default                                                        => 'milestone',
            };
            return [
                'milestone'  => $name,
                'percentage' => $pct,
                'amount'     => $this->money($amt, $currency),
                'stage'      => $stage,
            ];
        })->values()->all();

        // Supplier-uploaded documents (production photos, QC certs, etc.).
        // Each entry is `{name, path, size, mime, uploaded_at, uploaded_by}`;
        // we resolve a download URL that streams via downloadDocument().
        $supplierDocs = collect($contract->supplier_documents ?? [])->values()->map(function ($doc, $idx) use ($contract) {
            $name = is_array($doc) ? ($doc['name'] ?? 'document') : (string) $doc;
            $size = is_array($doc) ? ($doc['size'] ?? null) : null;
            return [
                'name'        => $name,
                'type'        => strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                'size'        => $size ? (is_numeric($size) ? round($size / 1024 / 1024, 1) . ' MB' : $size) : '—',
                'uploaded_at' => is_array($doc) && isset($doc['uploaded_at'])
                    ? \Carbon\Carbon::parse($doc['uploaded_at'])->format('M j, Y')
                    : '—',
                'url'         => route('dashboard.contracts.documents.download', ['id' => $contract->id, 'idx' => $idx]),
            ];
        })->all();

        // Progress update log — the supplier's `{at, by, percent, note}` entries
        // rendered as a human-readable timeline.
        $progressLog = collect($contract->progress_updates ?? [])->reverse()->values()->map(function ($entry) {
            return [
                'percent' => (int) ($entry['percent'] ?? 0),
                'note'    => $entry['note'] ?? null,
                'when'    => isset($entry['at']) ? \Carbon\Carbon::parse($entry['at'])->diffForHumans() : '—',
            ];
        })->all();

        // Feedback: resolve the current user's existing review (if any) so the
        // UI can switch between "leave a review" and "your review" states.
        // Only applies once the contract is completed.
        $canReview = $statusKey === 'completed'
            && ($user->company_id === $contract->buyer_company_id
                || in_array($user->company_id, collect($contract->parties ?? [])->pluck('company_id')->all(), true));
        $existingReview = null;
        if ($canReview) {
            $existingReview = \App\Models\Feedback::where('contract_id', $contract->id)
                ->where('rater_company_id', $user->company_id)
                ->first();
        }

        // Phase 3 — escrow panel data. Empty when escrow hasn't been
        // activated yet (most contracts pre-Phase-3); the view checks
        // `escrow.activated` before rendering the panel.
        $escrowPanel = $this->buildEscrowPanel($contract, $user);

        $data = [
            'id'              => $contract->contract_number,
            'numeric_id'      => $contract->id,
            // Phase 4 / Sprint 18 — surfaced for the "Buy Again" button so
            // the view can gate it on (current user company == buyer).
            'buyer_company_id' => $contract->buyer_company_id,
            'escrow'          => $escrowPanel,
            'title'           => $contract->title,
            'status'          => $statusKey,
            'amount'          => $this->money($totalAmount, $currency),
            'progress'        => $progress,
            'progress_label'  => $progressLabel,
            'days_remaining'  => $daysRemaining,
            'parties'         => $parties,
            'milestones'      => $milestones,
            'payment_schedule'=> $paymentSchedule,
            'terms_sections'  => $termsSections,
            'timeline'        => $timeline,
            'documents'       => $documents,
            'supplier_documents' => $supplierDocs,
            'progress_log'    => $progressLog,
            'has_shipment'    => $contract->shipments->isNotEmpty(),
            'shipment_id'     => $contract->shipments->first()?->tracking_number,
            'can_sign'        => $canSign,
            'can_review'      => $canReview,
            'existing_review' => $existingReview ? [
                'rating'  => $existingReview->rating,
                'comment' => $existingReview->comment,
            ] : null,
            'start_date'      => $this->longDate($contract->start_date ?? $contract->created_at),
            'end_date'        => $this->longDate($contract->end_date),
            'buyer_contact'   => $buyerContact,
            'total_amount'    => $this->money($totalAmount, $currency),
            // Status is an enum cast — use statusValue() helper. Sum total_amount
            // (amount + VAT) so "Received" reflects what was actually transferred.
            'paid_amount'     => $this->money(
                (float) $payments->filter(fn ($p) => in_array($this->statusValue($p->status), ['completed', 'paid'], true))->sum('total_amount'),
                $currency
            ),
            'pending_amount'  => $this->money(
                $totalAmount - (float) $payments->filter(fn ($p) => in_array($this->statusValue($p->status), ['completed', 'paid'], true))->sum('total_amount'),
                $currency
            ),
        ];

        if ($isSupplier) {
            return view('dashboard.contracts.show-supplier', ['contract' => $data]);
        }

        return view('dashboard.contracts.show', ['contract' => $data]);
    }

    public function pdf(string $id): Response
    {
        abort_unless(auth()->user()?->hasPermission('contract.pdf'), 403);

        $contract = $this->findOrFail($id)
            ->load(['buyerCompany', 'purchaseRequest', 'payments', 'shipments']);

        // Authorize: only parties of the contract may download.
        $userCompanyId = auth()->user()?->company_id;
        $partyCompanyIds = collect($contract->parties ?? [])->pluck('company_id')->push($contract->buyer_company_id)->filter()->all();
        abort_unless($userCompanyId && in_array($userCompanyId, $partyCompanyIds, true), 403);

        // Resolve the supplier party (the second party of the contract) so the
        // PDF template can render its full registration / TRN / address block.
        // Same lookup pattern is used in the API controller — keep them in
        // sync if you change one.
        $supplierCompanyId = collect($contract->parties ?? [])
            ->firstWhere('role', 'supplier')['company_id'] ?? null;
        $supplierCompany = $supplierCompanyId ? Company::find($supplierCompanyId) : null;

        $pdf = Pdf::loadView('contracts.pdf', [
            'contract'        => $contract,
            'buyerCompany'    => $contract->buyerCompany,
            'supplierCompany' => $supplierCompany,
        ]);

        return $pdf->download($contract->contract_number . '.pdf');
    }

    public function sign(string $id): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.sign'), 403, 'Forbidden: missing contracts.sign permission.');
        // Defence in depth: ContractService::sign() also rejects non-party
        // companies, but we hard-fail here too so a non-party never even
        // reaches the service call.
        $this->authorizeContractParty($contract);

        $result = $this->service->sign(
            id: $contract->id,
            userId: $user->id,
            companyId: $user->company_id,
        );

        if (is_string($result)) {
            return back()->withErrors(['contract' => $result]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.signed_successfully'));
    }

    /**
     * Supplier records a production progress update. Appends an entry to the
     * `progress_updates` JSON log and updates `progress_percentage` so the
     * buyer sees it immediately on their contract detail page.
     *
     * Authorization: only a party of the contract whose company is NOT the
     * buyer (i.e. the supplier side) can update.
     */
    public function updateProgress(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeSupplierParty($contract, $user);

        $validated = $request->validate([
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'note'                => ['nullable', 'string', 'max:500'],
        ]);

        $log = $contract->progress_updates ?? [];
        $log[] = [
            'at'      => now()->toIso8601String(),
            'by'      => $user->id,
            'percent' => (int) $validated['progress_percentage'],
            'note'    => $validated['note'] ?? null,
        ];

        $contract->update([
            'progress_percentage' => (int) $validated['progress_percentage'],
            'progress_updates'    => $log,
        ]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.progress_updated') ?? 'Progress updated.');
    }

    /**
     * Supplier uploads production documents (photos, QC certs, delivery
     * receipts). Files are stored on the private `local` disk; the URL
     * returned streams back through `downloadDocument()`.
     */
    public function uploadDocuments(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeSupplierParty($contract, $user);

        $request->validate([
            'documents'   => ['required', 'array', 'max:10'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $existing = $contract->supplier_documents ?? [];
        foreach ($request->file('documents') as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }
            $path = $file->store("contract-documents/{$contract->id}", 'local');
            $existing[] = [
                'name'        => $file->getClientOriginalName(),
                'path'        => $path,
                'size'        => $file->getSize(),
                'mime'        => $file->getClientMimeType(),
                'uploaded_at' => now()->toIso8601String(),
                'uploaded_by' => $user->id,
            ];
        }

        $contract->update(['supplier_documents' => $existing]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.documents_uploaded') ?? 'Documents uploaded.');
    }

    /**
     * Stream a supplier-uploaded contract document back to an authorized
     * party of the contract (buyer OR supplier).
     */
    public function downloadDocument(string $id, int $idx): StreamedResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();
        abort_unless($user?->hasPermission('contract.view'), 403);

        // Authorize: user's company must be the buyer OR a party in `parties`.
        $isBuyer = $user->company_id === $contract->buyer_company_id;
        $partyIds = collect($contract->parties ?? [])->pluck('company_id')->all();
        $isParty = in_array($user->company_id, $partyIds, true);
        abort_unless($isBuyer || $isParty, 403);

        $docs  = (array) ($contract->supplier_documents ?? []);
        $entry = $docs[$idx] ?? null;
        abort_unless(is_array($entry) && isset($entry['path']), 404);
        abort_unless(Storage::disk('local')->exists($entry['path']), 404);

        return Storage::disk('local')->download(
            $entry['path'],
            $entry['name'] ?? basename($entry['path'])
        );
    }

    /**
     * Supplier schedules a shipment for this contract. Creates a Shipment
     * row with the supplied tracking info and marks it IN_PRODUCTION. Further
     * status updates happen through the shipment detail page.
     */
    public function scheduleShipment(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('shipment.view'), 403);
        $this->authorizeSupplierParty($contract, $user);

        $validated = $request->validate([
            'tracking_number'     => ['nullable', 'string', 'max:100'],
            'carrier'             => ['nullable', 'string', 'max:100'],
            'origin'              => ['nullable', 'string', 'max:200'],
            'destination'         => ['nullable', 'string', 'max:200'],
            'estimated_delivery'  => ['nullable', 'date', 'after:today'],
        ]);

        // `origin`/`destination` are JSON columns on shipments — wrap the
        // free-form string the form posts so the cast doesn't choke.
        $shipment = Shipment::create([
            'tracking_number'    => $validated['tracking_number'] ?? ('SHP-' . strtoupper(uniqid())),
            'contract_id'        => $contract->id,
            'company_id'         => $user->company_id,
            'status'             => ShipmentStatus::IN_PRODUCTION->value,
            'origin'             => $validated['origin'] ? ['text' => $validated['origin']] : null,
            'destination'        => $validated['destination'] ? ['text' => $validated['destination']] : null,
            'estimated_delivery' => $validated['estimated_delivery'] ?? null,
            'notes'              => $validated['carrier'] ? 'Carrier: ' . $validated['carrier'] : null,
        ]);

        return redirect()
            ->route('dashboard.shipments.show', ['id' => $shipment->id])
            ->with('status', __('contracts.shipment_scheduled') ?? 'Shipment scheduled.');
    }

    /**
     * Guard: assert that the given user is on the SUPPLIER side of the
     * contract — their company is in `parties` but is NOT the buyer.
     */
    private function authorizeSupplierParty(Contract $contract, $user): void
    {
        $partyIds = collect($contract->parties ?? [])->pluck('company_id')->all();
        $isParty  = in_array($user->company_id, $partyIds, true);
        $isBuyer  = $user->company_id === $contract->buyer_company_id;
        abort_unless($isParty && !$isBuyer, 403, 'Only the supplier side may perform this action.');
    }

    private function findOrFail(string $id): Contract
    {
        $query = Contract::query();

        if (str_starts_with($id, 'CTR-') || str_starts_with($id, 'CNT-')) {
            return $query->where('contract_number', $id)->firstOrFail();
        }

        return $query->findOrFail((int) $id);
    }

    /**
     * Authorize that the current user belongs to a company that is a
     * party of the contract — buyer company OR an entry in the parties
     * JSON column. Admin and government users always pass. Aborts with
     * a 404 (not 403) so id enumeration can't distinguish "doesn't
     * exist" from "exists but you can't see it".
     */
    private function authorizeContractParty(Contract $contract): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(404);
        }
        if ($user->isAdmin() || $user->isGovernment()) {
            return;
        }

        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (!in_array($user->company_id, $partyCompanyIds, true)) {
            abort(404);
        }
    }

    private function supplierName(Contract $c, $companyNames): string
    {
        foreach ($c->parties ?? [] as $party) {
            $cid = $party['company_id'] ?? null;
            if ($cid && $cid !== $c->buyer_company_id && isset($companyNames[$cid])) {
                return $companyNames[$cid];
            }
        }

        return '—';
    }

    private function mapContractStatus(string $status): string
    {
        return match ($status) {
            'draft', 'pending_signatures' => 'pending',
            'signed', 'active'            => 'active',
            'completed'                   => 'completed',
            'terminated', 'cancelled'     => 'closed',
            default                       => 'pending',
        };
    }

    /**
     * Resolve [progress%, label, color] for a contract row in a list/show
     * page. The progress percentage comes from the model's realProgress()
     * helper when a Contract instance is passed (which respects the
     * supplier's manual overrides + paid milestone fraction). When only a
     * status string is available the percentage falls back to the rough
     * status defaults — used by the legacy `avg_progress` calculation that
     * doesn't have a Contract handle.
     *
     * @return array{0:int,1:string,2:string} [progress%, label, color]
     */
    private function progressFor(string $statusKey, ?Contract $contract = null): array
    {
        $label = match ($statusKey) {
            'completed' => __('status.completed'),
            'active'    => __('contracts.in_production'),
            'pending'   => __('status.pending'),
            'closed'    => __('status.closed'),
            default     => '',
        };

        $color = match ($statusKey) {
            'completed' => '#10B981',
            'active'    => '#3B82F6',
            'pending'   => '#F59E0B',
            default     => '#6B7280',
        };

        // Real progress from the model when we have it (preferred path).
        if ($contract !== null) {
            return [$contract->realProgress(), $label, $color];
        }

        // Status-only fallback for callers that don't have the Contract.
        $progress = match ($statusKey) {
            'completed' => 100,
            'active'    => 50,
            'pending'   => 5,
            'closed'    => 0,
            default     => 0,
        };

        return [$progress, $label, $color];
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '—';
        }
        $parts = preg_split('/[\s\-]+/u', $name) ?: [];
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
        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($name, 0, 2));
    }

    private function milestoneName(string $key): string
    {
        return match ($key) {
            'advance'  => __('contracts.advance_payment'),
            'delivery' => __('contracts.delivery_payment'),
            'production', 'production_completion' => __('contracts.production_completion'),
            'final', 'final_settlement' => __('contracts.final_settlement'),
            default => $key === '' ? __('contracts.milestone') : ucwords(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @return array<int, array{title:string, items: array<int,string>}>
     */
    private function parseTermsSections($terms): array
    {
        if (is_array($terms)) {
            return collect($terms)->map(function ($section) {
                return [
                    'title' => (string) ($section['title'] ?? ''),
                    'items' => array_values(array_filter((array) ($section['items'] ?? []))),
                ];
            })->all();
        }

        if (is_string($terms) && trim($terms) !== '') {
            $decoded = json_decode($terms, true);
            if (is_array($decoded)) {
                return $this->parseTermsSections($decoded);
            }

            $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $terms))));
            if (empty($lines)) {
                return [];
            }
            return [[
                'title' => __('contracts.terms_conditions'),
                'items' => $lines,
            ]];
        }

        return [];
    }

    /**
     * @return array<int, array{done:bool, date:string, title:string, desc:string}>
     */
    private function buildTimeline(Contract $contract, $payments): array
    {
        $events = [];

        $events[] = [
            'done'  => true,
            'date'  => $this->longDate($contract->created_at),
            'title' => __('contracts.timeline_created'),
            'desc'  => __('contracts.timeline_created_desc'),
        ];

        $signatures = collect($contract->signatures ?? []);
        if ($signatures->isNotEmpty()) {
            $lastSigned = $signatures->pluck('signed_at')->filter()->sort()->last();
            $allSigned = $contract->allPartiesHaveSigned();
            $events[] = [
                'done'  => $allSigned,
                'date'  => $this->longDate($lastSigned),
                'title' => __('contracts.timeline_signed'),
                'desc'  => __('contracts.timeline_signed_desc'),
            ];
        }

        foreach ($payments as $payment) {
            $statusValue = $this->statusValue($payment->status);
            $isPaid = in_array($statusValue, ['completed', 'paid'], true);
            $events[] = [
                'done'  => $isPaid,
                'date'  => $this->longDate($payment->approved_at ?? $payment->updated_at),
                'title' => $payment->milestone ?: __('contracts.payment_milestones'),
                'desc'  => $isPaid
                    ? __('contracts.timeline_payment_paid', ['amount' => $this->money((float) $payment->amount, $payment->currency ?? 'AED')])
                    : __('contracts.timeline_payment_pending', ['amount' => $this->money((float) $payment->amount, $payment->currency ?? 'AED')]),
            ];
        }

        if ($contract->end_date) {
            $events[] = [
                'done'  => $contract->end_date->isPast(),
                'date'  => $this->longDate($contract->end_date),
                'title' => __('contracts.timeline_final_delivery'),
                'desc'  => __('contracts.timeline_final_delivery_desc'),
            ];
        }

        return $events;
    }

    /**
     * @return array<int, array{name:string, url:?string}>
     */
    private function buildDocuments(Contract $contract): array
    {
        $documents = [
            [
                'name' => $contract->contract_number . '.pdf',
                'url'  => route('dashboard.contracts.pdf', ['id' => $contract->id]),
            ],
        ];

        foreach ($contract->amendments ?? [] as $amendment) {
            $documents[] = [
                'name' => __('contracts.amendment') . ' #' . $amendment->id . '.pdf',
                'url'  => null,
            ];
        }

        return $documents;
    }

    /**
     * Phase 3 — assemble the escrow sidebar panel data so the view stays
     * dumb. Returns a flat array of strings + booleans the Blade template
     * binds directly. When escrow has not been activated yet the panel
     * still renders to show the "Activate Escrow" CTA, but `activated`
     * is false so the deposit / release UI stays hidden.
     */
    private function buildEscrowPanel(Contract $contract, $user): array
    {
        $isBuyer = $user && $user->company_id === $contract->buyer_company_id;
        $canActivate = $isBuyer
            && $user?->hasPermission('escrow.activate')
            && in_array($this->statusValue($contract->status), ['active', 'signed', 'pending_signatures'], true);

        $account = $contract->escrowAccount;
        $currency = $account?->currency ?? $contract->currency ?? 'AED';

        if (!$account) {
            return [
                'activated'    => false,
                'can_activate' => $canActivate,
                'currency'     => $currency,
            ];
        }

        $available = $account->availableBalance();
        $deposited = (float) $account->total_deposited;
        $released  = (float) $account->total_released;
        $expected  = (float) $contract->total_amount;

        // Recent ledger entries — most recent 6 events for the sidebar.
        // The full ledger lives on the escrow dashboard.
        $recentReleases = $account->releases
            ->sortByDesc('recorded_at')
            ->take(6)
            ->map(function (\App\Models\EscrowRelease $r) use ($currency) {
                return [
                    'id'        => $r->id,
                    'type'      => $r->type,
                    'amount'    => $this->money((float) $r->amount, $r->currency ?: $currency),
                    'milestone' => $r->milestone,
                    'trigger'   => $r->triggered_by,
                    'when'      => $r->recorded_at?->diffForHumans() ?? '—',
                    'reference' => $r->bank_reference,
                    'notes'     => $r->notes,
                ];
            })
            ->values()
            ->all();

        return [
            'activated'        => true,
            'can_activate'     => false,
            'can_deposit'      => $isBuyer && $user?->hasPermission('escrow.deposit') && $account->isActive(),
            'can_release'      => $isBuyer && $user?->hasPermission('escrow.release') && $account->isActive() && $available > 0,
            'can_refund'       => $isBuyer && $user?->hasPermission('escrow.release') && $account->isActive() && $available > 0,
            'status'           => $account->status,
            'bank_partner'     => $account->bank_partner,
            'external_id'      => $account->external_account_id,
            'currency'         => $currency,
            'expected'         => $this->money($expected, $currency),
            'deposited'        => $this->money($deposited, $currency),
            'released'         => $this->money($released, $currency),
            'available'        => $this->money($available, $currency),
            'available_raw'    => $available,
            'progress'         => $expected > 0
                ? min(100, (int) round(($deposited / $expected) * 100))
                : 0,
            'release_progress' => $deposited > 0
                ? min(100, (int) round(($released / $deposited) * 100))
                : 0,
            'recent_events'    => $recentReleases,
            'unpaid_payments'  => $contract->payments
                ->whereNotIn('status', ['completed', 'refunded', 'cancelled'])
                ->map(fn ($p) => [
                    'id'        => $p->id,
                    'milestone' => $p->milestone ?: __('contracts.milestone'),
                    'amount'    => $this->money((float) $p->total_amount, $p->currency ?? $currency),
                    'amount_raw'=> (float) $p->total_amount,
                ])
                ->values()
                ->all(),
        ];
    }

    private function shortMoney(float $value, string $currency = 'AED'): string
    {
        if ($value >= 1_000_000) {
            return $currency . ' ' . round($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return $currency . ' ' . round($value / 1_000) . 'K';
        }

        return $currency . ' ' . number_format($value);
    }
}
