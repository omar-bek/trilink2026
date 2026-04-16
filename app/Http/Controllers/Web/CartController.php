<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Phase 4 / Sprint 16-17 — buyer-side cart + checkout. Cart mutations all
 * delegate to CartService; checkout delegates to ContractService::createFromCart
 * which splits a multi-supplier cart into one Contract per supplier.
 *
 * Authorization rules:
 *   - All cart actions require an authenticated user with a company.
 *   - The user can only ever interact with their OWN cart — CartService
 *     resolves it from the authenticated user, so cross-tenant access
 *     is impossible by construction.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ContractService $contractService,
    ) {}

    /**
     * Render the full cart page. The drawer in the topbar is a separate
     * component; this view is for the focused review/checkout flow.
     */
    public function index(): View
    {
        $cart = $this->cartService->current(auth()->user(), create: false);
        $cart?->load(['items.product.company', 'items.variant', 'items.supplierCompany']);

        return view('dashboard.cart.index', [
            'cart' => $cart,
            'grouped' => $this->groupBySupplier($cart),
            'totals' => $cart ? $cart->totalsByCurrency() : [],
        ]);
    }

    /**
     * POST /dashboard/cart/items — add a product (with optional variant)
     * to the buyer's open cart. Used by the catalog browse + product show
     * pages.
     */
    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = ! empty($data['product_variant_id'])
            ? ProductVariant::find($data['product_variant_id'])
            : null;

        try {
            $this->cartService->add(
                user: auth()->user(),
                product: $product,
                quantity: (int) $data['quantity'],
                variant: $variant,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => __($e->getMessage())]);
        }

        return back()->with('status', __('cart.item_added'));
    }

    public function updateQuantity(Request $request, int $itemId): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $this->cartService->updateQuantity(auth()->user(), $itemId, (int) $data['quantity']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => __($e->getMessage())]);
        }

        return back()->with('status', __('cart.updated'));
    }

    public function remove(int $itemId): RedirectResponse
    {
        try {
            $this->cartService->remove(auth()->user(), $itemId);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => __($e->getMessage())]);
        }

        return back()->with('status', __('cart.item_removed'));
    }

    public function clear(): RedirectResponse
    {
        $this->cartService->clear(auth()->user());

        return back()->with('status', __('cart.cleared'));
    }

    /**
     * Phase 4 / Sprint 17 — checkout. Splits the cart by supplier and
     * creates one PENDING_SIGNATURES Contract per supplier via
     * ContractService::createFromCart. Returns to the cart page with
     * a list of links to each freshly-created contract.
     */
    public function checkout(): RedirectResponse
    {
        $user = auth()->user();
        $cart = $this->cartService->current($user, create: false);

        if (! $cart || $cart->items()->count() === 0) {
            return back()->withErrors(['cart' => __('cart.empty')]);
        }

        $cart->load(['items.product', 'items.variant']);

        try {
            $contracts = $this->contractService->createFromCart($cart, $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        if (empty($contracts)) {
            return back()->withErrors(['cart' => __('cart.checkout_failed')]);
        }

        $this->cartService->markCheckedOut($cart);

        // Land on the first new contract; the success flash lists the
        // others so the buyer can navigate between them.
        $first = $contracts[0];
        $message = count($contracts) === 1
            ? __('cart.checkout_one_contract')
            : __('cart.checkout_multi_contracts', ['count' => count($contracts)]);

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $first->id])
            ->with('status', $message);
    }

    /**
     * Phase 4 / Sprint 18 — quick reorder. Looks up the user's previous
     * contract by id, repopulates the open cart with its line items,
     * and bounces to /dashboard/cart so the buyer can review + edit
     * before checking out a second time.
     */
    public function reorderFromContract(string $id): RedirectResponse
    {
        $user = auth()->user();
        $contract = is_numeric($id)
            ? Contract::findOrFail((int) $id)
            : Contract::where('contract_number', $id)->firstOrFail();

        // Authorise: the user's company must be the buyer of the original
        // contract — reordering off someone else's purchase would be a
        // privacy leak.
        abort_unless($user && $user->company_id === $contract->buyer_company_id, 403);

        $copied = $this->cartService->reorderFromContract($user, $contract);

        if ($copied === 0) {
            return back()->withErrors(['cart' => __('cart.reorder_no_items')]);
        }

        return redirect()
            ->route('dashboard.cart.index')
            ->with('status', __('cart.reorder_success', ['count' => $copied]));
    }

    /**
     * Group cart items by supplier so the cart + checkout views can show
     * one card per supplier (matching how the multi-supplier checkout
     * actually creates one Contract per supplier downstream).
     *
     * @return array<int, array{supplier_id:int, supplier_name:string, items:array, currency:string, total:float}>
     */
    private function groupBySupplier($cart): array
    {
        if (! $cart || $cart->items->isEmpty()) {
            return [];
        }

        $groups = [];
        foreach ($cart->items as $item) {
            $sid = $item->supplier_company_id;
            if (! isset($groups[$sid])) {
                $groups[$sid] = [
                    'supplier_id' => $sid,
                    'supplier_name' => $item->supplierCompany?->name ?? '—',
                    'items' => [],
                    'currency' => $item->currency ?: 'AED',
                    'total' => 0.0,
                ];
            }
            $groups[$sid]['items'][] = $item;
            $groups[$sid]['total'] += $item->lineTotal();
        }

        return array_values($groups);
    }
}
