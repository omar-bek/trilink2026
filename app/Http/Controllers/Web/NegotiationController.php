<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Services\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

    public function __construct(private readonly ContractService $contracts)
    {
    }

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
                    'amount'         => $this->money((float) ($m->offer['amount'] ?? 0), $m->offer['currency'] ?? 'AED'),
                    'delivery_days'  => (int) ($m->offer['delivery_days'] ?? 0),
                    'payment_terms'  => $m->offer['payment_terms'] ?? '—',
                    'reason'         => $m->offer['reason'] ?? '',
                ];
            }

            return [
                'id'     => $m->id,
                'side'   => $m->sender_side,                    // 'buyer' or 'supplier'
                'mine'   => $m->sender_side === $side,
                'author' => trim(($m->sender?->first_name ?? '') . ' ' . ($m->sender?->last_name ?? ''))
                    ?: ($m->sender_side === 'buyer' ? __('negotiation.buyer_team') : $m->bid?->company?->name),
                'kind'   => $m->kind,
                'body'   => $m->body,
                'offer'  => $offer,
                'time'   => $m->created_at?->format('M j, Y g:i A'),
            ];
        })->all();

        // Current offer = latest counter_offer, else original bid.
        $latestCounter = $bid->negotiationMessages
            ->where('kind', 'counter_offer')
            ->sortByDesc('created_at')
            ->first();

        $currentAmount = $latestCounter ? (float) ($latestCounter->offer['amount'] ?? $bid->price) : (float) $bid->price;
        $currentDays   = $latestCounter ? (int) ($latestCounter->offer['delivery_days'] ?? $bid->delivery_time_days) : (int) ($bid->delivery_time_days ?? 0);
        $currentTerms  = $latestCounter ? ($latestCounter->offer['payment_terms'] ?? $bid->payment_terms) : ($bid->payment_terms ?? '—');

        $originalBudget = (float) ($bid->rfq?->budget ?? $bid->price);
        // Suggested "target price" = 10% under the original budget — a common
        // negotiation anchor. Buyers see this as a soft goal; suppliers see
        // the same value so both sides have a shared reference point.
        $targetPrice = max(0, $originalBudget * 0.90);

        $diffFromBudget = $originalBudget - $currentAmount;

        $view = [
            'bid_id'       => 'BID-' . ($bid->created_at?->format('Y') ?? date('Y')) . '-' . str_pad((string) $bid->id, 4, '0', STR_PAD_LEFT),
            'numeric_id'   => $bid->id,
            'rfq_number'   => $bid->rfq?->rfq_number ?? '—',
            'rfq_title'    => $bid->rfq?->title ?? '—',
            'supplier'     => $bid->company?->name ?? '—',
            'status'       => $this->mapBidStatus($this->statusValue($bid->status)),
            'is_active'    => in_array($this->statusValue($bid->status), ['submitted', 'under_review'], true),
            'current'      => [
                'amount'        => $this->money($currentAmount, $bid->currency ?? 'AED'),
                'amount_raw'    => $currentAmount,
                'delivery_days' => $currentDays,
                'terms'         => $currentTerms,
                'valid_until'   => $this->longDate($bid->validity_date),
                'diff_label'    => $diffFromBudget >= 0
                    ? $this->money($diffFromBudget, $bid->currency ?? 'AED') . ' ' . __('negotiation.below_budget')
                    : $this->money(abs($diffFromBudget), $bid->currency ?? 'AED') . ' ' . __('negotiation.above_budget'),
                'diff_positive' => $diffFromBudget >= 0,
            ],
            'analysis'     => [
                'original_budget' => $this->money($originalBudget, $bid->currency ?? 'AED'),
                'current_offer'   => $this->money($currentAmount, $bid->currency ?? 'AED'),
                'target_price'    => $this->money($targetPrice, $bid->currency ?? 'AED'),
            ],
            'messages'     => $messages,
            'my_side'      => $side,
            'can_act'      => $side !== null && in_array($this->statusValue($bid->status), ['submitted', 'under_review'], true),
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
            'bid_id'      => $bid->id,
            'sender_id'   => $user->id,
            'sender_side' => $this->sideFor($bid, $user),
            'kind'        => 'text',
            'body'        => $data['body'],
        ]);

        return redirect()->route('dashboard.negotiations.show', ['id' => $bid->id]);
    }

    public function storeCounterOffer(Request $request, string $id): RedirectResponse
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();
        $this->authorizeParticipant($bid, $user);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:1'],
            'delivery_days'  => ['required', 'integer', 'min:1', 'max:3650'],
            'payment_terms'  => ['required', 'string', 'max:255'],
            'reason'         => ['nullable', 'string', 'max:1000'],
        ]);

        NegotiationMessage::create([
            'bid_id'      => $bid->id,
            'sender_id'   => $user->id,
            'sender_side' => $this->sideFor($bid, $user),
            'kind'        => 'counter_offer',
            'body'        => $data['reason'] ?? null,
            'offer'       => [
                'amount'        => (float) $data['amount'],
                'currency'      => $bid->currency ?? 'AED',
                'delivery_days' => (int) $data['delivery_days'],
                'payment_terms' => $data['payment_terms'],
                'reason'        => $data['reason'] ?? '',
            ],
        ]);

        return redirect()->route('dashboard.negotiations.show', ['id' => $bid->id])
            ->with('status', __('negotiation.counter_sent'));
    }

    public function accept(string $id): RedirectResponse
    {
        $bid = $this->findBidOrFail($id);
        $user = auth()->user();
        $this->authorizeParticipant($bid, $user);

        // Only the buyer side can "accept the current offer" from inside the
        // room — mirrors the bid accept rule.
        abort_unless($this->sideFor($bid, $user) === 'buyer', 403, 'Only the buyer can accept an offer.');
        abort_unless($user?->hasPermission('bid.accept'), 403);

        // Same shape as BidController@accept: update bid + reject siblings +
        // generate a contract — all inside one transaction. The only
        // difference is we also copy the latest counter-offer's terms onto
        // the bid first, so the contract reflects the negotiated price.
        $contract = DB::transaction(function () use ($bid) {
            $latestCounter = $bid->negotiationMessages()
                ->where('kind', 'counter_offer')
                ->latest()
                ->first();

            $update = ['status' => BidStatus::ACCEPTED];
            if ($latestCounter && is_array($latestCounter->offer)) {
                $update['price']              = (float) ($latestCounter->offer['amount'] ?? $bid->price);
                $update['delivery_time_days'] = (int) ($latestCounter->offer['delivery_days'] ?? $bid->delivery_time_days);
                $update['payment_terms']      = $latestCounter->offer['payment_terms'] ?? $bid->payment_terms;
            }
            $bid->update($update);

            Bid::where('rfq_id', $bid->rfq_id)
                ->where('id', '!=', $bid->id)
                ->update(['status' => BidStatus::REJECTED->value]);

            return $this->contracts->createFromBid($bid->fresh(['rfq', 'company']));
        });

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('contracts.created_from_bid'));
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
        if (!$user) {
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
            'draft'        => 'draft',
            'submitted'    => 'submitted',
            'under_review' => 'under_review',
            'accepted'     => 'accepted',
            'rejected'     => 'rejected',
            'withdrawn'    => 'closed',
            default        => 'draft',
        };
    }
}
