<?php

namespace App\Http\Controllers\Web;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Company;
use App\Models\Contract;
use App\Services\ContractService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ContractController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly ContractService $service)
    {
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('contract.view'), 403);

        $companyId = $this->currentCompanyId();

        $base = Contract::query()->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId));

        $totalValue = (clone $base)->sum('total_amount');

        $stats = [
            'total'     => (clone $base)->count(),
            'active'    => (clone $base)->where('status', ContractStatus::ACTIVE->value)->count(),
            'completed' => (clone $base)->where('status', ContractStatus::COMPLETED->value)->count(),
            'value'     => $this->shortMoney((float) $totalValue),
        ];

        // Pre-load all supplier names referenced in `parties` JSON to avoid N+1.
        $contractRows = (clone $base)->latest()->get();
        $partyCompanyIds = $contractRows
            ->flatMap(fn (Contract $c) => collect($c->parties ?? [])->pluck('company_id'))
            ->filter()
            ->unique()
            ->values();

        $companyNames = Company::whereIn('id', $partyCompanyIds)->pluck('name', 'id');

        $contracts = $contractRows->map(function (Contract $c) use ($companyNames) {
            $statusKey = $this->mapContractStatus($this->statusValue($c->status));
            [$progress, $label, $color] = $this->progressFor($statusKey);

            return [
                'id'             => $c->contract_number,
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

        return view('dashboard.contracts.index', compact('stats', 'contracts'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('contract.view'), 403);

        $contract = $this->findOrFail($id)->load(['payments', 'shipments']);

        $statusKey = $this->mapContractStatus($this->statusValue($contract->status));
        [$progress, $progressLabel, $progressColor] = $this->progressFor($statusKey);

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

        $data = [
            'id'              => $contract->contract_number,
            'numeric_id'      => $contract->id,
            'title'           => $contract->title,
            'status'          => $statusKey,
            'amount'          => $this->money($totalAmount, $currency),
            'progress'        => $progress,
            'progress_label'  => $progressLabel,
            'days_remaining'  => $daysRemaining,
            'parties'         => $parties,
            'milestones'      => $milestones,
            'terms_sections'  => $termsSections,
            'timeline'        => $timeline,
            'documents'       => $documents,
            'has_shipment'    => $contract->shipments->isNotEmpty(),
            'shipment_id'     => $contract->shipments->first()?->tracking_number,
        ];

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

        $pdf = Pdf::loadView('contracts.pdf', ['contract' => $contract]);

        return $pdf->download($contract->contract_number . '.pdf');
    }

    public function sign(string $id): RedirectResponse
    {
        $contract = $this->findOrFail($id);
        $user     = auth()->user();

        abort_unless($user?->hasPermission('contract.sign'), 403, 'Forbidden: missing contracts.sign permission.');

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

    private function findOrFail(string $id): Contract
    {
        $query = Contract::query();

        if (str_starts_with($id, 'CTR-') || str_starts_with($id, 'CNT-')) {
            return $query->where('contract_number', $id)->firstOrFail();
        }

        return $query->findOrFail((int) $id);
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
     * @return array{0:int,1:string,2:string} [progress%, label, color]
     */
    private function progressFor(string $statusKey): array
    {
        return match ($statusKey) {
            'completed' => [100, __('status.completed'), '#10B981'],
            'active'    => [65, __('contracts.in_production'), '#3B82F6'],
            'pending'   => [10, __('status.pending'), '#F59E0B'],
            'closed'    => [0, __('status.closed'), '#6B7280'],
            default     => [0, '', '#6B7280'],
        };
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
