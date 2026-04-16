<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 8 (UAE Compliance Roadmap) — admin-side verification queue for
 * Tier 3 compliance certificates (CoO, ECAS, Halal, GSO, ISO). The
 * admin downloads and inspects the uploaded PDF, then approves or
 * rejects with a reason.
 */
class CertificateUploadAdminController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('company.verify'), 403);

        $query = CertificateUpload::with(['company:id,name,registration_number', 'uploader', 'verifier'])
            ->orderBy('status')
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('certificate_type', $type);
        }

        if ($q = $request->query('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('certificate_number', 'like', "%{$q}%")
                    ->orWhere('issuer', 'like', "%{$q}%")
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        $certificates = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => CertificateUpload::where('status', CertificateUpload::STATUS_PENDING)->count(),
            'verified' => CertificateUpload::where('status', CertificateUpload::STATUS_VERIFIED)->count(),
            'rejected' => CertificateUpload::where('status', CertificateUpload::STATUS_REJECTED)->count(),
            'expired' => CertificateUpload::where('status', CertificateUpload::STATUS_EXPIRED)->count(),
        ];

        return view('dashboard.admin.certificate-uploads.index', [
            'certificates' => $certificates,
            'stats' => $stats,
            'filters' => [
                'q' => $request->query('q'),
                'status' => $request->query('status'),
                'type' => $request->query('type'),
            ],
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('company.verify'), 403);

        $cert = CertificateUpload::findOrFail($id);

        if ($cert->status !== CertificateUpload::STATUS_PENDING) {
            return back()->withErrors(['status' => __('cert_upload.only_pending_can_be_reviewed')]);
        }

        $cert->update([
            'status' => CertificateUpload::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('status', __('cert_upload.verified_successfully'));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('company.verify'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $cert = CertificateUpload::findOrFail($id);

        if ($cert->status !== CertificateUpload::STATUS_PENDING) {
            return back()->withErrors(['status' => __('cert_upload.only_pending_can_be_reviewed')]);
        }

        $cert->update([
            'status' => CertificateUpload::STATUS_REJECTED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);

        return back()->with('status', __('cert_upload.rejected_successfully'));
    }

    public function download(Request $request, int $id): StreamedResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('company.verify'), 403);

        $cert = CertificateUpload::findOrFail($id);

        if (! $cert->file_path || ! Storage::disk('local')->exists($cert->file_path)) {
            return back()->withErrors(['file' => __('cert_upload.file_missing')]);
        }

        return Storage::disk('local')->download(
            $cert->file_path,
            ($cert->original_filename ?: ('cert-'.$cert->id.'.pdf'))
        );
    }
}
