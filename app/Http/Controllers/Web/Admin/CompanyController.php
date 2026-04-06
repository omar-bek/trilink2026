<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Admin-side company management. Admins can approve pending registrations,
 * suspend bad actors, edit profile data, and soft-delete companies. They
 * cannot touch the company's transactional history (PRs/contracts/payments)
 * — those are managed via their own dashboards and protected for audit.
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $type   = $request->query('type');

        $companies = Company::query()
            ->withCount(['users', 'purchaseRequests', 'rfqs', 'bids'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('name_ar', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('registration_number', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'    => Company::count(),
            'active'   => Company::where('status', CompanyStatus::ACTIVE->value)->count(),
            'pending'  => Company::where('status', CompanyStatus::PENDING->value)->count(),
            'inactive' => Company::where('status', CompanyStatus::INACTIVE->value)->count(),
        ];

        return view('dashboard.admin.companies.index', compact('companies', 'stats', 'q', 'status', 'type'));
    }

    public function show(int $id): View
    {
        $company = Company::with(['users', 'categories'])
            ->withCount(['purchaseRequests', 'rfqs', 'bids', 'buyerContracts', 'payments'])
            ->findOrFail($id);

        return view('dashboard.admin.companies.show', compact('company'));
    }

    public function edit(int $id): View
    {
        $company    = Company::findOrFail($id);
        $categories = Category::where('is_active', true)->orderBy('path')->get();

        return view('dashboard.admin.companies.edit', compact('company', 'categories'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $before  = $company->only(['name', 'name_ar', 'email', 'phone', 'address', 'city', 'country', 'type', 'status', 'description']);

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'name_ar'             => ['nullable', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:100', Rule::unique('companies', 'registration_number')->ignore($company->id)->whereNull('deleted_at')],
            'tax_number'          => ['nullable', 'string', 'max:100'],
            'type'                => ['required', new Enum(CompanyType::class)],
            'status'              => ['required', new Enum(CompanyStatus::class)],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'website'             => ['nullable', 'url', 'max:255'],
            'address'             => ['nullable', 'string', 'max:1000'],
            'city'                => ['nullable', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:100'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'categories'          => ['nullable', 'array'],
            'categories.*'        => ['integer', 'exists:categories,id'],
        ]);

        $categories = $data['categories'] ?? [];
        unset($data['categories']);

        $company->update($data);
        $company->categories()->sync($categories);

        $this->audit(AuditAction::UPDATE, $company, $before, $company->only(array_keys($before)));

        return redirect()
            ->route('admin.companies.show', $company->id)
            ->with('status', __('admin.companies.updated'));
    }

    public function approve(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $before  = ['status' => $company->status?->value];

        $company->update(['status' => CompanyStatus::ACTIVE]);

        $this->audit(AuditAction::APPROVE, $company, $before, ['status' => CompanyStatus::ACTIVE->value]);

        return back()->with('status', __('admin.companies.approved'));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $before  = ['status' => $company->status?->value];

        $company->update(['status' => CompanyStatus::INACTIVE]);

        $this->audit(AuditAction::REJECT, $company, $before, ['status' => CompanyStatus::INACTIVE->value, 'reason' => $request->input('reason')]);

        return back()->with('status', __('admin.companies.rejected'));
    }

    public function suspend(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $before  = ['status' => $company->status?->value];

        $company->update(['status' => CompanyStatus::INACTIVE]);

        $this->audit(AuditAction::UPDATE, $company, $before, ['status' => CompanyStatus::INACTIVE->value]);

        return back()->with('status', __('admin.companies.suspended'));
    }

    public function reactivate(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $before  = ['status' => $company->status?->value];

        $company->update(['status' => CompanyStatus::ACTIVE]);

        $this->audit(AuditAction::UPDATE, $company, $before, ['status' => CompanyStatus::ACTIVE->value]);

        return back()->with('status', __('admin.companies.reactivated'));
    }

    /**
     * Soft-delete a company.
     *
     * The cascade lives in `Company::booted()` (a `deleting` hook):
     *  - All users belonging to this company are soft-deleted as well.
     *  - The company⇄category pivot is detached.
     *  - Contracts, bids, payments, RFQs, PRs, shipments, disputes and
     *    audit logs are PRESERVED. Companies are the platform's foundation
     *    but its transactional history must remain immutable for audit,
     *    compliance and counter-party protection.
     */
    public function destroy(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $this->audit(AuditAction::DELETE, $company, $company->toArray(), null);

        $company->delete(); // soft delete — see Company::booted() for cascade

        return redirect()
            ->route('admin.companies.index')
            ->with('status', __('admin.companies.deleted'));
    }

    private function audit(AuditAction $action, Company $company, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'user_id'       => auth()->id(),
            'company_id'    => $company->id,
            'action'        => $action,
            'resource_type' => 'Company',
            'resource_id'   => $company->id,
            'before'        => $before,
            'after'         => $after,
            'ip_address'    => request()->ip(),
            'user_agent'    => substr((string) request()->userAgent(), 0, 255),
            'status'        => 'success',
        ]);
    }
}
