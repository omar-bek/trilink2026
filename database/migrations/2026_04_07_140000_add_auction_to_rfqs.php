<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse Auction support, layered on top of the existing RFQ model.
     *
     * Why on the RFQ rather than a separate table?
     *   - The lifecycle (open → closed) and the access controls (only suppliers
     *     from other companies can bid) are identical.
     *   - The bid history is already stored in the bids table; auction-style
     *     bids are just regular Bids submitted faster.
     *
     * The new columns:
     *   - is_auction: opt-in flag. Standard RFQs ignore the auction logic.
     *   - auction_starts_at / auction_ends_at: time window during which bids
     *     are accepted. Outside this window the BidService rejects new bids.
     *   - reserve_price: optional floor; bids below this are rejected.
     *   - bid_decrement: minimum step a new bid must beat the current
     *     leader by (e.g. AED 100 enforces meaningful undercuts).
     *   - anti_snipe_seconds: if the leader changes within this many seconds
     *     of the end time, the auction extends by the same amount. Stops
     *     last-second sniping.
     */
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->boolean('is_auction')->default(false)->after('is_anonymous');
            $table->timestamp('auction_starts_at')->nullable()->after('is_auction');
            $table->timestamp('auction_ends_at')->nullable()->after('auction_starts_at');
            $table->decimal('reserve_price', 15, 2)->nullable()->after('auction_ends_at');
            $table->decimal('bid_decrement', 15, 2)->nullable()->after('reserve_price');
            $table->unsignedSmallInteger('anti_snipe_seconds')->default(120)->after('bid_decrement');
            $table->index(['is_auction', 'auction_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropIndex(['is_auction', 'auction_ends_at']);
            $table->dropColumn([
                'is_auction',
                'auction_starts_at',
                'auction_ends_at',
                'reserve_price',
                'bid_decrement',
                'anti_snipe_seconds',
            ]);
        });
    }
};
