<?php

namespace App\Http\Controllers\Web;

use App\Enums\BankGuaranteeStatus;
use App\Enums\BankGuaranteeType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\BankGuarantee;
use App\Services\BankGuaranteeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankGuaranteeController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly BankGuaranteeService $service) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $companyId = $user?->company_id;

        $query = BankGuarantee::query()
            ->with(['applicant', 'beneficiary', 'contract'])
            ->when($companyId, function ($q) use ($companyId) {
                $q->where(function ($q) use ($companyId) {
                    $q->where('applicant_company_id', $companyId)
                        ->orWhere('beneficiary_company_id', $companyId);
                });
            })
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        $guarantees = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => BankGuarantee::query()->when($companyId, fn ($q) => $q->where('applicant_company_id', $companyId)->orWhere('beneficiary_company_id', $companyId))->count(),
            'live' => BankGuarantee::query()->when($companyId, fn ($q) => $q->where('applicant_company_id', $companyId)->orWhere('beneficiary_company_id', $companyId))->whereIn('status', ['live', 'issued', 'reduced'])->count(),
            'expiring_soon' => BankGuarantee::query()
                ->when($companyId, fn ($q) => $q->where('applicant_company_id', $companyId)->orWhere('beneficiary_company_id', $companyId))
                ->whereIn('status', ['live', 'issued', 'reduced'])
                ->whereBetween('expiry_date', [now(), now()->addDays(30)])
                ->count(),
            'called' => BankGuarantee::query()->when($companyId, fn ($q) => $q->where('applicant_company_id', $companyId)->orWhere('beneficiary_company_id', $companyId))->where('status', 'called')->count(),
        ];

        return view('dashboard.bank-guarantees.index', [
            'guarantees' => $guarantees,
            'stats' => $stats,
            'types' => BankGuaranteeType::cases(),
            'statuses' => BankGuaranteeStatus::cases(),
        ]);
    }

    public function show(string $id): View
    {
        $bg = BankGuarantee::with(['applicant', 'beneficiary', 'contract', 'rfq', 'calls', 'events.bankGuarantee'])
            ->findOrFail((int) $id);

        $user = auth()->user();
        abort_unless(
            $user && in_array($user->company_id, [$bg->applicant_company_id, $bg->beneficiary_company_id], true)
                || in_array($user?->role?->value, ['admin', 'government'], true),
            403
        );

        return view('dashboard.bank-guarantees.show', [
            'bg' => $bg,
            'isApplicant' => $user->company_id === $bg->applicant_company_id,
            'isBeneficiary' => $user->company_id === $bg->beneficiary_company_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', new \Illuminate\Validation\Rules\Enum(BankGuaranteeType::class)],
            'governing_rules' => ['nullable', 'in:URDG_758,URDG_458,ISP_98,local_uae'],
            'beneficiary_company_id' => ['required', 'integer', 'exists:companies,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'rfq_id' => ['nullable', 'integer', 'exists:rfqs,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'percentage_of_base' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_amount' => ['nullable', 'numeric', 'min:0'],
            'validity_start_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after:validity_start_date'],
            'claim_period_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'issuing_bank_name' => ['required', 'string', 'max:150'],
            'issuing_bank_swift' => ['nullable', 'string', 'max:16'],
            'issuing_bank_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $data['applicant_company_id'] = $user->company_id;
        $data['currency'] ??= 'AED';
        $data['governing_rules'] ??= 'URDG_758';

        $bg = $this->service->register($data, $user);

        return redirect()->route('dashboard.bank-guarantees.show', ['id' => $bg->id])
            ->with('status', __('bg.registered'));
    }

    public function activate(string $id): RedirectResponse
    {
        $bg = BankGuarantee::findOrFail((int) $id);
        $user = auth()->user();
        abort_unless($user && $user->company_id === $bg->beneficiary_company_id, 403);

        $this->service->activate($bg, $user);

        return back()->with('status', __('bg.activated'));
    }

    public function call(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $bg = BankGuarantee::findOrFail((int) $id);
        $user = auth()->user();

        $this->service->call($bg, $user, (float) $data['amount'], $data['reason']);

        return back()->with('status', __('bg.called'));
    }

    public function extend(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'new_expiry' => ['required', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $bg = BankGuarantee::findOrFail((int) $id);
        $user = auth()->user();
        abort_unless($user && in_array($user->company_id, [$bg->applicant_company_id, $bg->beneficiary_company_id], true), 403);

        $this->service->extend($bg, $user, $data['new_expiry'], $data['note'] ?? null);

        return back()->with('status', __('bg.extended'));
    }

    public function release(string $id): RedirectResponse
    {
        $bg = BankGuarantee::findOrFail((int) $id);
        $user = auth()->user();

        $this->service->release($bg, $user);

        return back()->with('status', __('bg.released'));
    }
}
