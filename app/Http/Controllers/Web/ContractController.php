<?php

namespace App\Http\Controllers\Web;

use App\Enums\AmendmentStatus;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractAmendmentMessage;
use App\Models\ContractVersion;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ContractAmendmentDecidedNotification;
use App\Notifications\ContractAmendmentMessageNotification;
use App\Notifications\ContractAmendmentProposedNotification;
use App\Services\ContractService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

        $companyId  = $this->currentCompanyId();
        // Drives the buyer-vs-supplier query branch — see
        // FormatsForViews::isSupplierSideUser() for the role + company-type
        // dispatch rules.
        $isSupplier = $this->isSupplierSideUser();

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
        // The helper checks both the user's role AND the company TYPE so a
        // company_manager / finance / sales of a supplier company is correctly
        // routed to the supplier branch — the previous role-only check left
        // those users on an empty buyer index.
        $isSupplier = $this->isSupplierSideUser();

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
        // Paginate so a tenant with 500+ contracts doesn't blow the
        // request memory cap on a single GET — the previous ->get()
        // path materialised every contract on every page load.
        $perPage = 15;
        $paginator    = (clone $base)->with('payments')->paginate($perPage)->withQueryString();
        $contractRows = $paginator->getCollection();

        $partyCompanyIds = $contractRows
            ->flatMap(fn (Contract $c) => collect($c->parties ?? [])->pluck('company_id'))
            ->push(...$contractRows->pluck('buyer_company_id'))
            ->filter()
            ->unique()
            ->values();

        $companyNames = Company::whereIn('id', $partyCompanyIds)->pluck('name', 'id');

        if ($isSupplier) {
            return $this->supplierIndex($contractRows, $companyNames, $stats, $totalValue, $paginator);
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

        return view('dashboard.contracts.index', compact('stats', 'contracts', 'statusFilter', 'sort', 'paginator'));
    }

    /**
     * Supplier-side "My Contracts" — same query, different framing. Splits
     * contracts by status into Active / Completed tabs, computes the average
     * progress KPI, and shows buyer names + pending payments instead of a
     * supplier column.
     */
    private function supplierIndex($contractRows, $companyNames, array $stats, float $totalValue, $paginator = null): View
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

        // The "active" stat card on the supplier index has historically
        // been fed from the parent stats array, which counts only rows
        // with status === ACTIVE. That number disagreed with the active
        // TAB on the same page (which buckets every non-completed
        // contract — draft, pending, signed, active — as "active"). The
        // KPI now counts the actual content of the active bucket so
        // the card and the tab agree.
        return view('dashboard.contracts.index-supplier', [
            'stats' => [
                'active'       => $activeContracts->count(),
                'completed'    => $completedContracts->count(),
                'total_value'  => $this->money($totalValue, 'AED'),
                'avg_progress' => $avgProgress,
            ],
            'active_contracts'    => $activeContracts->all(),
            'completed_contracts' => $completedContracts->all(),
            'paginator'           => $paginator,
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

            // Resolve jurisdiction label from the company's enum
            // (FEDERAL / DIFC / ADGM) so the signature block makes the
            // legal seat of the party crystal clear at a glance — UAE
            // contracts in DIFC vs Federal vs ADGM apply different
            // enforcement and conflict-of-laws rules.
            $jurisdiction = null;
            if ($company?->legal_jurisdiction instanceof \BackedEnum) {
                $jurisdiction = strtoupper($company->legal_jurisdiction->value);
            }

            return [
                'code'           => $this->initials($name),
                'color'          => $role === 'buyer' ? 'bg-accent' : 'bg-[#10B981]',
                'name'           => $name,
                'type'           => $role === 'buyer' ? __('contracts.buyer') : __('contracts.supplier'),
                'contact'        => implode(' · ', $contactParts),
                'signed'         => (bool) $signature,
                'signed_on'      => $signature ? $this->longDate($signature['signed_at'] ?? null) : null,
                // Phase 4 / Sprint 18 — surface the legal identity of
                // the party so a reviewer doesn't need to drill into
                // the company profile to confirm TRN, registration #,
                // address, and jurisdiction. Each is null-safe so
                // legacy or partially-filled companies still render.
                'trn'            => $company?->tax_number ?: null,
                'registration'   => $company?->registration_number ?: null,
                'jurisdiction'   => $jurisdiction,
                'address'        => $company?->address ?: null,
                'country'        => $company?->country ?: null,
                // Audit metadata captured at sign time. The view
                // displays IP/device/hash inside an expandable details
                // strip so the reader can verify the e-signature meets
                // Federal Decree-Law 46/2021 evidentiary standards.
                'sig_audit'      => $signature ? [
                    'ip'         => $signature['ip_address']  ?? null,
                    'user_agent' => $signature['user_agent']  ?? null,
                    'hash'       => $signature['contract_hash'] ?? null,
                    'consent_at' => $signature['consent_at']  ?? null,
                ] : null,
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

        // Terms: bilingual contracts pull the current-locale slice straight
        // out of the stored envelope; legacy single-locale contracts get a
        // fresh regeneration via the service so the dashboard view always
        // matches the user's UI language. Same logic the PDF download uses.
        if ($this->termsAreBilingual($contract->terms)) {
            $termsSections = $this->parseTermsSections($contract->terms, app()->getLocale());
        } else {
            $termsSections = $this->service->regenerateTermsForLocale($contract, app()->getLocale());
        }

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
        // the buyer gets the sign-and-review layout. The helper looks at
        // both role AND company type so cross-cutting roles attached to a
        // supplier company also land on the supplier-side panel.
        $isSupplier = $this->isSupplierSideUser();

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

        // Bilateral clause amendments — proposals to modify or add a clause
        // to the contract terms. Each amendment carries a `requested_by`
        // user; the OTHER party can approve or reject. Amendments are
        // STRICTLY pre-signature: once both parties have signed and the
        // contract becomes ACTIVE, the terms are locked. The business rule
        // is that any change to the wording must be agreed in writing
        // BEFORE the e-signature is collected — afterwards, the only path
        // to amend a contract is to terminate and re-issue.
        $amendmentRecords = ContractAmendment::where('contract_id', $contract->id)
            ->with(['requestedBy', 'messages.user'])
            ->latest()
            ->get();

        $partyCompanyIdsAll = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();
        $userIsParty = $user && in_array($user->company_id, $partyCompanyIdsAll, true);

        // Pre-signature window only: DRAFT or PENDING_SIGNATURES, AND no
        // single party has fully signed yet. allPartiesHaveSigned() should
        // never return true here in practice (the service flips status to
        // ACTIVE the moment that happens), but we double-check defensively.
        $preSignature = in_array(
            $this->statusValue($contract->status),
            [ContractStatus::DRAFT->value, ContractStatus::PENDING_SIGNATURES->value],
            true
        ) && !$contract->allPartiesHaveSigned();

        $canAmend = $user
            && $user->hasPermission('contract.view')
            && $userIsParty
            && $preSignature;

        $amendments = $amendmentRecords->map(function (ContractAmendment $a) use ($user) {
            $changes      = $a->changes ?? [];
            $proposerUser = $a->requestedBy;
            $proposerCo   = $proposerUser?->company_id;
            $isMine       = $user && $proposerCo === $user->company_id;
            $statusValue  = $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status;

            // Negotiation thread bubbles. Each message is grouped to
            // either 'mine' (current user's company) or 'theirs' so the
            // view can render left/right bubbles without re-resolving
            // the user → company link on every render.
            $messages = $a->messages->map(function (ContractAmendmentMessage $m) use ($user) {
                return [
                    'id'        => $m->id,
                    'body'      => $m->body,
                    'author'    => $m->user?->name ?? '—',
                    'when'      => $m->created_at?->diffForHumans() ?? '—',
                    'is_mine'   => $user && (int) $m->company_id === (int) $user->company_id,
                ];
            })->all();

            return [
                'id'             => $a->id,
                'kind'           => $changes['kind'] ?? 'modify',
                'section_index'  => (int) ($changes['section_index'] ?? 0),
                'section_title'  => (string) ($changes['section_title'] ?? ''),
                'item_index'     => $changes['item_index'] ?? null,
                'old_text'       => $changes['old_text'] ?? null,
                'new_text'       => (string) ($changes['new_text'] ?? ''),
                'reason'         => $a->reason,
                'status'         => $statusValue,
                'is_pending'     => $statusValue === AmendmentStatus::PENDING_APPROVAL->value,
                'proposed_by'    => $proposerUser?->name ?? '—',
                'proposed_by_me' => $isMine,
                'proposed_at'    => $a->created_at?->diffForHumans() ?? '—',
                // Counter-party can only approve / reject if pending AND not the proposer's company.
                'can_decide'     => $statusValue === AmendmentStatus::PENDING_APPROVAL->value
                    && $user
                    && $proposerCo
                    && $proposerCo !== $user->company_id,
                // Discussion thread for this clause amendment.
                'messages'       => $messages,
                'message_count'  => count($messages),
                // Either party can post a message any time before the
                // contract is fully signed (so the conversation can
                // continue right up until the moment of signature).
                'can_message'    => $user && $userIsParty,
            ];
        })->all();

        // Line items for the supplier "Items" tab. The contract may
        // carry them in `amounts.line_items` (cart-sourced contracts)
        // or be a single line whose unit_price + quantity live at the
        // top level of the amounts JSON (Buy-Now contracts). Bid-driven
        // contracts have neither — they get a synthetic single line
        // built from the contract title + total so the tab still
        // renders something useful instead of an empty stub.
        $lineItems = collect($contract->amounts['line_items'] ?? [])
            ->map(function ($item) use ($currency) {
                $qty       = (float) ($item['quantity']  ?? 1);
                $unit      = (float) ($item['unit_price'] ?? 0);
                $lineTotal = (float) ($item['total']     ?? round($qty * $unit, 2));
                return [
                    'name'       => (string) ($item['name'] ?? '—'),
                    'sku'        => (string) ($item['sku']  ?? ''),
                    'qty'        => $qty,
                    'unit'       => (string) ($item['unit_of_measure'] ?? ''),
                    'unit_price' => $this->money($unit, $currency),
                    'total'      => $this->money($lineTotal, $currency),
                ];
            })
            ->all();
        if (empty($lineItems)) {
            // Buy-Now or single-line contract — synthesise one row.
            $unit = (float) ($contract->amounts['unit_price'] ?? $contract->amounts['subtotal'] ?? $contract->total_amount);
            $qty  = (float) ($contract->amounts['quantity']   ?? 1);
            $lineItems = [[
                'name'       => $contract->title,
                'sku'        => '',
                'qty'        => $qty,
                'unit'       => '',
                'unit_price' => $this->money($qty > 0 ? $unit / $qty : $unit, $currency),
                'total'      => $this->money((float) $contract->total_amount, $currency),
            ]];
        }

        // Payment history for the "Payments" tab — every Payment row
        // attached to the contract with its status, amount, milestone,
        // and the date it was paid (or pending due date). The supplier
        // tab renders this as a chronological table; the buyer tab
        // shows it inside the existing Milestones card.
        $paymentsHistory = $payments
            ->sortBy(fn ($p) => $p->approved_at ?? $p->created_at)
            ->values()
            ->map(function ($p) use ($currency) {
                $statusValue = $this->statusValue($p->status);
                return [
                    'id'         => $p->id,
                    'milestone'  => $p->milestone ?: __('contracts.milestone'),
                    'amount'     => $this->money((float) $p->total_amount, $p->currency ?: $currency),
                    'status'     => $statusValue,
                    'is_paid'    => in_array($statusValue, ['completed', 'paid'], true),
                    'date'       => $this->longDate($p->approved_at ?? $p->updated_at ?? $p->created_at),
                    'invoice_url'=> route('dashboard.payments.show', ['id' => $p->id]),
                ];
            })
            ->all();

        // Signature/stamp gating for the sign button. The buyer's own
        // company must have uploaded both an authorised signature image
        // AND a company stamp before the e-signature can be collected.
        // The view uses these flags to swap the "Sign" button for an
        // "Upload signature & stamp" CTA when the assets are missing.
        $userCompany = $user?->company_id ? Company::find($user->company_id) : null;
        $signatureAssets = [
            'has_signature' => (bool) $userCompany?->signature_path,
            'has_stamp'     => (bool) $userCompany?->stamp_path,
            'has_both'      => (bool) $userCompany?->hasSignatureAssets(),
            'signature_url' => $userCompany?->signatureUrl(),
            'stamp_url'     => $userCompany?->stampUrl(),
        ];
        // The "ready to sign" flag combines the existing signable check
        // with the new asset gate so the button is only enabled when
        // BOTH conditions are true. The view still falls back to the
        // upload modal CTA when can_sign is true but assets are missing.
        $needsSignatureAssets = $canSign && !$signatureAssets['has_both'];

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
            'amendments'      => $amendments,
            'can_amend'       => $canAmend,
            'timeline'        => $timeline,
            'documents'       => $documents,
            'supplier_documents' => $supplierDocs,
            'progress_log'    => $progressLog,
            'has_shipment'    => $contract->shipments->isNotEmpty(),
            'shipment_id'     => $contract->shipments->first()?->tracking_number,
            'can_sign'              => $canSign,
            'needs_signature_assets'=> $needsSignatureAssets,
            'signature_assets'      => $signatureAssets,
            // Decline / terminate gating. Pre-signature with no party
            // signed yet → decline; ACTIVE / SIGNED → terminate. Both
            // surfaced as separate flags so the view can render two
            // distinct buttons (decline is destructive but reversible
            // by re-issuing; terminate is destructive and final).
            'can_decline'           => $userIsParty
                && in_array($this->statusValue($contract->status), [ContractStatus::DRAFT->value, ContractStatus::PENDING_SIGNATURES->value], true)
                && empty($contract->signatures),
            'can_terminate'         => $userIsParty
                && in_array($this->statusValue($contract->status), [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value], true),
            // Line items + payments history surface in the supplier
            // Items / Payments tabs (which were stub placeholders
            // before this commit).
            'line_items'            => $lineItems,
            'payments_history'      => $paymentsHistory,
            'amounts_meta'          => [
                'tax_treatment'     => $contract->amounts['tax_treatment']     ?? null,
                'incoterm'          => $contract->amounts['incoterm']          ?? null,
                'country_of_origin' => $contract->amounts['country_of_origin'] ?? null,
                'hs_code'           => $contract->amounts['hs_code']           ?? null,
            ],
            'can_review'            => $canReview,
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

    public function pdf(string $id, Request $request): Response
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

        // Force the locale for the PDF render so the user can pick which
        // language they want regardless of their UI locale. Per UAE Federal
        // Law 26/1981 the Arabic version is the prevailing legal text, so
        // both versions must be available on demand.
        $requestedLang = $request->query('lang');
        $lang = in_array($requestedLang, ['ar', 'en'], true) ? $requestedLang : app()->getLocale();
        $previousLocale = app()->getLocale();

        try {
            App::setLocale($lang);

            // Resolve clause sections in the requested locale.
            //   - Bilingual contract: pull `terms[$lang]` directly so user
            //     amendments are preserved across both languages.
            //   - Legacy single-locale contract: regenerate the standard
            //     clause set fresh in the requested locale via the service.
            //     We don't have user amendments to merge for legacy rows
            //     because the amendment system itself was added in the same
            //     phase as the bilingual storage.
            if ($this->termsAreBilingual($contract->terms)) {
                $sections = $this->parseTermsSections($contract->terms, $lang);
            } else {
                $sections = $this->service->regenerateTermsForLocale($contract, $lang);
            }

            $pdf = Pdf::loadView('contracts.pdf', [
                'contract'        => $contract,
                'buyerCompany'    => $contract->buyerCompany,
                'supplierCompany' => $supplierCompany,
                'pdfLocale'       => $lang,
                'pdfSections'     => $sections,
            ]);

            // Filename includes a language suffix so the buyer can keep both
            // versions on disk side by side without overwriting each other.
            $filename = $contract->contract_number . '-' . $lang . '.pdf';
            $output   = $pdf->download($filename);
        } finally {
            App::setLocale($previousLocale);
        }

        return $output;
    }

    public function sign(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.sign'), 403, 'Forbidden: missing contracts.sign permission.');
        // Defence in depth: ContractService::sign() also rejects non-party
        // companies, but we hard-fail here too so a non-party never even
        // reaches the service call.
        $this->authorizeContractParty($contract);

        // Block signing while there are pending clause amendments. The
        // business rule is that the wording must be settled — every
        // proposed change either approved or rejected — BEFORE the
        // electronic signature is collected, otherwise a party could sign
        // a version they hadn't agreed to and then have a clause swapped
        // under them.
        $pendingCount = ContractAmendment::where('contract_id', $contract->id)
            ->where('status', AmendmentStatus::PENDING_APPROVAL)
            ->count();
        if ($pendingCount > 0) {
            return back()->withErrors([
                'contract' => __('contracts.sign_blocked_pending_amendments', ['count' => $pendingCount]),
            ]);
        }

        // Defence in depth: refuse to sign until the signing company
        // has uploaded BOTH an authorised signature image AND a stamp.
        // The view hides the sign button when these are missing, but a
        // forged POST would otherwise still slip through. We send the
        // user back to the contract page where the upload modal lives.
        $signerCompany = Company::find($user->company_id);
        if (!$signerCompany || !$signerCompany->hasSignatureAssets()) {
            return back()->withErrors([
                'contract' => __('contracts.sign_blocked_missing_signature_assets'),
            ]);
        }

        // Step-up authentication. UAE Federal Decree-Law 46/2021 requires
        // the signature be uniquely linked to the signatory; an active
        // session alone is not enough — the user must explicitly
        // re-authenticate AT THE MOMENT of signing AND tick a consent
        // checkbox. Both are validated server-side here so a forged form
        // can never slip through.
        $validated = $request->validate([
            'password'    => ['required', 'string'],
            'consent'     => ['required', 'accepted'],
        ], [
            'password.required' => __('contracts.sign_password_required'),
            'consent.accepted'  => __('contracts.sign_consent_required'),
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return back()->withErrors([
                'password' => __('contracts.sign_password_incorrect'),
            ]);
        }

        $result = $this->service->sign(
            id: $contract->id,
            userId: $user->id,
            companyId: $user->company_id,
            signature: null,
            auditContext: [
                'ip_address'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 500),
                'consent_text' => __('contracts.sign_consent_text', [
                    'number' => $contract->contract_number,
                    'amount' => ($contract->currency ?: 'AED') . ' ' . number_format((float) $contract->total_amount, 2),
                ]),
                'consent_at'  => now()->toIso8601String(),
            ],
        );

        if (is_string($result)) {
            return back()->withErrors(['contract' => $result]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.signed_successfully'));
    }

    /**
     * Either party proposes an amendment to the contract terms — modifying
     * an existing clause or adding a new one. The amendment is stored as
     * PENDING_APPROVAL until the OTHER party approves or rejects it.
     *
     * The contract's terms JSON is NOT touched until approval, so the
     * counter-party can keep referencing the original wording while they
     * decide. Once approved, terms are merged, the version increments, and
     * a fresh ContractVersion snapshot is captured for the audit trail.
     */
    public function proposeAmendment(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        // Pre-signature window only — once the contract is fully signed
        // (status flips to ACTIVE/SIGNED) the wording is locked. Same gate
        // the show() page uses to hide the propose buttons; enforced here
        // server-side too so a stale page or hand-crafted POST cannot
        // sneak past.
        if (!$this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $validated = $request->validate([
            'kind'          => ['required', 'in:modify,add'],
            'section_index' => ['required', 'integer', 'min:0'],
            'item_index'    => ['nullable', 'integer', 'min:0'],
            'new_text'      => ['required', 'string', 'max:2000'],
            'reason'        => ['nullable', 'string', 'max:500'],
        ]);

        $sections = $this->parseTermsSections($contract->terms);
        $si = (int) $validated['section_index'];
        if (!isset($sections[$si])) {
            return back()->withErrors(['amendment' => __('contracts.amendment_section_missing')]);
        }

        $oldText = null;
        if ($validated['kind'] === 'modify') {
            $ii = (int) ($validated['item_index'] ?? -1);
            if ($ii < 0 || !isset($sections[$si]['items'][$ii])) {
                return back()->withErrors(['amendment' => __('contracts.amendment_clause_missing')]);
            }
            $oldText = $sections[$si]['items'][$ii];
        }

        $amendment = ContractAmendment::create([
            'contract_id'  => $contract->id,
            'from_version' => $contract->version,
            'changes'      => [
                'kind'           => $validated['kind'],
                'section_index'  => $si,
                'section_title'  => $sections[$si]['title'] ?? '',
                'item_index'     => $validated['kind'] === 'modify' ? (int) $validated['item_index'] : null,
                'old_text'       => $oldText,
                'new_text'       => $validated['new_text'],
            ],
            'reason'           => $validated['reason'] ?? null,
            'status'           => AmendmentStatus::PENDING_APPROVAL,
            'requested_by'     => $user->id,
            'approval_history' => [[
                'event'      => 'proposed',
                'user_id'    => $user->id,
                'company_id' => $user->company_id,
                'at'         => now()->toIso8601String(),
            ]],
        ]);

        // Notify the OTHER party that there is a pending amendment
        // waiting for their decision. The proposer's own company is
        // intentionally excluded — they already know they just typed it.
        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentProposedNotification($contract, $amendment, $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_proposed'));
    }

    /**
     * Counter-party approves a pending amendment. Merges the change into
     * the contract terms, bumps the version, snapshots a ContractVersion,
     * and stamps the amendment's approval_history.
     *
     * Authorization: must be a party of the contract AND from a different
     * company than the proposer (you can't approve your own amendment).
     */
    public function approveAmendment(string $id, int $amendmentId): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        if (!$this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        if ($amendment->status !== AmendmentStatus::PENDING_APPROVAL) {
            return back()->withErrors(['amendment' => __('contracts.amendment_not_pending')]);
        }

        $proposer = User::find($amendment->requested_by);
        if ($proposer && $proposer->company_id === $user->company_id) {
            return back()->withErrors(['amendment' => __('contracts.amendment_self_approve_forbidden')]);
        }

        DB::transaction(function () use ($contract, $amendment, $user) {
            $changes = $amendment->changes ?? [];
            $si      = (int) ($changes['section_index'] ?? -1);
            $ii      = (int) ($changes['item_index'] ?? -1);
            $kind    = $changes['kind'] ?? '';
            $newText = (string) ($changes['new_text'] ?? '');

            // Decode the existing terms envelope. Two shapes possible:
            //  - bilingual {en: [...], ar: [...]}
            //  - legacy flat [sections...]
            // We apply the amendment to BOTH locales (for bilingual) so the
            // PDF download works in either language. The proposing user
            // typed a single language, so the same text lands in both
            // versions — they remain free to propose a translated version
            // as a follow-up amendment if they care about parity.
            $decoded = is_string($contract->terms) ? json_decode($contract->terms, true) : $contract->terms;
            $isBilingual = is_array($decoded) && (isset($decoded['en']) || isset($decoded['ar']));

            $applyToSections = function (array $sections) use ($si, $ii, $kind, $newText) {
                if (!isset($sections[$si])) {
                    abort(422, __('contracts.amendment_section_missing'));
                }
                if ($kind === 'modify') {
                    if ($ii < 0 || !isset($sections[$si]['items'][$ii])) {
                        abort(422, __('contracts.amendment_clause_missing'));
                    }
                    $sections[$si]['items'][$ii] = $newText;
                } else {
                    $sections[$si]['items'][] = $newText;
                }
                foreach ($sections as $idx => $sec) {
                    $sections[$idx]['items'] = array_values($sec['items'] ?? []);
                }
                return array_values($sections);
            };

            if ($isBilingual) {
                $newTerms = [
                    'en' => $applyToSections($this->parseTermsSections($decoded, 'en')),
                    'ar' => $applyToSections($this->parseTermsSections($decoded, 'ar')),
                ];
            } else {
                // Lazy upgrade: a legacy single-locale contract that gets
                // its first amendment is migrated to the bilingual envelope
                // so that later PDF downloads in either language preserve
                // the amendment. The "other" locale starts as a fresh
                // regeneration of the standard clauses, then the amendment
                // is applied to both sides (same text — the proposer typed
                // a single language and we don't auto-translate).
                $existing = $this->parseTermsSections($decoded);
                $regenerated = $this->service->regenerateTermsForLocale($contract, app()->getLocale() === 'ar' ? 'en' : 'ar');
                if (app()->getLocale() === 'ar') {
                    $newTerms = [
                        'en' => $applyToSections($regenerated),
                        'ar' => $applyToSections($existing),
                    ];
                } else {
                    $newTerms = [
                        'en' => $applyToSections($existing),
                        'ar' => $applyToSections($regenerated),
                    ];
                }
            }

            $contract->update([
                'terms'   => json_encode($newTerms, JSON_UNESCAPED_UNICODE),
                'version' => $contract->version + 1,
            ]);

            $history = $amendment->approval_history ?? [];
            $history[] = [
                'event'      => 'approved',
                'user_id'    => $user->id,
                'company_id' => $user->company_id,
                'at'         => now()->toIso8601String(),
            ];

            $amendment->update([
                'status'           => AmendmentStatus::APPROVED,
                'approval_history' => $history,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version'     => $contract->version,
                'snapshot'    => $contract->fresh()->toArray(),
                'created_by'  => $user->id,
            ]);
        });

        // Notify the proposer's side (and any other parties that aren't
        // the approver's company) that the amendment was approved so
        // the team that proposed it doesn't have to keep refreshing.
        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentDecidedNotification($contract, $amendment, 'approved', $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_approved'));
    }

    /**
     * Counter-party rejects a pending amendment. The contract terms stay
     * untouched and the amendment status flips to REJECTED with the
     * rejection event appended to approval_history.
     */
    public function rejectAmendment(string $id, int $amendmentId, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        if (!$this->canAmendNow($contract)) {
            return back()->withErrors(['amendment' => __('contracts.amendment_window_closed')]);
        }

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        if ($amendment->status !== AmendmentStatus::PENDING_APPROVAL) {
            return back()->withErrors(['amendment' => __('contracts.amendment_not_pending')]);
        }

        $proposer = User::find($amendment->requested_by);
        if ($proposer && $proposer->company_id === $user->company_id) {
            return back()->withErrors(['amendment' => __('contracts.amendment_self_approve_forbidden')]);
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $history = $amendment->approval_history ?? [];
        $history[] = [
            'event'      => 'rejected',
            'user_id'    => $user->id,
            'company_id' => $user->company_id,
            'reason'     => $validated['rejection_reason'] ?? null,
            'at'         => now()->toIso8601String(),
        ];

        $amendment->update([
            'status'           => AmendmentStatus::REJECTED,
            'approval_history' => $history,
        ]);

        // Notify the proposer's side that the amendment was rejected.
        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentDecidedNotification($contract, $amendment, 'rejected', $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_rejected'));
    }

    /**
     * Post a discussion message on a clause amendment thread. Either
     * party of the contract can post; the other party gets a
     * notification with the excerpt so they don't have to keep the
     * contract page open to follow the conversation.
     *
     * Append-only — there is no edit/delete endpoint by design (the
     * thread is part of the legal audit trail of the amendment).
     */
    public function postAmendmentMessage(string $id, int $amendmentId, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        // Once the contract has moved out of the pre-signature window
        // (DRAFT / PENDING_SIGNATURES with no party fully signed) the
        // amendment thread is closed — the wording is locked and any
        // further negotiation must move into a Change Order workflow.
        // Without this guard a stale browser tab could continue posting
        // messages forever after the contract was signed.
        if (!$this->canAmendNow($contract)) {
            return back()->withErrors([
                'amendment' => __('contracts.amendment_window_closed'),
            ]);
        }

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = ContractAmendmentMessage::create([
            'contract_amendment_id' => $amendment->id,
            'user_id'               => $user->id,
            'company_id'            => $user->company_id,
            'body'                  => trim($validated['body']),
        ]);

        // Fan out the notification to every contract party EXCEPT the
        // sender's own company so the supplier and the buyer can ping
        // each other in real time without spamming themselves.
        $this->notifyAmendment(
            $contract,
            $amendment,
            new ContractAmendmentMessageNotification($contract, $amendment, $message, $this->displayName($user)),
            excludeCompanyId: $user->company_id,
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->withFragment('amendment-' . $amendment->id);
    }

    /**
     * Side-by-side track-changes view of two contract versions. The
     * legacy implementation only exposed the diff via a JSON API
     * (compareVersions); this endpoint renders a Word-style diff in
     * the dashboard so legal reviewers can see what changed between
     * any two snapshots without dropping into a JSON tool.
     *
     * Query string:
     *   ?from=N (default: version - 1)
     *   ?to=N   (default: current version)
     */
    public function versionsDiff(string $id, Request $request): View
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $versions = ContractVersion::where('contract_id', $contract->id)
            ->orderBy('version')
            ->get();

        if ($versions->count() < 2) {
            return view('dashboard.contracts.versions-diff', [
                'contract'   => $contract,
                'versions'   => $versions,
                'fromVer'    => null,
                'toVer'      => null,
                'sectionsA'  => [],
                'sectionsB'  => [],
                'has_diff'   => false,
            ]);
        }

        $maxVersion  = (int) $versions->max('version');
        $defaultFrom = max(1, $maxVersion - 1);
        $fromVer     = (int) $request->query('from', $defaultFrom);
        $toVer       = (int) $request->query('to',   $maxVersion);

        $a = $versions->firstWhere('version', $fromVer);
        $b = $versions->firstWhere('version', $toVer);

        $extractSections = function ($snapshot) {
            $terms = is_array($snapshot) ? ($snapshot['terms'] ?? null) : null;
            if (is_string($terms)) {
                $terms = json_decode($terms, true);
            }
            return $this->parseTermsSections($terms ?? []);
        };

        $sectionsA = $a ? $extractSections($a->snapshot) : [];
        $sectionsB = $b ? $extractSections($b->snapshot) : [];

        return view('dashboard.contracts.versions-diff', [
            'contract'   => $contract,
            'versions'   => $versions,
            'fromVer'    => $fromVer,
            'toVer'      => $toVer,
            'sectionsA'  => $sectionsA,
            'sectionsB'  => $sectionsB,
            'has_diff'   => true,
        ]);
    }

    /**
     * JSON endpoint that returns the messages of a single amendment
     * thread since a given timestamp. The blade view polls this every
     * 10 seconds while the thread is open so the two parties see each
     * other's replies in near real-time without a page refresh.
     *
     * Query string:
     *   ?since=2026-04-09T12:34:56Z   ISO 8601 timestamp; only messages
     *                                 created STRICTLY AFTER this are
     *                                 returned. Omit on first poll to
     *                                 receive every message.
     */
    public function pollAmendmentMessages(string $id, int $amendmentId, Request $request): \Illuminate\Http\JsonResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        $since = $request->query('since');
        $sinceCarbon = null;
        if (is_string($since) && $since !== '') {
            try {
                $sinceCarbon = \Carbon\Carbon::parse($since);
            } catch (\Throwable) {
                $sinceCarbon = null;
            }
        }

        $query = ContractAmendmentMessage::where('contract_amendment_id', $amendment->id)
            ->with('user:id,first_name,last_name,email,company_id')
            ->orderBy('created_at');
        if ($sinceCarbon) {
            $query->where('created_at', '>', $sinceCarbon);
        }
        $messages = $query->get();

        return response()->json([
            'amendment_id' => $amendment->id,
            'now'          => now()->toIso8601String(),
            'messages'     => $messages->map(function (ContractAmendmentMessage $m) use ($user) {
                $author = trim(($m->user?->first_name ?? '') . ' ' . ($m->user?->last_name ?? '')) ?: ($m->user?->email ?? '—');
                return [
                    'id'         => $m->id,
                    'body'       => $m->body,
                    'author'     => $author,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'when'       => $m->created_at?->diffForHumans() ?? '—',
                    'is_mine'    => $user && (int) $m->company_id === (int) $user->company_id,
                ];
            })->all(),
        ]);
    }

    /**
     * Track-changes view of the contract terms across two versions.
     * Renders the bilingual sections side-by-side and highlights every
     * line that was added, removed or modified between the two
     * snapshots — the legal-grade equivalent of Word's "Compare
     * Documents" feature. Reuses the existing ContractVersion table
     * (already populated on every amendment approval) so there is no
     * extra storage cost.
     *
     * Query string:
     *   ?from=1   the older version (defaults to 1)
     *   ?to=N     the newer version (defaults to current contract.version)
     */
    public function diffVersions(string $id, Request $request): View
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();
        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $from = (int) $request->query('from', 1);
        $to   = (int) $request->query('to', $contract->version);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // Pull every version from disk so the dropdown can offer the
        // full ladder, then resolve the two requested snapshots.
        $allVersions = ContractVersion::where('contract_id', $contract->id)
            ->orderBy('version')
            ->get(['version', 'created_at', 'created_by']);

        $fromVersion = $allVersions->firstWhere('version', $from);
        $toVersion   = $allVersions->firstWhere('version', $to);

        if (!$fromVersion || !$toVersion) {
            abort(404);
        }

        // The snapshot column holds the full contract array. We only
        // care about the terms slice for the diff.
        $fromSnapshot = ContractVersion::where('contract_id', $contract->id)->where('version', $from)->value('snapshot');
        $toSnapshot   = ContractVersion::where('contract_id', $contract->id)->where('version', $to)->value('snapshot');

        $fromTerms = $this->parseTermsSections($fromSnapshot['terms'] ?? null);
        $toTerms   = $this->parseTermsSections($toSnapshot['terms']   ?? null);

        // Build a structured diff: for every section that exists in
        // either version, line up the items and tag each as added /
        // removed / modified / unchanged. Plain string comparison is
        // sufficient because clauses are short and edits are typically
        // whole-clause replacements (the amendment workflow is at the
        // clause-item level, not character level).
        $diff = $this->buildTermsDiff($fromTerms, $toTerms);

        return view('dashboard.contracts.diff', [
            'contract'    => $contract,
            'from'        => $from,
            'to'          => $to,
            'all_versions'=> $allVersions->map(fn ($v) => [
                'version' => $v->version,
                'date'    => $this->longDate($v->created_at),
            ])->values()->all(),
            'diff'        => $diff,
        ]);
    }

    /**
     * Compare two parsed-terms structures and return a flat list of
     * sections, each with its items annotated with a status. The
     * matching is done by item index inside each section — when an
     * item exists in both versions but the text differs it is tagged
     * `modified`; otherwise `added` / `removed` / `unchanged`.
     *
     * @return array<int, array{title:string, status:string, items: array<int, array{status:string, from:?string, to:?string}>}>
     */
    private function buildTermsDiff(array $fromTerms, array $toTerms): array
    {
        $sections = [];
        $maxSections = max(count($fromTerms), count($toTerms));
        for ($s = 0; $s < $maxSections; $s++) {
            $a = $fromTerms[$s] ?? null;
            $b = $toTerms[$s]   ?? null;

            if ($a && !$b) {
                $sections[] = [
                    'title'  => $a['title'],
                    'status' => 'removed',
                    'items'  => array_map(fn ($t) => ['status' => 'removed', 'from' => $t, 'to' => null], $a['items'] ?? []),
                ];
                continue;
            }
            if ($b && !$a) {
                $sections[] = [
                    'title'  => $b['title'],
                    'status' => 'added',
                    'items'  => array_map(fn ($t) => ['status' => 'added', 'from' => null, 'to' => $t], $b['items'] ?? []),
                ];
                continue;
            }

            $itemsA = $a['items'] ?? [];
            $itemsB = $b['items'] ?? [];
            $maxItems = max(count($itemsA), count($itemsB));
            $items = [];
            for ($i = 0; $i < $maxItems; $i++) {
                $ta = $itemsA[$i] ?? null;
                $tb = $itemsB[$i] ?? null;
                if ($ta === null && $tb === null) {
                    continue;
                }
                if ($ta === null) {
                    $items[] = ['status' => 'added', 'from' => null, 'to' => $tb];
                } elseif ($tb === null) {
                    $items[] = ['status' => 'removed', 'from' => $ta, 'to' => null];
                } elseif (trim($ta) !== trim($tb)) {
                    $items[] = ['status' => 'modified', 'from' => $ta, 'to' => $tb];
                } else {
                    $items[] = ['status' => 'unchanged', 'from' => $ta, 'to' => $tb];
                }
            }

            $sectionStatus = collect($items)->pluck('status')->unique()->values()->all();
            $status = count($sectionStatus) === 1 ? $sectionStatus[0] : 'modified';

            $sections[] = [
                'title'  => $b['title'] ?? $a['title'] ?? '',
                'status' => $status,
                'items'  => $items,
            ];
        }
        return $sections;
    }

    /**
     * Cancel a pending amendment that the current user proposed.
     * Only the proposing user (NOT just the same company) can cancel,
     * and only while the amendment is still PENDING_APPROVAL — once
     * the counter-party has approved or rejected, the decision sticks.
     * The endpoint flips the status to REJECTED with a "cancelled by
     * proposer" event in the approval_history so the audit log
     * preserves both the original proposal and the cancel action.
     */
    public function cancelAmendment(string $id, int $amendmentId): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $amendment = ContractAmendment::where('contract_id', $contract->id)->findOrFail($amendmentId);

        if ($amendment->status !== AmendmentStatus::PENDING_APPROVAL) {
            return back()->withErrors(['amendment' => __('contracts.amendment_not_pending')]);
        }

        // Strict ownership: only the user who proposed the amendment
        // (not just any user from the same company) may cancel it.
        // This prevents one team member from undoing another's work
        // without leaving an obvious accountability trail.
        if ((int) $amendment->requested_by !== (int) $user->id) {
            return back()->withErrors(['amendment' => __('contracts.amendment_cancel_forbidden')]);
        }

        $history = $amendment->approval_history ?? [];
        $history[] = [
            'event'      => 'cancelled',
            'user_id'    => $user->id,
            'company_id' => $user->company_id,
            'at'         => now()->toIso8601String(),
        ];

        $amendment->update([
            'status'           => AmendmentStatus::REJECTED,
            'approval_history' => $history,
        ]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.amendment_cancelled'));
    }

    /**
     * Decline a contract before it has been signed by either party.
     * Either side can decline — the contract flips to CANCELLED with a
     * declination reason captured for the audit log. Once any party has
     * applied a signature this path is closed (the contract is then in
     * PENDING_SIGNATURES with at least one signer, and the only way
     * out is the formal terminate flow).
     */
    public function decline(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $statusValue = $this->statusValue($contract->status);
        $hasAnySignature = !empty($contract->signatures);

        if (!in_array($statusValue, [ContractStatus::DRAFT->value, ContractStatus::PENDING_SIGNATURES->value], true) || $hasAnySignature) {
            return back()->withErrors([
                'contract' => __('contracts.decline_window_closed'),
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $contract->update([
            'status'      => ContractStatus::CANCELLED,
            'description' => trim(($contract->description ?? '') . "\n\n[DECLINED " . now()->toDateString() . "] " . $validated['reason']),
        ]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.declined_successfully'));
    }

    /**
     * Terminate an active contract by mutual agreement. Either party
     * may initiate termination, but the contract status only flips to
     * TERMINATED here — the actual settlement of held escrow funds and
     * outstanding payments is handled by the existing escrow / payment
     * workflows. The reason is appended to the contract description so
     * a future reader of the contract can see why it ended.
     *
     * Business rule: only ACTIVE / SIGNED contracts can be terminated.
     * Pre-signature contracts go through decline() instead;
     * already-terminated / completed / cancelled contracts cannot be
     * terminated again.
     */
    public function terminate(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.view'), 403);
        $this->authorizeContractParty($contract);

        $statusValue = $this->statusValue($contract->status);
        if (!in_array($statusValue, [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value], true)) {
            return back()->withErrors([
                'contract' => __('contracts.terminate_window_closed'),
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $contract->update([
            'status'      => ContractStatus::TERMINATED,
            'description' => trim(($contract->description ?? '') . "\n\n[TERMINATED " . now()->toDateString() . " by " . $this->displayName($user) . "] " . $validated['reason']),
        ]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.terminated_successfully'));
    }

    /**
     * Helper: send a notification to every user belonging to a contract
     * party, optionally excluding one company (typically the actor's
     * own). Wrapped in try/catch so a notification failure can NEVER
     * roll back a contract action — the contract is the source of
     * truth, the notification is best-effort.
     */
    private function notifyAmendment(
        Contract $contract,
        ContractAmendment $amendment,
        \Illuminate\Notifications\Notification $notification,
        ?int $excludeCompanyId = null,
    ): void {
        try {
            $partyCompanyIds = collect($contract->parties ?? [])
                ->pluck('company_id')
                ->push($contract->buyer_company_id)
                ->filter()
                ->unique()
                ->reject(fn ($cid) => $excludeCompanyId !== null && (int) $cid === (int) $excludeCompanyId)
                ->values()
                ->all();

            if (empty($partyCompanyIds)) {
                return;
            }

            $recipients = User::whereIn('company_id', $partyCompanyIds)->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            }
        } catch (\Throwable $e) {
            \Log::warning('Amendment notification dispatch failed', [
                'contract_id'  => $contract->id,
                'amendment_id' => $amendment->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve a friendly display name for a user — first + last name
     * with a sane fallback so notification subjects don't ever read
     * "  has signed". Used by every notification dispatch in this
     * controller.
     */
    private function displayName(User $user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : ($user->email ?? 'A party');
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
     * Decode the contract's `terms` column into a flat array of
     * `{title, items[]}` sections in a specific locale.
     *
     * Three storage shapes are supported:
     *
     *   1. Bilingual envelope (new): `{"en": [sections], "ar": [sections]}`
     *      → return `terms[$locale]`, falling back to `en` then `ar`.
     *
     *   2. Flat list of sections (legacy single-locale): `[sections]`
     *      → return as-is. The caller is responsible for re-rendering in
     *        a different locale via ContractService::regenerateTermsForLocale
     *        if the requested locale doesn't match what was baked.
     *
     *   3. Plain text (very old contracts): split into a single section.
     *
     * @return array<int, array{title:string, items: array<int,string>}>
     */
    private function parseTermsSections($terms, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();

        if (is_array($terms)) {
            // Bilingual envelope — pick the requested locale.
            if (isset($terms['en']) || isset($terms['ar'])) {
                $picked = $terms[$locale] ?? $terms['en'] ?? $terms['ar'] ?? [];
                return $this->parseTermsSections($picked, $locale);
            }

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
                return $this->parseTermsSections($decoded, $locale);
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
     * Server-side gate for the bilateral amendment window. Mirrors the
     * `$canAmend` flag the show() page passes to the view, so the
     * propose / approve / reject endpoints reject any request that lands
     * after the contract has moved out of the pre-signature window
     * (DRAFT or PENDING_SIGNATURES with no party fully signed yet).
     *
     * The business rule: clause wording is settled BEFORE the e-signature
     * is collected. After the contract is fully signed, the only way to
     * change a clause is to terminate and re-issue.
     */
    private function canAmendNow(Contract $contract): bool
    {
        $statusValue = $this->statusValue($contract->status);
        $preSignatureStatus = in_array(
            $statusValue,
            [ContractStatus::DRAFT->value, ContractStatus::PENDING_SIGNATURES->value],
            true
        );
        return $preSignatureStatus && !$contract->allPartiesHaveSigned();
    }

    /**
     * Returns true when the contract's `terms` column is stored in the
     * bilingual `{en, ar}` envelope (new format) — false for legacy
     * single-locale flat arrays. Used by the PDF download path to decide
     * whether to render `terms[$locale]` directly or to regenerate the
     * standard clauses fresh in the requested locale.
     */
    private function termsAreBilingual($terms): bool
    {
        if (is_string($terms)) {
            $terms = json_decode($terms, true);
        }
        return is_array($terms) && (isset($terms['en']) || isset($terms['ar']));
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
