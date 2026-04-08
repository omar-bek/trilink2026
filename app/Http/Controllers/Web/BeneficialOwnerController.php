<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BeneficialOwner;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manager-facing CRUD for the company's beneficial owners list.
 *
 * Phase 2 / Sprint 8 / task 2.7. Disclosure is required for Gold tier
 * and above. The page is reachable from the company settings sidebar
 * and from the verification badge tooltip when the company is
 * "almost there" for promotion.
 *
 * Authorisation: only the company manager can edit the list, since
 * declared owners are personal data with regulatory implications. Other
 * staff (finance, sales, branch_manager) cannot reach this controller.
 */
class BeneficialOwnerController extends Controller
{
    public function __construct(private readonly VerificationService $verification)
    {
    }

    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);

        $owners = BeneficialOwner::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('ownership_percentage')
            ->get();

        $totalOwnership = (float) $owners->sum('ownership_percentage');
        $isComplete     = $totalOwnership >= 100.0;

        return view('dashboard.beneficial-owners.index', [
            'owners'         => $owners,
            'totalOwnership' => $totalOwnership,
            'isComplete'     => $isComplete,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $this->validateData($request);

        BeneficialOwner::create(array_merge($data, [
            'company_id' => $user->company_id,
        ]));

        // After every change, ask the verification service whether the
        // company now qualifies for a tier upgrade. The auto-promotion
        // is conservative — it only ever upgrades, never demotes.
        $this->verification->autoPromoteIfEligible(
            $user->company,
            $user->id,
        );

        return redirect()
            ->route('dashboard.beneficial-owners.index')
            ->with('status', __('beneficial_owners.added_successfully'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $owner = BeneficialOwner::where('company_id', $user->company_id)->findOrFail($id);

        $data = $this->validateData($request);
        $owner->update($data);

        return redirect()
            ->route('dashboard.beneficial-owners.index')
            ->with('status', __('beneficial_owners.updated_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $owner = BeneficialOwner::where('company_id', $user->company_id)->findOrFail($id);
        $owner->delete();

        return redirect()
            ->route('dashboard.beneficial-owners.index')
            ->with('status', __('beneficial_owners.deleted_successfully'));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'full_name'            => ['required', 'string', 'max:191'],
            'nationality'          => ['nullable', 'string', 'max:64'],
            'date_of_birth'        => ['nullable', 'date', 'before:today'],
            'id_type'              => ['nullable', 'string', 'in:' . implode(',', BeneficialOwner::ID_TYPES)],
            'id_number'            => ['nullable', 'string', 'max:64'],
            'id_expiry'            => ['nullable', 'date', 'after:today'],
            'ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'role'                 => ['nullable', 'string', 'in:' . implode(',', BeneficialOwner::ROLES)],
            'is_pep'               => ['nullable', 'boolean'],
            'source_of_wealth'     => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
