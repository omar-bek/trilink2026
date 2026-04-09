<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\PrivacyRequest;
use App\Services\Privacy\ConsentLedger;
use App\Services\Privacy\DataErasureService;
use App\Services\Privacy\DataExportService;
use App\Jobs\ExecutePrivacyErasureJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 2 (UAE Compliance Roadmap) — PDPL public + dashboard surfaces.
 *
 * Public:
 *   - GET /privacy                     → bilingual privacy policy
 *   - GET /data-processing-agreement   → DPA template (B2B downloadable)
 *   - POST /privacy/cookies            → record cookie consent (no auth)
 *
 * Dashboard (auth required):
 *   - GET    /dashboard/privacy                → privacy hub (consents + requests)
 *   - POST   /dashboard/privacy/export         → request a data export (DSAR)
 *   - GET    /dashboard/privacy/export/{id}/download → download the archive
 *   - POST   /dashboard/privacy/erasure        → schedule erasure
 *   - POST   /dashboard/privacy/erasure/{id}/cancel → cancel pending erasure
 *   - POST   /dashboard/privacy/consents/{type}/grant   → re-grant after withdraw
 *   - POST   /dashboard/privacy/consents/{type}/withdraw → withdraw a consent
 */
class PrivacyController extends Controller
{
    public function __construct(
        private readonly ConsentLedger $consents,
        private readonly DataExportService $exportService,
        private readonly DataErasureService $erasureService,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────
    //  Public surfaces
    // ─────────────────────────────────────────────────────────────────

    public function showPolicy(): View
    {
        return view('public.privacy-policy', [
            'version'        => config('data_residency.privacy_policy_version'),
            'region'         => config('data_residency.region'),
            'adequacy_basis' => config('data_residency.adequacy_basis'),
            'sub_processors' => config('data_residency.sub_processors', []),
            'dpo'            => config('data_residency.dpo'),
        ]);
    }

    public function showDpa(): View
    {
        return view('public.dpa', [
            'version'        => config('data_residency.dpa_version'),
            'sub_processors' => config('data_residency.sub_processors', []),
            'dpo'            => config('data_residency.dpo'),
        ]);
    }

    /**
     * Record cookie consent from the cookie banner. Works for both
     * authenticated and anonymous visitors — anonymous consent goes
     * into a session-scoped marker (handled in the blade banner JS),
     * while logged-in users get a real Consent ledger row.
     */
    public function recordCookieConsent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'analytics' => ['nullable', 'boolean'],
        ]);

        if (auth()->check()) {
            $version = (string) config('data_residency.privacy_policy_version', '1.0');
            // Essential cookies are always granted (necessary for the
            // session to function — see PDPL Article 5(1)(b) — necessary
            // for the performance of a contract).
            $this->consents->grant(auth()->user(), Consent::TYPE_COOKIES_ESSENTIAL, $version);

            if (!empty($data['analytics'])) {
                $this->consents->grant(auth()->user(), Consent::TYPE_COOKIES_ANALYTICS, $version);
            } else {
                $this->consents->withdraw(auth()->user(), Consent::TYPE_COOKIES_ANALYTICS);
            }
        }

        // Always set a session marker so the banner doesn't reappear.
        $request->session()->put('cookie_consent_recorded', true);

        return back()->with('status', __('privacy.cookie_consent_recorded'));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Authenticated dashboard
    // ─────────────────────────────────────────────────────────────────

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        $consentLedger = $this->consents->ledgerFor($user);
        $activeConsents = $consentLedger
            ->filter(fn (Consent $c) => $c->isActive())
            ->keyBy('consent_type');

        $requests = PrivacyRequest::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Surface erasure blockers up-front so the user knows what is
        // standing in the way before they even click the button.
        $erasureBlockers = $this->erasureService->findBlockers($user);

        return view('dashboard.privacy.index', [
            'activeConsents'    => $activeConsents,
            'consentLedger'     => $consentLedger,
            'requests'          => $requests,
            'erasureBlockers'   => $erasureBlockers,
            'dataResidency'     => [
                'region'         => config('data_residency.region'),
                'adequacy_basis' => config('data_residency.adequacy_basis'),
                'sub_processors' => config('data_residency.sub_processors', []),
                'dpo'            => config('data_residency.dpo'),
            ],
        ]);
    }

    public function requestExport(Request $request): RedirectResponse
    {
        $user = $request->user();

        $privacyRequest = PrivacyRequest::create([
            'user_id'      => $user->id,
            'request_type' => PrivacyRequest::TYPE_DATA_EXPORT,
            'status'       => PrivacyRequest::STATUS_PENDING,
            'requested_at' => now(),
            'scheduled_for'=> now()->addDays(30),
        ]);

        // Build inline — DSAR archives are small (~few MB) and the
        // service is cheap. Larger platforms would dispatch a job here
        // and let the queue worker upload to S3, but for Phase 2 the
        // sync flow gives the user instant gratification.
        try {
            $zipPath = $this->exportService->buildArchive($user, $privacyRequest);

            $privacyRequest->update([
                'status'       => PrivacyRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'fulfillment_metadata' => [
                    'archive_path' => $zipPath,
                    'archive_size' => Storage::disk('local')->size($zipPath),
                ],
            ]);
        } catch (\Throwable $e) {
            $privacyRequest->update([
                'status'           => PrivacyRequest::STATUS_REJECTED,
                'rejection_reason' => 'Archive build failed: ' . $e->getMessage(),
            ]);
            return back()->withErrors(['export' => __('privacy.export_failed')]);
        }

        return redirect()
            ->route('dashboard.privacy.index')
            ->with('status', __('privacy.export_ready'));
    }

    public function downloadExport(Request $request, int $id): StreamedResponse|RedirectResponse
    {
        $user = $request->user();

        $privacyRequest = PrivacyRequest::where('user_id', $user->id)->findOrFail($id);

        if ($privacyRequest->request_type !== PrivacyRequest::TYPE_DATA_EXPORT) {
            abort(404);
        }

        $path = $privacyRequest->fulfillment_metadata['archive_path'] ?? null;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return back()->withErrors(['export' => __('privacy.archive_missing')]);
        }

        return Storage::disk('local')->download(
            $path,
            sprintf('trilink-data-export-%d.zip', $user->id),
            ['Content-Type' => 'application/zip']
        );
    }

    public function requestErasure(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $privacyRequest = $this->erasureService->scheduleErasure($user);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['erasure' => $e->getMessage()]);
        }

        // Dispatch the job with the cooling-period delay. The user can
        // still cancel any time before the job actually executes.
        ExecutePrivacyErasureJob::dispatch($privacyRequest->id)
            ->delay(now()->addDays(30));

        return redirect()
            ->route('dashboard.privacy.index')
            ->with('status', __('privacy.erasure_scheduled'));
    }

    public function cancelErasure(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $privacyRequest = PrivacyRequest::where('user_id', $user->id)->findOrFail($id);

        if (!$privacyRequest->isErasure()) {
            abort(404);
        }

        $this->erasureService->cancel($privacyRequest);

        return back()->with('status', __('privacy.erasure_cancelled'));
    }

    public function grantConsent(Request $request, string $type): RedirectResponse
    {
        if (!in_array($type, Consent::ALL_TYPES, true)) {
            abort(404);
        }

        $version = (string) config('data_residency.privacy_policy_version', '1.0');
        $this->consents->grant($request->user(), $type, $version);

        return back()->with('status', __('privacy.consent_granted'));
    }

    public function withdrawConsent(Request $request, string $type): RedirectResponse
    {
        if (!in_array($type, Consent::ALL_TYPES, true)) {
            abort(404);
        }

        $this->consents->withdraw($request->user(), $type);

        return back()->with('status', __('privacy.consent_withdrawn'));
    }
}
