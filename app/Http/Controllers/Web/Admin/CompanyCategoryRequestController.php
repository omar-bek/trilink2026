<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin review queue for category-assignment requests submitted by
 * company managers from their profile page.
 *
 * Why gated: a company's categories drive RFQ visibility for suppliers;
 * allowing self-assignment would let any company flood every buyer's
 * RFQ feed. Admin approval keeps the taxonomy meaningful.
 */
class CompanyCategoryRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', CompanyCategoryRequest::STATUS_PENDING);
        if (! in_array($status, [
            CompanyCategoryRequest::STATUS_PENDING,
            CompanyCategoryRequest::STATUS_APPROVED,
            CompanyCategoryRequest::STATUS_REJECTED,
        ], true)) {
            $status = CompanyCategoryRequest::STATUS_PENDING;
        }

        $requests = CompanyCategoryRequest::with(['company', 'category', 'requestedBy', 'reviewedBy'])
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'pending' => CompanyCategoryRequest::where('status', CompanyCategoryRequest::STATUS_PENDING)->count(),
            'approved' => CompanyCategoryRequest::where('status', CompanyCategoryRequest::STATUS_APPROVED)->count(),
            'rejected' => CompanyCategoryRequest::where('status', CompanyCategoryRequest::STATUS_REJECTED)->count(),
        ];

        return view('dashboard.admin.category-requests.index', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $req = CompanyCategoryRequest::findOrFail($id);

        abort_unless($req->isPending(), 422, __('admin.category_requests.already_reviewed'));

        $company = $req->company;

        $before = ['status' => $req->status];

        $company->categories()->syncWithoutDetaching([$req->category_id]);

        $req->update([
            'status' => CompanyCategoryRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $company->id,
            'action' => AuditAction::APPROVE->value,
            'resource_type' => 'CompanyCategoryRequest',
            'resource_id' => $req->id,
            'before' => $before,
            'after' => ['status' => $req->status, 'category_id' => $req->category_id],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'status' => 'success',
        ]);

        return back()->with('status', __('admin.category_requests.approved'));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $req = CompanyCategoryRequest::findOrFail($id);

        abort_unless($req->isPending(), 422, __('admin.category_requests.already_reviewed'));

        $before = ['status' => $req->status];

        $req->update([
            'status' => CompanyCategoryRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $data['reason'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => $req->company_id,
            'action' => AuditAction::REJECT->value,
            'resource_type' => 'CompanyCategoryRequest',
            'resource_id' => $req->id,
            'before' => $before,
            'after' => ['status' => $req->status, 'reason' => $req->rejection_reason],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'status' => 'success',
        ]);

        return back()->with('status', __('admin.category_requests.rejected'));
    }
}
