<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Services\ContractService;
use App\Services\NegotiationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Negotiation Room — chat + counter offers between a buyer and a supplier
 * for a specific bid. The "room" is a view over (Bid, its NegotiationMessages).
 *
 * Participants:
 *   - Buyer side  = any user from the RFQ's company (bid.rfq.company_id)
 *   - Supplier side = any user from the bid's company (bid.company_id)
 *
 * Current offer:
 *   = the latest counter_offer in the chat, OR
 *   = the original bid price if no counter offers yet.
 *
 * Accepting the current offer sets the bid status to ACCEPTED and updates
 * the bid price/terms to match. Ending the negotiation marks the bid as
 * WITHDRAWN (for suppliers) or REJECTED (for buyers).
 */
class NegotiationController extends Controller
{
    use FormatsForViews;

    public function __construct(
        private readonly ContractService $contracts,
        private readonly NegotiationService $negotiations,
    ) {}

    public function show(string $id): View
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();

        $this->authorizeParticipant($bid, $user);

        $bid->loadMissing(['rfq.category', 'company', 'provider', 'negotiationMessages.sender']);

        $side = $this->sideFor($bid, $user);

        $messages = $bid->negotiationMessages->map(function (NegotiationMessage $m) use ($side) {
            $offer = null;
            if ($m->kind === 'counter_offer' && is_array($m->offer)) {
                $offer = [
                    'amount' => $this->money((float) ($m->offer['amount'] ?? 0), $m->offer['currency'] ?? 'AED'),
                    'delivery_days' => (int) ($m->offer['delivery_days'] ?? 0),
                    'payment_terms' => $m->offer['payment_terms'] ?? '—',
                    'reason' => $m->offer['reason'] ?? '',
                ];
            }

            return [
                'id' => $m->id,
                'side' => $m->sender_side,                    // 'buyer' or 'supplier'
                'mine' => $m->sender_side === $side,
                'author' => trim(($m->sender?->first_name ?? '').' '.($m->sender?->last_name ?? ''))
                    ?: ($m->sender_side === 'buyer' ? __('negotiation.buyer_team') : $m->bid?->company?->name),
                'kind' => $m->kind,
                'body' => $m->body,
                'offer' => $offer,
                'time' => $m->created_at?->format('M j, Y g:i A'),
            ];
        })->all();

        // Current offer = latest counter_offer, else original bid.
        $latestCounter = $bid->negotiationMessages
            ->where('kind', 'counter_offer')
            ->sortByDesc('created_at')
            ->first();

        // Open round = a counter that is still awaiting a response. Only
        // the OPPOSITE-side user can accept or reject it — the side that
        // sent the counter has to wait for the other party to act.
        $openRound = $this->negotiations->latestOpenRound($bid);
        $hasOpenCounter = (bool) $openRound;
        $canRespond = $hasOpenCounter
            && $side !== null
            && $openRound->sender_side !== $side
            && ! $openRound->isExpired();

        $currentAmount = $latestCounter ? (float) ($latestCounter->offer['amount'] ?? $bid->price) : (float) $bid->price;
        $currentDays = $latestCounter ? (int) ($latestCounter->offer['delivery_days'] ?? $bid->delivery_time_days) : (int) ($bid->delivery_time_days ?? 0);
        $currentTerms = $latestCounter ? ($latestCounter->offer['payment_terms'] ?? $bid->payment_terms) : ($bid->payment_terms ?? '—');

        $originalBudget = (float) ($bid->rfq?->budget ?? $bid->price);
        // Suggested "target price" = 10% under the original budget — a common
        // negotiation anchor. Buyers see this as a soft goal; suppliers see
        // the same value so both sides have a shared reference point.
        $targetPrice = max(0, $originalBudget * 0.90);

        $diffFromBudget = $originalBudget - $currentAmount;

        $view = [
            'bid_id' => 'BID-'.($bid->created_at?->format('Y') ?? date('Y')).'-'.str_pad((string) $bid->id, 4, '0', STR_PAD_LEFT),
            'numeric_id' => $bid->id,
            'rfq_number' => $bid->rfq?->rfq_number ?? '—',
            'rfq_title' => $bid->rfq?->title ?? '—',
            'supplier' => $bid->company?->name ?? '—',
            'status' => $this->mapBidStatus($this->statusValue($bid->status)),
            'is_active' => in_array($this->statusValue($bid->status), ['submitted', 'under_review'], true),
            'current' => [
                'amount' => $this->money($currentAmount, $bid->currency ?? 'AED'),
                'amount_raw' => $currentAmount,
                'delivery_days' => $currentDays,
                'terms' => $currentTerms,
                'valid_until' => $this->longDate($bid->validity_date),
                'diff_label' => $diffFromBudget >= 0
                    ? $this->money($diffFromBudget, $bid->currency ?? 'AED').' '.__('negotiation.below_budget')
                    : $this->money(abs($diffFromBudget), $bid->currency ?? 'AED').' '.__('negotiation.above_budget'),
                'diff_positive' => $diffFromBudget >= 0,
            ],
            'analysis' => [
                'original_budget' => $this->money($originalBudget, $bid->currency ?? 'AED'),
                'current_offer' => $this->money($currentAmount, $bid->currency ?? 'AED'),
                'target_price' => $this->money($targetPrice, $bid->currency ?? 'AED'),
            ],
            'messages' => $messages,
            'my_side' => $side,
            'can_act' => $side !== null && in_array($this->statusValue($bid->status), ['submitted', 'under_review'], true),
            'has_open_counter' => $hasOpenCounter,
            'can_respond' => $canRespond,
            'open_round_sender' => $openRound?->sender_side,
            'open_round_number' => $openRound?->round_number,
        ];

