<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Catalog & Buy-Now expansion. Schema lays the groundwork for:
 *
 *   - product_variants: SKU-level variations of a product (size, color,
 *     material, tier). Optional — products without variants still work
 *     exactly as they do today.
 *   - carts:            one open cart per user. Status flows
 *                       open → checked_out → archived.
 *   - cart_items:       individual lines on a cart. Each line snapshots
 *                       price + currency + name at add-time so a price
 *                       change on the supplier side doesn't silently
 *                       re-price something the buyer is about to checkout.
 *
 * The cart deliberately stores the supplier_company_id on every line so
 * the multi-supplier checkout flow can group lines without joining back
 * through products. That's the entire reason this column exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            // Display label e.g. "Red / Large" — what the buyer sees on
            // the product show page next to the price modifier.
            $table->string('name');
            // Free-form attributes (color, size, finish, voltage). Used
            // by future filters (Phase 5+) and snapshotted onto cart_items
            // at add time so historical orders survive variant deletion.
            $table->json('attributes')->nullable();
            // Added/subtracted from product.base_price. Negative for
            // smaller-tier discounts; positive for premium tiers.
            $table->decimal('price_modifier', 15, 2)->default(0);
            $table->unsignedInteger('stock_qty')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
            // SKU is unique within a product so the same SKU can be
            // re-used by different suppliers without collision.
            $table->unique(['product_id', 'sku']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Denormalised buyer company id so the cart query (which runs
            // on every page that renders the topbar count) doesn't need
            // a join through users.
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            // open → checked_out → abandoned. We never delete a checked-
            // out cart so reorder + analytics can replay the history.
            $table->string('status', 20)->default('open');
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Most queries are "current open cart for this user" so this
            // composite index is hot path.
            $table->index(['user_id', 'status']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Nullable because most products won't have variants. When set
            // the snapshotted attributes give us a self-contained record
            // even if the variant is later deleted by the supplier.
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();
            // Denormalised supplier company id — see the migration
            // docblock above for the rationale.
            $table->foreignId('supplier_company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            // Frozen at add time so a supplier price change doesn't drag
            // a buyer's pre-confirmed cart along with it.
            $table->decimal('unit_price', 15, 2);
            $table->string('currency', 3)->default('AED');
            // Snapshots the buyer-facing label and the variant attributes
            // so the cart line survives variant deletion.
            $table->string('name_snapshot', 191);
            $table->json('attributes_snapshot')->nullable();
            $table->timestamps();

            $table->index(['cart_id', 'supplier_company_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('product_variants');
    }
};
