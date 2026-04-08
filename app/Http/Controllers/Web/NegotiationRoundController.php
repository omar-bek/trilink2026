<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Services\NegotiationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web actions for the structured negotiation flow. The list/show UI is
 * handled by the existing BidController + NegotiationController; this
 * controller owns the round-specific actions: open counter, accept, reject.
 *
 * Authorization: only members of the buyer or supplier company on the bid
 * can act. Cross-tenant attempts return 403.
 */
class NegotiationRoundController extends Controller
{
    public function __construct(private readonly NegotiationService $service)
    {
    }

    public function counter(Request $request, int $bidId): RedirectResponse
    {
        $user = $request->user();
        $bid  = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'delivery_days'  => ['nullable', 'integer', 'min:1'],
            'payment_terms'  => ['nullable', 'string', 'max:500'],
            'reason'         => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->openCounterOffer(
            bid: $bid,
            sender: $user,
            offer: [
                'amount'        => (float) $data['amount'],
                'currency'      => $data['currency'] ?? $bid->currency ?? 'AED',
                'delivery_days' => $data['delivery_days'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
            ],
            reason: $data['reason'] ?? null,
        );

        return back()->with('status', __('negotiation.counter_sent'));
    }

    public function accept(Request $request, int $bidId): RedirectResponse
    {
        $user = $request->user();
        $bid  = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $msg = $this->service->acceptOffer($bid, $user);

        if (!$msg) {
            return back()->withErrors(['negotiation' => __('negotiation.no_open_round')]);
        }

        return back()->with('status', __('negotiation.offer_accepted'));
    }

    public function reject(Request $request, int $bidId): RedirectResponse
    {
        $user = $request->user();
        $bid  = Bid::with('rfq')->findOrFail($bidId);
        $this->authorizeParty($user, $bid);

        $reason = (string) $request->input('reason', '');
        $msg = $this->service->rejectOffer($bid, $user, $reason !== '' ? $reason : null);

        if (!$msg) {
            return back()->withErrors(['negotiation' => __('negotiation.no_open_round')]);
        }

        return back()->with('status', __('negotiation.offer_rejected'));
    }

    private function authorizeParty($user, Bid $bid): void
    {
        $allowed = $user
            && $user->company_id
            && ($user->company_id === $bid->company_id || $user->company_id === $bid->rfq?->company_id);

        abort_unless($allowed, 403);
    }
}
