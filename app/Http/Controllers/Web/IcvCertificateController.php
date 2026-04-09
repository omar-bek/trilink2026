<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IcvCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 4 (UAE Compliance Roadmap) — supplier-side ICV certificate
 * management. The supplier uploads their MoIAT/ADNOC/etc. certificate
 * along with the published score, an admin verifies, and bid evaluation
 * picks it up automatically (see {@see \App\Services\Procurement\IcvScoringService}).
 *
 * Admin-side verification (approve / reject) lives in
 * {@see \App\Http\Controllers\Web\Admin\IcvCertificateAdminController}
 * — kept in a separate controller so the supplier-facing routes stay
 * focused on self-service.
 */
class IcvCertificateController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $certificates = IcvCertificate::with(['verifier'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('issued_date')
            ->get();

        $stats = [
            'total'    => $certificates->count(),
            'active'   => $certificates->filter(fn (IcvCertificate $c) => $c->isActive())->count(),
            'pending'  => $certificates->where('status', IcvCertificate::STATUS_PENDING)->count(),
            'expired'  => $certificates->filter(fn (IcvCertificate $c) => $c->isExpired())->count(),
            'best'     => $certificates
                ->filter(fn (IcvCertificate $c) => $c->isActive())
                ->map(fn (IcvCertificate $c) => (float) $c->score)
                ->max(),
        ];

        return view('dashboard.icv-certificates.index', compact('certificates', 'stats'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->company_id, 403);
        return view('dashboard.icv-certificates.upload', [
            'issuers' => IcvCertificate::ALL_ISSUERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $request->validate([
            'issuer'             => ['required', 'string', \Illuminate\Validation\Rule::in(IcvCertificate::ALL_ISSUERS)],
            'certificate_number' => ['required', 'string', 'max:64'],
            'score'              => ['required', 'numeric', 'min:0', 'max:100'],
            'issued_date'        => ['required', 'date', 'before_or_equal:today'],
            'expires_date'       => ['required', 'date', 'after:issued_date'],
            'file'               => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        // Reject duplicate uploads of the same certificate (same
        // company / issuer / number tuple) — saves the admin from
        // verifying the same row twice.
        $existing = IcvCertificate::where('company_id', $user->company_id)
            ->where('issuer', $data['issuer'])
            ->where('certificate_number', $data['certificate_number'])
            ->whereNull('deleted_at')
            ->first();
        if ($existing) {
            return back()->withErrors([
                'certificate_number' => __('icv.duplicate_certificate'),
            ])->withInput();
        }

        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());
        $sha = hash('sha256', $bytes);
        $path = $file->store("icv-certificates/{$user->company_id}", 'local');

        IcvCertificate::create([
            'company_id'         => $user->company_id,
            'issuer'             => $data['issuer'],
            'certificate_number' => $data['certificate_number'],
            'score'              => $data['score'],
            'issued_date'        => $data['issued_date'],
            'expires_date'       => $data['expires_date'],
            'file_path'          => $path,
            'file_sha256'        => $sha,
            'file_size'          => $file->getSize(),
            'original_filename'  => $file->getClientOriginalName(),
            'status'             => IcvCertificate::STATUS_PENDING,
            'uploaded_by'        => $user->id,
        ]);

        return redirect()
            ->route('dashboard.icv-certificates.index')
            ->with('status', __('icv.uploaded_pending_review'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $cert = IcvCertificate::where('company_id', $user->company_id)->findOrFail($id);

        // Suppliers can only delete certificates that are still pending —
        // once an admin has verified or rejected one, removing the row
        // would erase the audit trail.
        if ($cert->status !== IcvCertificate::STATUS_PENDING) {
            return back()->withErrors(['delete' => __('icv.cannot_delete_after_review')]);
        }

        if ($cert->file_path && Storage::disk('local')->exists($cert->file_path)) {
            Storage::disk('local')->delete($cert->file_path);
        }
        $cert->delete();

        return back()->with('status', __('icv.deleted_successfully'));
    }

    public function download(Request $request, int $id): StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        $cert = IcvCertificate::where('company_id', $user->company_id)->findOrFail($id);

        if (!$cert->file_path || !Storage::disk('local')->exists($cert->file_path)) {
            return back()->withErrors(['file' => __('icv.file_missing')]);
        }

        return Storage::disk('local')->download(
            $cert->file_path,
            ($cert->original_filename ?: ('icv-' . $cert->id . '.pdf'))
        );
    }
}
