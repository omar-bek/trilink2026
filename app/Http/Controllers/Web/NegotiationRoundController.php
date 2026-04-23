<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Services\NegotiationService;
use App\Services\NegotiationVatCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Web actions for the structured negotiation flow. Owns the round-specific
 * actions: open counter, accept (with typed signature), reject, and a
 * live VAT-recalculation preview used by the counter-offer form.
 *
 * Authorization: only members of the buyer or supplier company on the bid
 * can act. A counter-offer is also blocked when the opposite-side already
 * has an open round waiting on THIS side — a counter is a "response to
 * you", so the sender must be the party that was waited on.
 */
class NegotiationRoundController extends Controller
{
    public function __construct(
        private readonly NegotiationService $service,
        private readonly NegotiationVatCalculator $vat,
    ) {}

    public function counter(Request $request, int $bidId): RedirectResponse
    {
        $user = $request->user();
        $bid = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $bidCurrency = strtoupper((string) ($bid->currency ?? 'AED'));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3', "in:$bidCurrency"],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Only the side that was WAITING on the open round can counter it.
        // Same-side counters would let someone override their teammate's
        // open offer, which defeats the round contract.
        $senderSide = $this->service->resolveSide($bid, $user);
        $open = $this->service->latestOpenRound($bid);
        if ($open && $open->sender_side === $senderSide) {
            return back()->withErrors(['negotiation' => __('negotiation.error_same_side_counter')]);
        }

        try {
            $this->service->openCounterOffer(
                bid: $bid,
                sender: $user,
                offer: [
                    'amount' => (float) $data['amount'],
                    'currency' => $data['currency'] ?? $bidCurrency,
                    'delivery_days' => $data['delivery_days'] ?? null,
                    'payment_terms' => $data['payment_terms'] ?? null,
                ],
                reason: $data['reason'] ?? null,
            );
        } catch (Throwable $e) {
            return back()->withErrors(['negotiation' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('negotiation.counter_sent'));
    }

    public function accept(Request $request, int $bidId): RedirectResponse
    {
        $user = $request->user();
        $bid = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $data = $request->validate([
            'signature_name' => ['required', 'string', 'min:3', 'max:150'],
            'acknowledge' => ['accepted'],
        ], [
            'signature_name.required' => __('negotiation.error_signature_required'),
            'acknowledge.accepted' => __('negotiation.error_ack_required'),
        ]);

        try {
            $msg = $this->service->acceptOffer($bid, $user, [
                'name' => $data['signature_name'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['negotiation' => $e->getMessage()]);
        }

        if (! $msg) {
            return back()->withErrors(['negotiation' => __('negotiation.no_open_round')]);
        }

        return back()->with('status', __('negotiation.offer_accepted'));
    }

    public function reject(Request $request, int $bidId): RedirectResponse
    {
        $user = $request->user();
        $bid = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $reason = trim((string) $request->input('reason', ''));
        $msg = $this->service->rejectOffer($bid, $user, $reason !== '' ? $reason : null);

        if (! $msg) {
            return back()->withErrors(['negotiation' => __('negotiation.no_open_round')]);
        }

        return back()->with('status', __('negotiation.offer_rejected'));
    }

    /**
     * JSON endpoint used by the counter form to render the live VAT
     * breakdown while the user types. Returns subtotal / VAT / total in
     * the bid's original tax treatment so both parties see the same math.
     */
    public function vatPreview(Request $request, int $bidId): JsonResponse
    {
        $user = $request->user();
        $bid = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $out = $this->vat->recalculate($bid, (float) $data['amount']);

        return response()->json([
            'currency' => strtoupper((string) ($bid->currency ?? 'AED')),
            'treatment' => $out['treatment'],
            'rate' => $out['rate'],
            'subtotal' => $out['subtotal_excl_tax'],
            'vat' => $out['tax_amount'],
            'total' => $out['total_incl_tax'],
        ]);
    }

    private function authorizeParty($user, Bid $bid): void
    {
        $allowed = $user
            && $user->company_id
            && ($user->company_id === $bid->company_id || $user->company_id === $bid->rfq?->company_id);

        abort_unless($allowed, 403);
    }
}
