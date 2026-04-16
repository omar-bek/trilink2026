<?php

namespace App\Http\Controllers\Web;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\FreeZoneAuthority;
use App\Enums\LegalJurisdiction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyCategoryRequest;
use App\Models\CompanyDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Manager-facing Company Profile.
 *
 * One unified page that shows everything about a company — identity,
 * legal & compliance fields, document vault, insurance policies, ICV
 * certificates, bank details, beneficial owners, branches, team and
 * activity counters — all in one place.
 *
 * The same blade view is reused by the admin side via
 * {@see \App\Http\Controllers\Web\Admin\CompanyController::profile()},
 * so the visual layout stays in sync between the two surfaces. The
 * controller passes a $mode flag ('manager' or 'admin') and the view
 * conditionally shows the admin-only review controls.
 *
 * Editing rules:
 *   - Manager  : may edit identity + contact + description + free
 *                zone classification, but NEVER status / verification
 *                level / company type. Those are admin-only.
 *   - Admin    : may edit everything via the existing admin controller
 *                (this profile page links over to it for the deeper
 *                forms — verify document, set verification level …).
 */
class CompanyProfileController extends Controller
{
    /**
     * Show the manager's own company profile. The manager's company id
     * comes off the auth user; suppliers, buyers and service providers
     * all share the same view because the profile is data-driven.
     */
    public function show(): View
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);

        // Permission gate: every company-attached user with team.view
        // can read the profile. Editing is gated separately on the
        // update endpoint with team.edit (the existing permission used
        // by team management — keeps profile edit access aligned with
        // who can manage their company on the platform).
        abort_unless($user->hasPermission('team.view'), 403);

        $company = Company::with([
            'users'           => fn ($q) => $q->orderBy('id'),
            'categories',
            'bankDetails',
            'beneficialOwners',
            'infoRequest',
        ])
            ->withCount(['purchaseRequests', 'rfqs', 'bids', 'buyerContracts', 'payments'])
            ->findOrFail($user->company_id);

        return view('dashboard.company.profile', $this->buildViewData($company, mode: 'manager'));
    }

    /**
     * Manager submits a request to add a new category to their company.
     * The request lands in admin's review queue; on approval, the
     * category is attached to the company via the company_category pivot.
     *
     * Reason for the approval loop: categories drive RFQ visibility for
     * suppliers — letting any manager self-assign arbitrary categories
     * would let a company spam itself into every RFQ feed.
     */
    public function requestCategory(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);
        abort_unless($user->hasPermission('team.edit'), 403);

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);

        $companyId  = $user->company_id;
        $categoryId = (int) $data['category_id'];

        if (Company::find($companyId)?->categories()->where('categories.id', $categoryId)->exists()) {
            return back()->withErrors(['category_id' => __('company_profile.category_already_assigned')]);
        }

        $alreadyPending = CompanyCategoryRequest::where('company_id', $companyId)
            ->where('category_id', $categoryId)
            ->where('status', CompanyCategoryRequest::STATUS_PENDING)
            ->exists();

        if ($alreadyPending) {
            return back()->withErrors(['category_id' => __('company_profile.category_request_already_pending')]);
        }

        CompanyCategoryRequest::create([
            'company_id'   => $companyId,
            'category_id'  => $categoryId,
            'requested_by' => $user->id,
            'note'         => $data['note'] ?? null,
            'status'       => CompanyCategoryRequest::STATUS_PENDING,
        ]);

        return redirect()
            ->route('dashboard.company.profile')
            ->with('status', __('company_profile.category_request_submitted'));
    }

    /**
     * Manager cancels their own still-pending category request. Only
     * allowed while the request is pending — approved / rejected
     * requests stay in history for audit.
     */
    public function cancelCategoryRequest(int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);
        abort_unless($user->hasPermission('team.edit'), 403);

        $req = CompanyCategoryRequest::where('company_id', $user->company_id)
            ->where('status', CompanyCategoryRequest::STATUS_PENDING)
            ->findOrFail($id);

        $req->delete();

        return redirect()
            ->route('dashboard.company.profile')
            ->with('status', __('company_profile.category_request_cancelled'));
    }

    /**
     * Update the editable subset of the company's profile. The manager
     * can change identity / contact / description / free-zone fields
     * but NEVER status, verification level, or company type — those
     * are admin-controlled and changing them on the supplier side
     * would let a manager self-promote.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);
        abort_unless($user->hasPermission('team.edit'), 403);

        $company = Company::findOrFail($user->company_id);

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'name_ar'             => ['nullable', 'string', 'max:255'],
            'tax_number'          => ['nullable', 'string', 'max:100'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'website'             => ['nullable', 'url', 'max:255'],
            'address'             => ['nullable', 'string', 'max:1000'],
            'city'                => ['nullable', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:100'],
            'description'         => ['nullable', 'string', 'max:5000'],

            // Phase 3 (UAE Compliance Roadmap) free-zone classification.
            // Managers can set their own free-zone authority because
            // it drives downstream VAT and clause selection — keeping
            // it admin-only would block legitimate self-service.
            'is_free_zone'        => ['nullable', 'boolean'],
            'free_zone_authority' => ['nullable', 'string', 'max:32'],
            'legal_jurisdiction'  => ['nullable', 'string', 'max:16'],
        ]);

        // Snapshot for the audit log so we can show a before/after diff.
        $before = $company->only(array_keys($data));

        // Coerce the boolean explicitly — HTML forms send "1"/"0" or
        // omit the key entirely, and we want a real bool either way.
        $data['is_free_zone'] = (bool) ($data['is_free_zone'] ?? false);

        // is_designated_zone is derived from the free zone authority,
        // not set by the user — keeps the two columns in sync.
        if ($data['is_free_zone'] && !empty($data['free_zone_authority'])) {
            $zone = FreeZoneAuthority::tryFrom($data['free_zone_authority']);
            $data['is_designated_zone'] = $zone?->isDesignated() ?? false;
        } else {
            $data['free_zone_authority'] = null;
            $data['is_designated_zone']  = false;
        }

        // Validate the legal jurisdiction against the enum. An unknown
        // value silently falls back to FEDERAL so the column never
        // ends up holding garbage even if the form is tampered with.
        if (!empty($data['legal_jurisdiction'])) {
            $data['legal_jurisdiction'] = LegalJurisdiction::tryFrom($data['legal_jurisdiction'])?->value
                ?? LegalJurisdiction::FEDERAL->value;
        } else {
            $data['legal_jurisdiction'] = LegalJurisdiction::FEDERAL->value;
        }

        $company->update($data);

        AuditLog::create([
            'user_id'       => $user->id,
            'company_id'    => $company->id,
            'action'        => AuditAction::UPDATE->value,
            'resource_type' => 'Company',
            'resource_id'   => $company->id,
            'before'        => $before,
            'after'         => $company->only(array_keys($before)),
            'ip_address'    => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 255),
            'status'        => 'success',
        ]);

        return redirect()
            ->route('dashboard.company.profile')
            ->with('status', __('company_profile.updated'));
    }

    /**
     * Upload BOTH the authorised signature image and the company
     * stamp/seal in a single request. Both files are stored on the
     * public disk so the contract show page and the PDF render can
     * reach them via a normal asset URL.
     *
     * The contract sign endpoint refuses to proceed unless both files
     * exist, which is why this endpoint is the gate the contract page
     * pushes users to via an inline modal when either is missing.
     *
     * Either file is optional in this request — the endpoint partially
     * updates whichever ones are provided so the user can re-upload
     * just the stamp without re-uploading the signature.
     *
     * Redirect target is the URL the form's hidden `redirect_to` field
     * points at when present (so the user lands back on the contract
     * page they came from), falling back to the company profile.
     */
    public function uploadSignature(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);
        abort_unless($user->hasPermission('team.edit'), 403);

        $request->validate([
            'signature'   => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'stamp'       => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'redirect_to' => ['nullable', 'string', 'max:500'],
        ]);

        // Reject empty submissions — there's nothing to do and a silent
        // success would mislead the user into thinking they uploaded
        // when they didn't.
        if (!$request->hasFile('signature') && !$request->hasFile('stamp')) {
            return back()->withErrors([
                'signature' => __('company_profile.signature_required'),
            ]);
        }

        $company = Company::findOrFail($user->company_id);
        $updates = [];

        if ($request->hasFile('signature')) {
            // Drop the previous signature so the public disk doesn't
            // accumulate orphan images on every re-upload.
            if ($company->signature_path && Storage::disk('public')->exists($company->signature_path)) {
                Storage::disk('public')->delete($company->signature_path);
            }
            $updates['signature_path'] = $request->file('signature')
                ->store("company-signatures/{$company->id}", 'public');
        }

        if ($request->hasFile('stamp')) {
            if ($company->stamp_path && Storage::disk('public')->exists($company->stamp_path)) {
                Storage::disk('public')->delete($company->stamp_path);
            }
            $updates['stamp_path'] = $request->file('stamp')
                ->store("company-stamps/{$company->id}", 'public');
        }

        $company->update($updates);

        AuditLog::create([
            'user_id'       => $user->id,
            'company_id'    => $company->id,
            'action'        => AuditAction::UPDATE->value,
            'resource_type' => 'Company',
            'resource_id'   => $company->id,
            'before'        => null,
            'after'         => array_keys($updates),
            'ip_address'    => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 255),
            'status'        => 'success',
        ]);

        // Honour the form's redirect_to so the user lands back on the
        // page they came from (typically a contract show page).
        $target = $request->input('redirect_to');
        if (is_string($target) && $target !== '' && str_starts_with($target, '/')) {
            return redirect($target)->with('status', __('company_profile.signature_uploaded'));
        }

        return redirect()
            ->route('dashboard.company.profile')
            ->with('status', __('company_profile.signature_uploaded'));
    }

    /**
     * Replace the company logo. Stored on the public disk so the
     * sidebar and the public supplier directory can reach it via a
     * normal asset URL — there's nothing sensitive about a logo.
     */
    public function uploadLogo(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);
        abort_unless($user->hasPermission('team.edit'), 403);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $company = Company::findOrFail($user->company_id);

        // Drop the previous file so the public disk doesn't accumulate
        // orphan logos every time the manager re-uploads.
        if ($company->logo && Storage::disk('public')->exists($company->logo)) {
            Storage::disk('public')->delete($company->logo);
        }

        $path = $request->file('logo')->store("company-logos/{$company->id}", 'public');
        $company->update(['logo' => $path]);

        return redirect()
            ->route('dashboard.company.profile')
            ->with('status', __('company_profile.logo_updated'));
    }

    /**
     * Build the array passed to the profile blade. Extracted into a
     * helper because the admin and the cross-company surfaces both
     * call into it — we want every caller to receive an identical
     * shape so the blade stays simple and data-driven.
     *
     * Mode controls how much of the company is exposed:
     *
     *   - 'manager' : the user's own company. Full data + edit form.
     *   - 'admin'   : platform admin viewing any company. Full data
     *                 + verify/reject controls + verification level
     *                 selector + status actions.
     *   - 'public'  : a user from ANOTHER company viewing this one.
     *                 Read-only, with sensitive sections (bank
     *                 details, beneficial owners, full document
     *                 file paths) hidden. Only VERIFIED documents
     *                 are returned so the public surface can never
     *                 leak a pending or rejected upload.
     *
     * @return array<string, mixed>
     */
    public static function buildViewData(Company $company, string $mode): array
    {
        $isPublic = $mode === 'public';

        // Eager-load anything the parent controller might have skipped.
        // Idempotent — loadMissing only fires for relations that aren't
        // already hydrated. Bank details + beneficial owners are still
        // loaded in public mode so the count is accurate, but the
        // blade gates the actual rendering.
        $company->loadMissing([
            'users'             => fn ($q) => $q->orderBy('id'),
            'categories',
            'bankDetails',
            'beneficialOwners',
            'infoRequest',
        ]);

        $documents = CompanyDocument::query()
            ->with(['verifiedBy:id,first_name,last_name,email', 'uploadedBy:id,first_name,last_name,email'])
            ->where('company_id', $company->id)
            ->when($isPublic, fn ($q) => $q->where('status', CompanyDocument::STATUS_VERIFIED))
            ->latest()
            ->get();

        // CompanyInsurance has its own model with verification status —
        // pulled separately so the page can show one row per policy
        // with its own verify/reject controls (mirrors documents). In
        // public mode only verified policies are exposed.
        $insurances = \App\Models\CompanyInsurance::query()
            ->with(['verifiedBy:id,first_name,last_name,email'])
            ->where('company_id', $company->id)
            ->when($isPublic, fn ($q) => $q->where('status', \App\Models\CompanyInsurance::STATUS_VERIFIED))
            ->latest()
            ->get();

        $icvCertificates = \App\Models\IcvCertificate::query()
            ->with(['verifier:id,first_name,last_name,email'])
            ->where('company_id', $company->id)
            ->when($isPublic, fn ($q) => $q->where('status', \App\Models\IcvCertificate::STATUS_VERIFIED))
            ->latest()
            ->get();

        $certificateUploads = \App\Models\CertificateUpload::query()
            ->with(['verifier:id,first_name,last_name,email'])
            ->where('company_id', $company->id)
            ->when($isPublic, fn ($q) => $q->where('status', \App\Models\CertificateUpload::STATUS_VERIFIED))
            ->latest()
            ->get();

        $branches = \App\Models\Branch::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        // Activity counters — preferred from withCount on the parent
        // company query if available, otherwise computed inline.
        $activity = [
            'purchase_requests' => $company->purchase_requests_count
                ?? $company->purchaseRequests()->count(),
            'rfqs'              => $company->rfqs_count
                ?? $company->rfqs()->count(),
            'bids'              => $company->bids_count
                ?? $company->bids()->count(),
            'contracts'         => $company->buyer_contracts_count
                ?? $company->buyerContracts()->count(),
            'payments'          => $company->payments_count
                ?? $company->payments()->count(),
        ];

        // Document vault stats for the header KPI strip. In public
        // mode the only meaningful number is the verified count, so
        // we collapse pending/rejected/expiring to zero — the public
        // viewer doesn't need (or get) to see them.
        $docStats = [
            'total'    => $documents->count(),
            'verified' => $documents->where('status', CompanyDocument::STATUS_VERIFIED)->count(),
            'pending'  => $isPublic ? 0 : $documents->where('status', CompanyDocument::STATUS_PENDING)->count(),
            'rejected' => $isPublic ? 0 : $documents->where('status', CompanyDocument::STATUS_REJECTED)->count(),
            'expiring' => $isPublic ? 0 : $documents->filter(
                fn (CompanyDocument $d) => $d->status === CompanyDocument::STATUS_VERIFIED && $d->isExpiringSoon()
            )->count(),
        ];

        $manager = $company->users->firstWhere('role', UserRole::COMPANY_MANAGER);

        $assignedCategoryIds = $company->categories->pluck('id')->all();

        $pendingCategoryRequests = $isPublic
            ? collect()
            : CompanyCategoryRequest::with('category')
                ->where('company_id', $company->id)
                ->whereIn('status', [
                    CompanyCategoryRequest::STATUS_PENDING,
                    CompanyCategoryRequest::STATUS_REJECTED,
                ])
                ->latest()
                ->get();

        $pendingCategoryIds = $pendingCategoryRequests
            ->where('status', CompanyCategoryRequest::STATUS_PENDING)
            ->pluck('category_id')
            ->all();

        $availableCategories = ($mode === 'manager')
            ? Category::query()
                ->where('is_active', true)
                ->whereNotIn('id', array_merge($assignedCategoryIds, $pendingCategoryIds))
                ->orderBy('path')
                ->get()
            : collect();

        return [
            'mode'             => $mode,
            'company'          => $company,
            'manager'          => $manager,
            'documents'        => $documents,
            'insurances'       => $insurances,
            'icvCertificates'      => $icvCertificates,
            'certificateUploads'   => $certificateUploads,
            'branches'             => $branches,
            'activity'         => $activity,
            'docStats'         => $docStats,
            'documentTypes'    => DocumentType::cases(),
            'freeZones'        => FreeZoneAuthority::cases(),
            'jurisdictions'    => LegalJurisdiction::cases(),
            'pendingCategoryRequests' => $pendingCategoryRequests,
            'availableCategories'     => $availableCategories,
        ];
    }
}
