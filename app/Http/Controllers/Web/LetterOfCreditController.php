<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LetterOfCredit;
use App\Models\LetterOfCreditDrawing;
use App\Services\LetterOfCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buyer-side applicants open LCs; supplier-side beneficiaries draw
 * against them. Both sides share the same index/show pages — the
 * authorization check keys on membership (applicant OR beneficiary)
 * rather than on role, so a company that plays both sides on different
 * contracts sees a single unified list.
 */
class LetterOfCreditController extends Controller
{
    public function __construct(private readonly LetterOfCreditService $service) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyIdOrAbort($request);

        $lcs = LetterOfCredit::query()
            ->where(function ($q) use ($companyId) {
                $q->where('applicant_company_id', $companyId)
                    ->orWhere('beneficiary_company_id', $companyId);
            })
            ->with(['applicant', 'beneficiary', 'contract'])
            ->latest('issue_date')
            ->paginate(20);

        return view('dashboard.letters-of-credit.index', compact('lcs'));
    }

    public function show(Request $request, int $id): View
    {
        $lc = $this->findOrAbort($request, $id);
        $lc->load(['applicant', 'beneficiary', 'contract', 'events.actor', 'drawings.presenter']);

        return view('dashboard.letters-of-credit.show', compact('lc'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $companyId = $this->companyIdOrAbort($request);

        $data = $request->validate([
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'beneficiary_company_id' => ['required', 'integer', 'exists:companies,id'],
            'lc_number' => ['required', 'string', 'max:64', 'unique:letters_of_credit,lc_number'],
            'issuing_bank' => ['required', 'string', 'max:200'],
            'issuing_bank_bic' => ['nullable', 'string', 'size:11'],
            'advising_bank' => ['nullable', 'string', 'max:200'],
            'advising_bank_bic' => ['nullable', 'string', 'size:11'],
            'form' => ['required', 'in:irrevocable,revocable,standby'],
            'payment_type' => ['required', 'in:sight,usance,mixed,deferred'],
            'usance_days' => ['nullable', 'integer', 'between:0,365'],
            'transferable' => ['nullable', 'boolean'],
            'confirmed' => ['nullable', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'tolerance_percent_over' => ['nullable', 'integer', 'between:0,20'],
            'tolerance_percent_under' => ['nullable', 'integer', 'between:0,20'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after:issue_date'],
            'expiry_place' => ['nullable', 'string', 'max:100'],
            'latest_shipment_date' => ['nullable', 'date', 'before_or_equal:expiry_date'],
            'incoterm' => ['nullable', 'string', 'max:8'],
            'port_of_loading' => ['nullable', 'string', 'max:100'],
            'port_of_discharge' => ['nullable', 'string', 'max:100'],
            'goods_description' => ['nullable', 'string', 'max:2000'],
            'documents_required' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if($data['beneficiary_company_id'] == $companyId, 422, 'Beneficiary cannot be the applicant');

        $lc = new LetterOfCredit($data);
        $lc->applicant_company_id = $companyId;
        $lc->available_amount = $data['amount'];
        $lc->transferable = $request->boolean('transferable');
        $lc->confirmed = $request->boolean('confirmed');
        $lc->save();

        $this->service->issue($lc, $user);

        return redirect()->route('dashboard.lc.show', $lc->id)
            ->with('status', __('lc.issued'));
    }

    public function present(Request $request, int $id): RedirectResponse
    {
        $lc = $this->findOrAbort($request, $id);
        abort_unless($lc->beneficiary_company_id === $request->user()->company_id, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'discrepancies' => ['nullable', 'array'],
            'discrepancies.*' => ['string', 'max:200'],
            'document_bundle' => ['nullable', 'file', 'max:10240'],
        ]);

        $path = $request->hasFile('document_bundle')
            ? $request->file('document_bundle')->store("lc/{$lc->id}", 'local')
            : null;

        $this->service->present($lc, (float) $data['amount'], $request->user(), $data['discrepancies'] ?? [], $path);

        return back()->with('status', __('lc.presented'));
    }

    public function honour(Request $request, int $lcId, int $drawingId): RedirectResponse
    {
        $lc = $this->findOrAbort($request, $lcId);
        abort_unless($lc->applicant_company_id === $request->user()->company_id, 403);

        $drawing = LetterOfCreditDrawing::where('letter_of_credit_id', $lc->id)->findOrFail($drawingId);
        $this->service->honour($drawing, $request->user());

        return back()->with('status', __('lc.honoured'));
    }

    public function reject(Request $request, int $lcId, int $drawingId): RedirectResponse
    {
        $lc = $this->findOrAbort($request, $lcId);
        abort_unless($lc->applicant_company_id === $request->user()->company_id, 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $drawing = LetterOfCreditDrawing::where('letter_of_credit_id', $lc->id)->findOrFail($drawingId);
        $this->service->reject($drawing, $request->user(), $data['reason']);

        return back()->with('status', __('lc.rejected'));
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $lc = $this->findOrAbort($request, $id);
        abort_unless($lc->applicant_company_id === $request->user()->company_id, 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->cancel($lc, $request->user(), $data['reason']);

        return redirect()->route('dashboard.lc.index')
            ->with('status', __('lc.cancelled'));
    }

    private function companyIdOrAbort(Request $request): int
    {
        $id = $request->user()?->company_id;
        abort_unless($id, 403);

        return (int) $id;
    }

    private function findOrAbort(Request $request, int $id): LetterOfCredit
    {
        $companyId = $this->companyIdOrAbort($request);
        $lc = LetterOfCredit::findOrFail($id);
        abort_unless(
            $lc->applicant_company_id === $companyId || $lc->beneficiary_company_id === $companyId,
            403
        );

        return $lc;
    }
}
