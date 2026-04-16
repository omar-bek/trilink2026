<?php

namespace App\Http\Controllers\Web;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\CompanyDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Manager-facing CRUD for the company's Document Vault. Each row is a real
 * file on disk (private storage), with status moved by an admin reviewer.
 *
 * The verification tier on the parent company is updated by AdminCompanyController
 * when an admin promotes the company — this controller just owns the upload
 * lifecycle.
 */
class CompanyDocumentController extends Controller
{
    public function index(): View
    {
        $companyId = auth()->user()->company_id;
        abort_unless($companyId, 403);

        $documents = CompanyDocument::with(['verifiedBy', 'uploadedBy'])
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        // Phase 0 / task 0.7 — compliance summary used by the Documents
        // dashboard. The expiry status is purely a function of expires_at:
        //   - Valid          : verified and > 30 days from expiry (or no expiry)
        //   - Expiring Soon  : verified and within 30 days of expiry
        //   - Expired        : status flipped to expired by the daily job
        $stats = [
            'total'    => $documents->count(),
            'verified' => $documents->where('status', CompanyDocument::STATUS_VERIFIED)->count(),
            'pending'  => $documents->where('status', CompanyDocument::STATUS_PENDING)->count(),
            'expiring' => $documents->filter(fn (CompanyDocument $d) => $d->status === CompanyDocument::STATUS_VERIFIED && $d->isExpiringSoon())->count(),
            'expired'  => $documents->filter(fn (CompanyDocument $d) => $d->status === CompanyDocument::STATUS_EXPIRED || $d->isExpired())->count(),
        ];

        $types = DocumentType::cases();

        return view('dashboard.documents.index', compact('documents', 'types', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $request->validate([
            'type'       => ['required', 'string', new \Illuminate\Validation\Rules\Enum(DocumentType::class)],
            'label'      => ['nullable', 'string', 'max:191'],
            'file'       => ['required', 'file', 'max:10240', ...\App\Rules\SafeUpload::pdfOrImage()],
            'issued_at'  => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $file = $request->file('file');
        // Store under a per-company folder, private disk. Filename is hashed
        // to avoid collisions; the original is preserved on the row.
        $path = $file->store("company-documents/{$user->company_id}", 'local');

        CompanyDocument::create([
            'company_id'        => $user->company_id,
            'type'              => $data['type'],
            'label'             => $data['label'] ?? null,
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size'         => $file->getSize(),
            'mime_type'         => $file->getMimeType(),
            'status'            => CompanyDocument::STATUS_PENDING,
            'issued_at'         => $data['issued_at'] ?? null,
            'expires_at'        => $data['expires_at'] ?? null,
            'uploaded_by'       => $user->id,
        ]);

        return redirect()
            ->route('dashboard.documents.index')
            ->with('status', __('trust.uploaded_successfully'));
    }

    public function download(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = auth()->user();
        $doc  = CompanyDocument::where('company_id', $user->company_id)->findOrFail($id);

        abort_unless(
            $doc->file_path && Storage::disk('local')->exists($doc->file_path),
            404,
            __('trust.file_not_found')
        );

        return Storage::disk('local')->download(
            $doc->file_path,
            $doc->original_filename ?? basename($doc->file_path)
        );
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $doc  = CompanyDocument::where('company_id', $user->company_id)->findOrFail($id);

        if ($doc->file_path && Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        $doc->delete();

        return redirect()
            ->route('dashboard.documents.index')
            ->with('status', __('trust.deleted_successfully'));
    }

    /**
     * Phase 2 / Sprint 9 / task 2.11 — renew an expiring/expired document.
     *
     * The manager re-uploads a fresh copy plus the new expiry date. The
     * old file is replaced (and physically deleted from disk to avoid
     * stale binaries piling up), the row flips back to `pending`, and
     * the `verified_at` / `verified_by` audit trail is wiped so the
     * admin re-reviews from scratch.
     */
    public function renew(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $doc = CompanyDocument::where('company_id', $user->company_id)->findOrFail($id);

        $data = $request->validate([
            'file'       => ['required', 'file', 'max:10240', ...\App\Rules\SafeUpload::pdfOrImage()],
            'issued_at'  => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        // Replace the file on disk so we don't accumulate orphan binaries
        // for expired versions of the same document.
        if ($doc->file_path && Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        $file = $request->file('file');
        $path = $file->store("company-documents/{$user->company_id}", 'local');

        $doc->update([
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size'         => $file->getSize(),
            'mime_type'         => $file->getMimeType(),
            'status'            => CompanyDocument::STATUS_PENDING,
            'issued_at'         => $data['issued_at'] ?? null,
            'expires_at'        => $data['expires_at'] ?? null,
            'rejection_reason'  => null,
            'verified_by'       => null,
            'verified_at'       => null,
            'uploaded_by'       => $user->id,
        ]);

        return redirect()
            ->route('dashboard.documents.index')
            ->with('status', __('trust.renewed_successfully'));
    }
}
