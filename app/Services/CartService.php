<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 4 / Sprint 16 — single source of truth for the buyer's shopping
 * cart. Every controller (CartController + ProductController add-to-cart)
 * goes through this service, which keeps the snapshot rules consistent
 * (price + name + variant attributes are frozen at add time).
 *
 * Pattern: every method that mutates the cart returns the updated Cart
 * (or throws RuntimeException with a translatable error key). The
 * controller catches and surfaces the error via the standard flash
 * channel.
 */
class CartService
{
    /**
     * Resolve the user's current OPEN cart, creating one on demand. Lazy
     * creation matters because we render a topbar count on every page —
     * we don't want to insert a row for every visitor who never adds
     * anything.
     */
    public function current(User $user, bool $create = true): ?Cart
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', Cart::STATUS_OPEN)
            ->latest('id')
            ->first();

        if ($cart || ! $create) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'status' => Cart::STATUS_OPEN,
        ]);
    }

    /**
     * Add a product (with optional variant) to the buyer's cart. If the
     * same product+variant pair already exists in the cart, the quantity
     * is incremented in place rather than creating a duplicate line.
     *
     * @throws RuntimeException 'cart.cannot_buy_own', 'cart.not_purchasable', 'cart.exceeds_stock'
     */
    public function add(User $user, Product $product, int $quantity, ?ProductVariant $variant = null): Cart
    {
        if ($product->company_id === $user->company_id) {
            throw new RuntimeException('cart.cannot_buy_own');
        }
        if (! $product->isPurchasable()) {
            throw new RuntimeException('cart.not_purchasable');
        }
        if ($variant && $variant->product_id !== $product->id) {
            throw new RuntimeException('cart.variant_mismatch');
        }
        if ($variant && ! $variant->isPurchasable()) {
            throw new RuntimeException('cart.variant_unavailable');
        }

        $minQty = (int) ($product->min_order_qty ?: 1);
        $quantity = max($minQty, $quantity);

        // Stock check applies to whichever level (variant > product) is
        // tracking inventory. Untracked products skip the check entirely.
        $stockSource = $variant?->stock_qty ?? $product->stock_qty;
        if ($stockSource !== null && $quantity > $stockSource) {
            throw new RuntimeException('cart.exceeds_stock');
        }

        $unitPrice = $variant ? $variant->effectivePrice() : (float) $product->base_price;
        $currency = $product->currency ?: 'AED';
        $name = $variant ? ($product->name.' — '.$variant->name) : $product->name;

        return DB::transaction(function () use ($user, $product, $variant, $quantity, $unitPrice, $currency, $name, $stockSource) {
            $cart = $this->current($user);

            // Look for an existing line with the same product+variant
            // pair. Two lines for the same product would just clutter
            // the UI for no benefit.
            $existing = $cart->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variant?->id)
                ->first();

            if ($existing) {
                $newQty = $existing->quantity + $quantity;
                if ($stockSource !== null && $newQty > $stockSource) {
                    throw new RuntimeException('cart.exceeds_stock');
                }
                $existing->update(['quantity' => $newQty]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'supplier_company_id' => $product->company_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'currency' => $currency,
                    'name_snapshot' => $name,
                    'attributes_snapshot' => $variant?->attributes,
                ]);
            }

            return $cart->fresh('items');
        });
    }

    /**
     * Update the quantity of an existing cart line. Setting quantity to 0
     * removes the line. Same stock check as add().
     */
    public function updateQuantity(User $user, int $itemId, int $quantity): Cart
    {
        $cart = $this->current($user, create: false);
        if (! $cart) {
            throw new RuntimeException('cart.not_found');
        }

        $item = $cart->items()->findOrFail($itemId);

        if ($quantity <= 0) {
            $item->delete();

            return $cart->fresh('items');
        }

        // Re-resolve stock from the live product/variant since stock can
        // change between adding and updating.
        $product = $item->product;
        $variant = $item->variant;
        $stockSource = $variant?->stock_qty ?? $product?->stock_qty;
        if ($stockSource !== null && $quantity > $stockSource) {
            throw new RuntimeException('cart.exceeds_stock');
        }

        $item->update(['quantity' => $quantity]);

        return $cart->fresh('items');
    }

    public function remove(User $user, int $itemId): Cart
    {
        $cart = $this->current($user, create: false);
        if (! $cart) {
            throw new RuntimeException('cart.not_found');
        }

        $cart->items()->where('id', $itemId)->delete();

        return $cart->fresh('items');
    }

    public function clear(User $user): ?Cart
    {
        $cart = $this->current($user, create: false);
        if (! $cart) {
            return null;
        }
        $cart->items()->delete();

        return $cart->fresh('items');
    }

    /**
     * Mark a cart as checked out. Called from CheckoutController after
     * the per-supplier contracts have been created so the buyer can't
     * accidentally double-checkout the same lines.
     */
    public function markCheckedOut(Cart $cart): Cart
    {
        $cart->update([
            'status' => Cart::STATUS_CHECKED_OUT,
            'checked_out_at' => now(),
        ]);

        return $cart->fresh();
    }

    /**
     * Phase 4 / Sprint 18 — repopulate the user's open cart with the
     * line items of a previously completed purchase. Used by the "Buy
     * Again" button on contract show pages. Lines that point at
     * deleted/inactive products or variants are silently skipped — the
     * caller can see the diff in the cart afterwards.
     *
     * @return int number of lines successfully copied
     */
    public function reorderFromContract(User $user, $contract): int
    {
        $copied = 0;
        $cart = $this->current($user);

        // Most contracts pre-Phase-4 don't carry line items in `amounts`,
        // so we degrade gracefully. The Buy-Now flow stores `unit_price`
        // and `quantity` in `amounts` which is enough to reconstitute.
        $amounts = is_array($contract->amounts) ? $contract->amounts : [];

        // Catalog Buy-Now contracts are single-product. We don't have a
        // line items table on contracts (Phase 5+), so heuristically
        // recover the buy-now product via the title + supplier.
        $supplierParty = collect($contract->parties ?? [])->firstWhere('role', 'supplier');
        $supplierId = $supplierParty['company_id'] ?? null;
        if (! $supplierId) {
            return 0;
        }

        $product = Product::query()
            ->where('company_id', $supplierId)
            ->where('name', $contract->title)
            ->first();

        if (! $product || ! $product->isPurchasable()) {
            return 0;
        }

        $quantity = (int) max(1, $amounts['quantity'] ?? $product->min_order_qty);

        try {
            $this->add($user, $product, $quantity);
            $copied++;
        } catch (RuntimeException) {
            // Ignore — most likely the buyer is on the same company as
            // the supplier (e.g. they switched companies), or the stock
            // ran out. Skipping is the right call.
        }

        return $copied;
    }
}
