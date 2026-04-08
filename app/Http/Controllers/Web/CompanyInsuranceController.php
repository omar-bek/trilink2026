<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanyInsurance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Manager-facing CRUD for company insurance policies. Phase 2 /
 * Sprint 10 / task 2.14.
 *
 * Lives next to the document vault and beneficial owners — all three
 * feed the verification queue. Status of an uploaded policy starts as
 * `pending` and is flipped by the admin from the verification queue.
 *
 * Phase 3 will replace the manual upload with an API integration to
 * the GCC insurer associations (Atradius / Coface for credit, Tawuniya
 * / NLG for cargo) so policies validate in real time.
 */
class CompanyInsuranceController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);

        $policies = CompanyInsurance::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total'    => $policies->count(),
            'verified' => $policies->where('status', CompanyInsurance::STATUS_VERIFIED)->count(),
            'pending'  => $policies->where('status', CompanyInsurance::STATUS_PENDING)->count(),
            'expired'  => $policies->filter(fn ($p) => $p->status === CompanyInsurance::STATUS_EXPIRED || ($p->expires_at && $p->expires_at->isPast()))->count(),
        ];

        return view('dashboard.insurances.index', compact('policies', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $request->validate([
            'type'            => ['required', 'string', 'in:' . implode(',', CompanyInsurance::TYPES)],
            'insurer'         => ['required', 'string', 'max:191'],
            'policy_number'   => ['required', 'string', 'max:128'],
            'coverage_amount' => ['required', 'numeric', 'min:0'],
            'currency'        => ['required', 'string', 'size:3'],
            'starts_at'       => ['required', 'date'],
            'expires_at'      => ['required', 'date', 'after:starts_at'],
            'file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store("company-insurances/{$user->company_id}", 'local');

        CompanyInsurance::create([
            'company_id'        => $user->company_id,
            'type'              => $data['type'],
            'insurer'           => $data['insurer'],
            'policy_number'     => $data['policy_number'],
            'coverage_amount'   => $data['coverage_amount'],
            'currency'          => $data['currency'],
            'starts_at'         => $data['starts_at'],
            'expires_at'        => $data['expires_at'],
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size'         => $file->getSize(),
            'mime_type'         => $file->getMimeType(),
            'status'            => CompanyInsurance::STATUS_PENDING,
            'uploaded_by'       => $user->id,
        ]);

        return redirect()
            ->route('dashboard.insurances.index')
            ->with('status', __('insurances.uploaded_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $policy = CompanyInsurance::where('company_id', $user->company_id)->findOrFail($id);

        if ($policy->file_path && Storage::disk('local')->exists($policy->file_path)) {
            Storage::disk('local')->delete($policy->file_path);
        }

        $policy->delete();

        return redirect()
            ->route('dashboard.insurances.index')
            ->with('status', __('insurances.deleted_successfully'));
    }
}