        return view('dashboard.negotiations.show', ['n' => $view]);
    }

    public function storeMessage(Request $request, string $id): RedirectResponse
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();
        $this->authorizeParticipant($bid, $user);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        NegotiationMessage::create([
            'bid_id' => $bid->id,
            'sender_id' => $user->id,
            'sender_side' => $this->sideFor($bid, $user),
            'kind' => 'text',
            'body' => $data['body'],
        ]);

        return redirect()->route('dashboard.negotiations.show', ['id' => $bid->id]);
    }

    /**
     * Legacy endpoint kept for backwards compatibility with the older
     * in-room counter form — delegates to the structured round service so
     * VAT, expiry, audit, and notifications run consistently regardless of
     * which UI opens the counter.
     */
    public function storeCounterOffer(Request $request, string $id): RedirectResponse
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();
        $this->authorizeParticipant($bid, $user);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'delivery_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'payment_terms' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->negotiations->openCounterOffer(
                bid: $bid,
                sender: $user,
                offer: [
                    'amount' => (float) $data['amount'],
                    'currency' => strtoupper((string) ($bid->currency ?? 'AED')),
                    'delivery_days' => (int) $data['delivery_days'],
                    'payment_terms' => $data['payment_terms'],
                ],
                reason: $data['reason'] ?? null,
            );
        } catch (Throwable $e) {
            return back()->withErrors(['negotiation' => $e->getMessage()])->withInput();
        }

        return redirect()->route('dashboard.negotiations.show', ['id' => $bid->id])
            ->with('status', __('negotiation.counter_sent'));
    }

    /**
     * Accept the current open round from inside the negotiation room.
     * Delegates to NegotiationService so the VAT snapshot, signed
     * acceptance, audit log, and notifications all go through the same
     * code path as the bid-show Negotiation tab. Bid acceptance +
     * contract creation is then handled by the Bid accept endpoint /
     * the buyer from the bid show page — this only closes the round.
     */
    public function accept(Request $request, string $id): RedirectResponse
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();
        $this->authorizeParticipant($bid, $user);

        $data = $request->validate([
            'signature_name' => ['required', 'string', 'min:3', 'max:150'],
            'acknowledge' => ['accepted'],
        ], [
            'signature_name.required' => __('negotiation.error_signature_required'),
            'acknowledge.accepted' => __('negotiation.error_ack_required'),
        ]);

        try {
            $msg = $this->negotiations->acceptOffer($bid, $user, [
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

        return redirect()->route('dashboard.bids.show', ['id' => $bid->id])
            ->with('status', __('negotiation.offer_accepted'));
    }

    public function end(string $id): RedirectResponse
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();
        $this->authorizeParticipant($bid, $user);

        $side = $this->sideFor($bid, $user);

        // Buyer ending = reject. Supplier ending = withdraw.
        $newStatus = $side === 'buyer' ? BidStatus::REJECTED->value : BidStatus::WITHDRAWN->value;
        $bid->update(['status' => $newStatus]);

        return redirect()->route('dashboard.bids.show', ['id' => $bid->id])
            ->with('status', __('negotiation.ended'));
    }

    // ---------------------------------------------------------------------

    private function findBidOrFail(string $id): Bid
    {
        if (preg_match('/BID-\d{4}-(\d+)/', $id, $m)) {
            return Bid::findOrFail(max((int) $m[1] - 5421, 1));
        }

        return Bid::findOrFail((int) $id);
    }

    /**
     * Which side of the negotiation is the current user on?
     * Returns 'buyer', 'supplier', or null (not a participant).
     */
    private function sideFor(Bid $bid, $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($bid->rfq && $user->company_id === $bid->rfq->company_id) {
            return 'buyer';
        }

        if ($user->company_id === $bid->company_id) {
            return 'supplier';
        }

        return null;
    }

    private function authorizeParticipant(Bid $bid, $user): void
    {
        abort_unless($user?->hasPermission('bid.view'), 403);
        abort_unless($this->sideFor($bid, $user) !== null, 403, 'Only participants can view the negotiation room.');
    }

    /**
     * Same mapping BidController uses — kept private here so the two never
     * drift apart even if we change one later.
     */
    private function mapBidStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'draft',
            'submitted' => 'submitted',
            'under_review' => 'under_review',
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'withdrawn' => 'closed',
            default => 'draft',
        };
    }
}
