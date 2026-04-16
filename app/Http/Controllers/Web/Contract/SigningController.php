<?php

namespace App\Http\Controllers\Web\Contract;

use App\Enums\AmendmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesContract;
use App\Models\Company;
use App\Models\ContractAmendment;
use App\Services\ContractService;
use App\Services\Signing\UaePassProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract signing actions — both the password-reauth ("standard") flow
 * and the UAE Pass OAuth flow that lifts the signature to the Advanced
 * grade under Federal Decree-Law 46/2021 Article 18.
 *
 * Extracted from ContractController so the security-critical signing path
 * can be reviewed in isolation. All actions go through ContractService::sign
 * for the actual mutation; this controller only handles auth, validation,
 * and the audit-context envelope.
 */
class SigningController extends Controller
{
    use AuthorizesContract;

    public function __construct(
        private readonly ContractService $service,
    ) {}

    /**
     * Standard password-reauth signature. UAE Federal Decree-Law 46/2021
     * requires the signature be uniquely linked to the signatory; an
     * active session alone is not enough — the user must explicitly
     * re-authenticate AT THE MOMENT of signing AND tick a consent
     * checkbox. Both are validated server-side here so a forged form
     * can never slip through.
     */
    public function sign(string $id, Request $request): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.sign'), 403, 'Forbidden: missing contracts.sign permission.');
        // Defence in depth: ContractService::sign() also rejects non-party
        // companies, but we hard-fail here too so a non-party never even
        // reaches the service call.
        $this->authorizeContractParty($contract);

        // Block signing while there are pending clause amendments. The
        // wording must be settled — every proposed change either approved
        // or rejected — BEFORE the e-signature is collected, otherwise a
        // party could sign a version they hadn't agreed to and then have
        // a clause swapped under them.
        $pendingCount = ContractAmendment::where('contract_id', $contract->id)
            ->where('status', AmendmentStatus::PENDING_APPROVAL)
            ->count();
        if ($pendingCount > 0) {
            return back()->withErrors([
                'contract' => __('contracts.sign_blocked_pending_amendments', ['count' => $pendingCount]),
            ]);
        }

        // Refuse to sign until the signing company has uploaded BOTH an
        // authorised signature image AND a stamp. The view hides the
        // sign button when these are missing, but a forged POST would
        // otherwise still slip through.
        $signerCompany = Company::find($user->company_id);
        if (! $signerCompany || ! $signerCompany->hasSignatureAssets()) {
            return back()->withErrors([
                'contract' => __('contracts.sign_blocked_missing_signature_assets'),
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'consent' => ['required', 'accepted'],
        ], [
            'password.required' => __('contracts.sign_password_required'),
            'consent.accepted' => __('contracts.sign_consent_required'),
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors([
                'password' => __('contracts.sign_password_incorrect'),
            ]);
        }

        $result = $this->service->sign(
            id: $contract->id,
            userId: $user->id,
            companyId: $user->company_id,
            signature: null,
            auditContext: [
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'consent_text' => __('contracts.sign_consent_text', [
                    'number' => $contract->contract_number,
                    'amount' => ($contract->currency ?: 'AED').' '.number_format((float) $contract->total_amount, 2),
                ]),
                'consent_at' => now()->toIso8601String(),
            ],
        );

        if (is_string($result)) {
            return back()->withErrors(['contract' => $result]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.signed_successfully'));
    }

    /**
     * Phase 6 — kick off the UAE Pass OAuth flow. Validates that the
     * contract is signable, mints a CSRF state value, stashes it in the
     * session, and redirects to UAE Pass. The matching callback handles
     * the return leg.
     */
    public function uaePassRedirect(string $id, UaePassProvider $uaePass): Response
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.sign'), 403);
        $this->authorizeContractParty($contract);

        if (! $uaePass->isEnabled()) {
            return back()->withErrors([
                'contract' => __('contracts.uae_pass_disabled'),
            ]);
        }

        $state = $uaePass->newState();
        // Bind the state to BOTH the session AND the contract id so a
        // user signing two contracts in parallel doesn't get the
        // states crossed.
        session()->put("uae_pass.state.{$contract->id}", [
            'state' => $state,
            'expires_at' => now()->addSeconds((int) config('uae_pass.state_ttl_seconds', 600))->toIso8601String(),
        ]);

        return redirect()->away($uaePass->buildAuthorizationUrl((string) $contract->id, $state));
    }

    /**
     * Phase 6 — UAE Pass callback handler. Validates the state, exchanges
     * the code, fetches the verified profile, then calls
     * ContractService::sign with `signature_grade = advanced` and the
     * UAE Pass identifiers stamped into the audit context.
     */
    public function uaePassCallback(string $id, Request $request, UaePassProvider $uaePass): RedirectResponse
    {
        $contract = $this->findContractOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('contract.sign'), 403);
        $this->authorizeContractParty($contract);

        $stateBag = session()->pull("uae_pass.state.{$contract->id}");
        if (! $stateBag || ! is_array($stateBag) || empty($stateBag['state'])) {
            return redirect()
                ->route('dashboard.contracts.show', ['id' => $contract->id])
                ->withErrors(['contract' => __('contracts.uae_pass_state_missing')]);
        }
        if (isset($stateBag['expires_at']) && now()->isAfter($stateBag['expires_at'])) {
            return redirect()
                ->route('dashboard.contracts.show', ['id' => $contract->id])
                ->withErrors(['contract' => __('contracts.uae_pass_state_expired')]);
        }

        try {
            $assertion = $uaePass->handleCallback($request, (string) $stateBag['state']);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('dashboard.contracts.show', ['id' => $contract->id])
                ->withErrors(['contract' => $e->getMessage()]);
        }

        $result = $this->service->sign(
            id: $contract->id,
            userId: $user->id,
            companyId: $user->company_id,
            signature: null,
            auditContext: [
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'consent_text' => __('contracts.sign_consent_text', [
                    'number' => $contract->contract_number,
                    'amount' => ($contract->currency ?: 'AED').' '.number_format((float) $contract->total_amount, 2),
                ]),
                'consent_at' => now()->toIso8601String(),
                // UAE Pass identity assertion satisfies the Advanced
                // grade under Federal Decree-Law 46/2021 Article 18.
                'signature_grade' => 'advanced',
                'uae_pass_user_id' => $assertion['uae_pass_user_id'] ?? null,
                'uae_pass_full_name' => $assertion['full_name'] ?? null,
            ],
        );

        if (is_string($result)) {
            return redirect()
                ->route('dashboard.contracts.show', ['id' => $contract->id])
                ->withErrors(['contract' => $result]);
        }

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.signed_successfully_uae_pass'));
    }
}
