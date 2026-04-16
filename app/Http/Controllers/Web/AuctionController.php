<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Services\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reverse Auction front-end. Three endpoints:
 *
 *   - GET  /auctions/{id}      → live auction page (HTML, JS poller)
 *   - GET  /auctions/{id}/live → JSON snapshot for the JS poller (5s interval)
 *   - POST /auctions/{id}/bid  → place a new bid (form post + redirect)
 *
 * Polling instead of WebSocket keeps the H1 deployment story dead simple
 * (no Soketi/Reverb to manage) while still feeling live at 5s intervals.
 * The Reverb migration is a drop-in once H2 traffic justifies it.
 */
class AuctionController extends Controller
{
    public function __construct(private readonly AuctionService $service) {}

    public function show(int $id): View
    {
        abort_unless(auth()->user()?->hasPermission('rfq.view'), 403);

        $rfq = Rfq::with(['company', 'category'])->findOrFail($id);
        abort_unless($rfq->is_auction, 404);

        $snapshot = $this->service->liveSnapshot($rfq);

        return view('dashboard.auctions.show', compact('rfq', 'snapshot'));
    }

    /**
     * JSON polling endpoint. Returns the live auction snapshot. The JS
     * client hits this every ~5 seconds while the auction page is open.
     */
    public function live(int $id): JsonResponse
    {
        $rfq = Rfq::findOrFail($id);
        abort_unless($rfq->is_auction, 404);

        return response()->json($this->service->liveSnapshot($rfq));
    }

    public function placeBid(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('bid.submit'), 403);
        abort_unless($user->company_id, 403);

        $rfq = Rfq::findOrFail($id);
        abort_unless($rfq->is_auction, 404);

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = $this->service->placeAuctionBid(
            rfq: $rfq,
            companyId: $user->company_id,
            providerId: $user->id,
            price: (float) $data['price'],
            currency: $rfq->currency ?? 'AED',
        );

        if (is_string($result)) {
            return back()->withErrors(['price' => __('auction.error_'.$result)]);
        }

        return back()->with('status', __('auction.bid_placed'));
    }

    /**
     * Render the "Enable reverse auction" form for an existing RFQ. Only the
     * RFQ owner (with rfq.edit permission) sees this — they pick the start
     * window, reserve price, decrement, and anti-snipe seconds.
     */
    public function createForm(int $id): View
    {
        $rfq = Rfq::with('company')->findOrFail($id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('rfq.edit'), 403);
        abort_unless($user->company_id === $rfq->company_id, 403);

        return view('dashboard.auctions.create', compact('rfq'));
    }

    /**
     * Convert a regular RFQ into a reverse auction. Sets the auction window,
     * reserve price, bid decrement, and anti-snipe duration. The RFQ stays
     * a regular RFQ as well — bids placed via the standard form still work
     * during the auction window.
     */
    public function enable(Request $request, int $id): RedirectResponse
    {
        $rfq = Rfq::findOrFail($id);
        $user = $request->user();

        abort_unless($user?->hasPermission('rfq.edit'), 403);
        abort_unless($user->company_id === $rfq->company_id, 403);

        $data = $request->validate([
            'auction_starts_at' => ['required', 'date'],
            'auction_ends_at' => ['required', 'date', 'after:auction_starts_at'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'bid_decrement' => ['nullable', 'numeric', 'min:0'],
            'anti_snipe_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        $rfq->update([
            'is_auction' => true,
            'auction_starts_at' => $data['auction_starts_at'],
            'auction_ends_at' => $data['auction_ends_at'],
            'reserve_price' => $data['reserve_price'] ?? null,
            'bid_decrement' => $data['bid_decrement'] ?? null,
            'anti_snipe_seconds' => $data['anti_snipe_seconds'],
        ]);

        return redirect()
            ->route('dashboard.auctions.show', $rfq->id)
            ->with('status', __('auction.enabled'));
    }
}
