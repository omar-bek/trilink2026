<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin/Government CRUD for platform-wide tax rates.
 *
 * The model behind this controller (TaxRate) is what ContractService and
 * PaymentService consult when creating new contracts or auto-generating
 * milestone payments. Editing here directly affects how every subsequent
 * transaction is taxed.
 */
class TaxRateController extends Controller
{
    public function index(): View
    {
        $taxRates = TaxRate::with('category')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20);

        return view('dashboard.admin.tax-rates.index', compact('taxRates'));
    }

    public function create(): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('dashboard.admin.tax-rates.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // Only one default rate can exist at a time. If the new row is the
        // default, demote any prior default first so the lookup is unambiguous.
        if (! empty($data['is_default'])) {
            TaxRate::where('is_default', true)->update(['is_default' => false]);
        }

        $taxRate = TaxRate::create($data);

        $this->audit(AuditAction::CREATE, $taxRate, null, $taxRate->toArray());

        return redirect()
            ->route('admin.tax-rates.index')
            ->with('status', __('admin.tax_rates.created'));
    }

    public function edit(int $id): View
    {
        $taxRate = TaxRate::findOrFail($id);
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('dashboard.admin.tax-rates.edit', compact('taxRate', 'categories'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $taxRate = TaxRate::findOrFail($id);
        $before = $taxRate->toArray();
        $data = $this->validateData($request, $id);

        if (! empty($data['is_default'])) {
            TaxRate::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $taxRate->update($data);

        $this->audit(AuditAction::UPDATE, $taxRate, $before, $taxRate->fresh()->toArray());

        return redirect()
            ->route('admin.tax-rates.index')
            ->with('status', __('admin.tax_rates.updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $taxRate = TaxRate::findOrFail($id);
        $before = $taxRate->toArray();

        $taxRate->delete();

        $this->audit(AuditAction::DELETE, $taxRate, $before, null);

        return redirect()
            ->route('admin.tax-rates.index')
            ->with('status', __('admin.tax_rates.deleted'));
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32', 'unique:tax_rates,code'.($id ? ','.$id : '')],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'country' => ['nullable', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function audit(AuditAction $action, TaxRate $taxRate, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => auth()->user()?->company_id,
            'action' => $action,
            'resource_type' => 'TaxRate',
            'resource_id' => $taxRate->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'status' => 'success',
        ]);
    }
}
