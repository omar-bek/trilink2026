<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manager-facing CRUD for the company's exclusive supplier list. Adding a
 * supplier here prevents that supplier from bidding on the manager's RFQs
 * (BidService::create enforces the rule). Removal restores their ability
 * to bid.
 */
class CompanySupplierController extends Controller
{
    public function index(): View
    {
        $companyId = auth()->user()->company_id;
        abort_unless($companyId, 403);

        $links = CompanySupplier::with(['supplierCompany', 'addedBy'])
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(20);

        return view('dashboard.suppliers.index', compact('links'));
    }

    public function create(Request $request): View
    {
        $companyId = auth()->user()->company_id;
        abort_unless($companyId, 403);

        // Show every supply-capable company that isn't already linked to
        // me and isn't my own company. "Supply-capable" = has at least one
        // assigned category, since every company can both buy and sell —
        // the category list is what marks them as offering something.
        $linkedIds = CompanySupplier::where('company_id', $companyId)->pluck('supplier_company_id');

        $suppliers = Company::query()
            ->where('id', '!=', $companyId)
            ->whereHas('categories')
            ->whereNotIn('id', $linkedIds)
            ->when($request->input('q'), fn ($q, $term) => $q->search($term, ['name', 'name_ar']))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'name_ar', 'country']);

        return view('dashboard.suppliers.create', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $request->validate([
            'supplier_company_id' => ['required', 'integer', 'exists:companies,id'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ]);

        // Cannot lock yourself to yourself.
        abort_if($data['supplier_company_id'] == $user->company_id, 422, 'Cannot link a company to itself.');

        CompanySupplier::firstOrCreate(
            [
                'company_id'          => $user->company_id,
                'supplier_company_id' => $data['supplier_company_id'],
            ],
            [
                'status'   => 'active',
                'notes'    => $data['notes'] ?? null,
                'added_by' => $user->id,
            ]
        );

        return redirect()
            ->route('dashboard.suppliers.index')
            ->with('status', __('suppliers.added_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $link = CompanySupplier::findOrFail($id);

        abort_unless($link->company_id === $user->company_id, 403);

        $link->delete();

        return redirect()
            ->route('dashboard.suppliers.index')
            ->with('status', __('suppliers.removed_successfully'));
    }
}
