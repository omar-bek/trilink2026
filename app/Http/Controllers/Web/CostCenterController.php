<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CostCenterController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->authorize($request);

        $centers = $company->costCenters()->with('parent', 'owner')
            ->orderBy('code')->get();
        $owners = User::where('company_id', $company->id)->active()->get(['id', 'first_name', 'last_name']);

        return view('dashboard.settings.cost-centers.index', compact('centers', 'owners', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->authorize($request);
        $data = $this->validateCenter($request, $company->id);
        $company->costCenters()->create($data);

        return redirect()->route('settings.cost-centers.index')
            ->with('status', __('settings.saved'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $company = $this->authorize($request);
        $center = CostCenter::where('company_id', $company->id)->findOrFail($id);
        $data = $this->validateCenter($request, $company->id, $center->id);

        // Don't let a manager accidentally make a centre its own parent —
        // that would produce an infinite loop in the tree renderer.
        if (isset($data['parent_id']) && (int) $data['parent_id'] === $center->id) {
            $data['parent_id'] = null;
        }

        $center->update($data);

        return redirect()->route('settings.cost-centers.index')
            ->with('status', __('settings.saved'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $company = $this->authorize($request);
        $center = CostCenter::where('company_id', $company->id)->findOrFail($id);

        $center->delete();

        return back()->with('status', __('settings.deleted'));
    }

    private function authorize(Request $request): \App\Models\Company
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('manageDefaults', $company), 403);

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCenter(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:32',
                Rule::unique('cost_centers', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($ignoreId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'parent_id' => ['nullable', 'integer',
                Rule::exists('cost_centers', 'id')->where('company_id', $companyId),
            ],
            'annual_budget_aed' => ['nullable', 'numeric', 'min:0'],
            'fiscal_year' => ['nullable', 'integer', 'between:2020,2100'],
            'owner_user_id' => ['nullable', 'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
