<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Enums\VerificationLevel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Services\SanctionsScreeningService;
use App\Notifications\CompanyInfoRequestedNotification;
use App\Support\CompanyInfoFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
            ->when($q !== '', fn ($query) => $query->search($q, ['name', 'name_ar', 'email', 'registration_number']))
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

        // Approving clears any pending info request — the admin has decided
        // they have everything they need. Deleting the typed row mirrors the
        // old behaviour of nulling the JSON column.
        $company->infoRequest()->delete();

        $this->audit(AuditAction::APPROVE, $company, $before, ['status' => CompanyStatus::ACTIVE->value]);

        return back()->with('status', __('admin.companies.approved'));
    }

    /**
     * Save an "I need more info" request against the company. The pending
     * status is preserved — this is just a structured note + a checklist of
     * fields we want the manager to fill in. The manager sees a completion
     * form on /register/success the next time they hit the site.
     */
    public function requestInfo(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $data = $request->validate([
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['string', Rule::in(CompanyInfoFields::allKeys())],
            'note'    => ['nullable', 'string', 'max:2000'],
        ]);

        $items = array_values(array_unique($data['items']));
        $note  = $data['note'] ?? '';

        $before = $company->infoRequest
            ? [
                'items'        => $company->infoRequest->items,
                'note'         => $company->infoRequest->note,
                'requested_at' => $company->infoRequest->requested_at?->toDateTimeString(),
                'requested_by' => $company->infoRequest->requested_by,
            ]
            : null;

        // Upsert the typed row (single active request per company is
        // enforced by the unique index). Previous responses on the row
        // are cleared — this is a fresh ask.
        \App\Models\CompanyInfoRequest::updateOrCreate(
            ['company_id' => $company->id],
            [
                'items'        => $items,
                'note'         => $note,
                'requested_at' => now(),
                'requested_by' => auth()->id(),
                'responded_at' => null,
                'responded_by' => null,
            ]
        );

        $this->audit(
            AuditAction::UPDATE,
            $company,
            ['info_request' => $before],
            ['info_request' => ['items' => $items, 'note' => $note]]
        );

        // Notify the company manager so they don't sit waiting in the dark.
        try {
            $managers = $company->users()->where('role', UserRole::COMPANY_MANAGER->value)->get();
            if ($managers->isNotEmpty()) {
                Notification::send($managers, new CompanyInfoRequestedNotification($company, $note));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', __('admin.companies.info_requested'));
    }

    /**
     * Cancel a previously-saved info request without changing the rest of
     * the company state. Useful if the admin changes their mind or fixed
     * the missing data themselves. Deletes the typed row outright — the
     * audit log entry written below preserves the history.
     */
    public function cancelInfoRequest(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $existing = $company->infoRequest;
        $before   = $existing ? ['items' => $existing->items, 'note' => $existing->note] : null;

        $existing?->delete();

        $this->audit(AuditAction::UPDATE, $company, ['info_request' => $before], ['info_request' => null]);

        return back()->with('status', __('admin.companies.info_request_cancelled'));
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
     * Promote a company to a specific verification tier (Bronze/Silver/Gold/
     * Platinum). The admin is the source of truth — they review the document
     * vault and decide. The badge then shows everywhere on the platform.
     */
    public function setVerificationLevel(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $data = $request->validate([
            'verification_level' => ['required', new \Illuminate\Validation\Rules\Enum(VerificationLevel::class)],
        ]);

        $before = ['verification_level' => $company->verification_level?->value];

        $company->update([
            'verification_level' => $data['verification_level'],
            'verified_by'        => auth()->id(),
            'verified_at'        => now(),
        ]);

        $this->audit(AuditAction::UPDATE, $company, $before, ['verification_level' => $data['verification_level']]);

        return back()->with('status', __('trust.level_updated'));
    }

    /**
     * Bulk re-screen every selected company against the sanctions provider.
     * Same bypass-cache behaviour as single re-screen — admin gets fresh
     * results. Any individual failure is logged and counted, but doesn't
     * abort the whole batch.
     */
    public function bulkRescreen(Request $request, SanctionsScreeningService $service): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $companies = Company::whereIn('id', $data['ids'])->get();
        $processed = 0;
        $hits      = 0;
        $errors    = 0;

        foreach ($companies as $company) {
            try {
                $screening = $service->screenCompany($company, auth()->id(), useCache: false);
                $processed++;
                if ($screening->result === 'hit') {
                    $hits++;
                }
                $this->audit(
                    AuditAction::UPDATE,
                    $company,
                    null,
                    ['sanctions_status' => $screening->result, 'match_count' => $screening->match_count],
                );
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return back()->with('status', __('trust.bulk_rescreen_summary', [
            'processed' => $processed,
            'hits'      => $hits,
            'errors'    => $errors,
        ]));
    }

    /**
     * Re-run the sanctions screening for a company on demand. Bypasses the
     * 24h cache so the admin always gets a fresh verdict from OpenSanctions.
     */
    public function rescreenSanctions(int $id, SanctionsScreeningService $service): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $screening = $service->screenCompany($company, auth()->id(), useCache: false);

        $this->audit(
            AuditAction::UPDATE,
            $company,
            null,
            ['sanctions_status' => $screening->result, 'match_count' => $screening->match_count]
        );

        return back()->with('status', __('trust.sanctions_rescreened', ['result' => $screening->result]));
    }

    /**
     * Mark a single uploaded document as verified or rejected. Verification
     * is a per-document decision because Gold tier needs multiple documents
     * verified — we don't want one bad doc to demote the whole company.
     */
    public function verifyDocument(Request $request, int $documentId): RedirectResponse
    {
        $document = CompanyDocument::findOrFail($documentId);

        $data = $request->validate([
            'action' => ['required', 'in:verify,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'verify') {
            $document->update([
                'status'           => CompanyDocument::STATUS_VERIFIED,
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
                'rejection_reason' => null,
            ]);
        } else {
            $document->update([
                'status'           => CompanyDocument::STATUS_REJECTED,
                'rejection_reason' => $data['reason'] ?? null,
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
            ]);
        }

        // Phase 2 / Sprint 8 / task 2.5 — after a verify action, ask the
        // verification service whether the company now qualifies for a
        // higher tier and bump it automatically.
        if ($data['action'] === 'verify') {
            try {
                app(\App\Services\VerificationService::class)
                    ->autoPromoteIfEligible($document->company, auth()->id());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', __('trust.doc_review_saved'));
    }

    /**
     * Phase 2 / Sprint 10 / task 2.14 — admin verify or reject an
     * uploaded insurance policy. Same shape as verifyDocument so the
     * verification queue UI can reuse the same modal pattern.
     */
    public function verifyInsurance(Request $request, int $insuranceId): RedirectResponse
    {
        $policy = \App\Models\CompanyInsurance::findOrFail($insuranceId);

        $data = $request->validate([
            'action' => ['required', 'in:verify,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'verify') {
            $policy->update([
                'status'           => \App\Models\CompanyInsurance::STATUS_VERIFIED,
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
                'rejection_reason' => null,
            ]);

            // A newly-verified insurance policy can unlock a Gold-tier
            // promotion, since the verification service treats insurance
            // as a hard requirement at that level.
            try {
                app(\App\Services\VerificationService::class)
                    ->autoPromoteIfEligible($policy->company, auth()->id());
            } catch (\Throwable $e) {
                report($e);
            }
        } else {
            $policy->update([
                'status'           => \App\Models\CompanyInsurance::STATUS_REJECTED,
                'rejection_reason' => $data['reason'] ?? null,
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
            ]);
        }

        return back()->with('status', __('insurances.review_saved'));
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
