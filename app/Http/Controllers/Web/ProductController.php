<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ContractService;
use App\Services\HsCodeClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Catalog management for suppliers + browse + Buy-Now for buyers.
 *
 * Suppliers list standardised goods with fixed prices; buyers browse the
 * catalog and either RFQ for custom orders or Buy-Now for direct purchase.
 * Buy-Now creates a Contract immediately via ContractService::createFromProduct
 * and inherits the existing tax + signing + auto-payment pipeline.
 */
class ProductController extends Controller
{
    public function __construct(private readonly ContractService $contractService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────
    // SUPPLIER SIDE — manage own catalog
    // ─────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $companyId = auth()->user()->company_id;
        abort_unless($companyId, 403);

        $products = Product::with(['category'])
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(20);

        return view('dashboard.products.index', compact('products'));
    }

    public function create(): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('dashboard.products.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $this->validateData($request);

        // Persist uploaded images first so we can store the resulting paths
        // alongside the rest of the product fields. On create there are no
        // existing images to keep.
        $data['images'] = $this->syncImages(
            request: $request,
            companyId: (int) $user->company_id,
            existingPaths: [],
            keepPaths: [],
        );

        $product = Product::create(array_merge($data, [
            'company_id' => $user->company_id,
            'branch_id'  => $user->branch_id,
        ]));

        // Phase 4 / Sprint 15 — persist any variants posted alongside
        // the product. The form serialises rows as variants[i][name|sku|...].
        $this->syncVariants($product, $request->input('variants', []));

        return redirect()
            ->route('dashboard.products.index')
            ->with('status', __('catalog.created_successfully'));
    }

    public function edit(int $id): View
    {
        $user = auth()->user();
        $product = Product::with('variants')->where('company_id', $user->company_id)->findOrFail($id);
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('dashboard.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $product = Product::where('company_id', $user->company_id)->findOrFail($id);

        $data = $this->validateData($request);

        // Reconcile images: keep whichever existing paths the form sent
        // back in `existing_images[]`, delete the rest, then append any
        // newly uploaded files. The merged array replaces the column.
        $data['images'] = $this->syncImages(
            request: $request,
            companyId: (int) $user->company_id,
            existingPaths: is_array($product->images) ? $product->images : [],
            keepPaths: $request->input('existing_images', []),
        );

        $product->update($data);
        $this->syncVariants($product, $request->input('variants', []));

        return redirect()
            ->route('dashboard.products.index')
            ->with('status', __('catalog.updated_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $product = Product::where('company_id', $user->company_id)->findOrFail($id);

        // Clean up image files from disk before soft-deleting the row.
        // If the SoftDeletes record is later restored the buyer would see
        // missing thumbnails, but that's the lesser evil — leaving orphan
        // files on disk is the bigger long-term problem.
        if (is_array($product->images)) {
            foreach ($product->images as $path) {
                if (is_string($path) && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $product->delete();

        return redirect()
            ->route('dashboard.products.index')
            ->with('status', __('catalog.deleted_successfully'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // BUYER SIDE — browse marketplace + Buy-Now
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Public-ish catalog browse. Buyers see active products from every
     * supplier company except their own.
     *
     * Phase 4 / Sprint 18 — added filters: price range, supplier country,
     * supplier verification level, in-stock toggle. The filters cooperate
     * with the existing q + category_id so all of them stack.
     */
    public function browse(Request $request): View
    {
        $companyId  = auth()->user()->company_id;
        $query      = $request->input('q');
        $catId      = $request->input('category_id');
        $priceMin   = $request->input('price_min');
        $priceMax   = $request->input('price_max');
        $country    = $request->input('country');
        $verifLevel = $request->input('verification');
        $inStock    = $request->boolean('in_stock');

        $products = Product::query()
            ->with(['company', 'category', 'variants'])
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', '!=', $companyId))
            ->when($catId, fn ($q) => $q->where('category_id', $catId))
            ->when($query, fn ($q) => $q->search($query, ['name', 'description', 'sku']))
            // Numeric price range filters apply to base_price. Variant
            // modifiers are ignored at the SQL layer (premature for
            // Phase 4); the variant-aware "From X" label still renders.
            ->when($priceMin !== null && $priceMin !== '', fn ($q) => $q->where('base_price', '>=', (float) $priceMin))
            ->when($priceMax !== null && $priceMax !== '', fn ($q) => $q->where('base_price', '<=', (float) $priceMax))
            ->when($inStock, fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('stock_qty')->orWhere('stock_qty', '>', 0);
            }))
            ->when($country, fn ($q, $c) => $q->whereHas('company', fn ($q2) => $q2->where('country', $c)))
            ->when($verifLevel, fn ($q, $v) => $q->whereHas('company', fn ($q2) => $q2->where('verification_level', $v)))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Country dropdown is sourced from the supplier set actually on
        // the platform — this stays stable as the catalog grows but
        // doesn't pollute the UI with countries we have no suppliers in.
        $countries = \App\Models\Company::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return view('dashboard.catalog.browse', compact(
            'products', 'categories', 'countries',
            'query', 'catId', 'priceMin', 'priceMax', 'country', 'verifLevel', 'inStock',
        ));
    }

    public function show(int $id): View
    {
        $product = Product::with(['company', 'category', 'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])
            ->findOrFail($id);
        abort_unless($product->is_active, 404);

        return view('dashboard.catalog.show', compact('product'));
    }

    /**
     * Buy-Now: skip the RFQ pipeline and create a Contract directly. The
     * buyer's company becomes the buyer party, the product's company the
     * supplier party. Quantity is validated against min_order_qty + stock.
     */
    public function buyNow(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $product = Product::with('company')->findOrFail($id);

        if (!$product->isPurchasable()) {
            return back()->withErrors(['quantity' => __('catalog.not_purchasable')]);
        }

        if ($product->company_id === $user->company_id) {
            return back()->withErrors(['quantity' => __('catalog.cannot_buy_own_product')]);
        }

        $maxQty = $product->stock_qty ?? 1_000_000;
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:' . $product->min_order_qty, 'max:' . $maxQty],
        ]);

        $contract = $this->contractService->createFromProduct(
            product: $product,
            buyerCompanyId: $user->company_id,
            buyerUserId: $user->id,
            quantity: (int) $data['quantity'],
        );

        return redirect()
            ->route('dashboard.contracts.show', ['id' => $contract->id])
            ->with('status', __('catalog.purchase_created'));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'category_id'    => ['nullable', 'exists:categories,id'],
            'sku'            => ['nullable', 'string', 'max:64'],
            'hs_code'        => ['nullable', 'string', 'max:16'],
            'name'           => ['required', 'string', 'max:191'],
            'name_ar'        => ['nullable', 'string', 'max:191'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'base_price'     => ['required', 'numeric', 'min:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'unit'           => ['required', 'string', 'max:32'],
            'min_order_qty'  => ['required', 'integer', 'min:1'],
            'stock_qty'      => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active'      => ['sometimes', 'boolean'],

            // Image upload — multiple files via images[]. Hard cap of 6 keeps
            // storage and the front-end thumbnail strip predictable. Each
            // file is capped at 4 MB to match the company-logo precedent.
            'images'         => ['nullable', 'array', 'max:6'],
            'images.*'       => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // Edit form: paths the user wants to keep. Anything in the
            // product's existing images that isn't in this list is deleted.
            'existing_images'   => ['nullable', 'array', 'max:6'],
            'existing_images.*' => ['string'],
        ]);
    }

    /**
     * Persist newly-uploaded images and reconcile them with the existing
     * set the form decided to keep. Returns the merged path array ready
     * to be saved on the product's `images` JSON column.
     *
     * The merge order is `[...kept, ...newly uploaded]`, so the supplier
     * can re-order images by re-uploading them in their preferred order
     * after removing all current images. The first entry is treated as
     * the primary thumbnail by the catalog views.
     *
     * @param  list<string>  $existingPaths  All paths currently on the product (before update)
     * @param  list<string>  $keepPaths      Paths the form sent back as `existing_images[]`
     * @return list<string>
     */
    private function syncImages(Request $request, int $companyId, array $existingPaths, array $keepPaths): array
    {
        // Normalise existing & keep so we can compare safely.
        $existing = array_values(array_filter($existingPaths, 'is_string'));
        $keep     = array_values(array_filter($keepPaths, 'is_string'));

        // Anything in $existing that isn't in $keep needs to be deleted.
        $toDelete = array_diff($existing, $keep);
        foreach ($toDelete as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // Persist any newly-uploaded files. Each file lands under a
        // company-scoped directory so two suppliers can never collide
        // and a single `rm -rf` cleans up everything for a company on
        // account closure.
        $uploaded = [];
        foreach ((array) $request->file('images', []) as $file) {
            if ($file === null) {
                continue;
            }
            $uploaded[] = $file->store("products/{$companyId}/images", 'public');
        }

        return array_values(array_merge($keep, $uploaded));
    }

    /**
     * Phase 4 / Sprint 15 — persist the variants posted from the product
     * form. The form sends an indexed array `variants[i] = {id, name,
     * sku, price_modifier, stock_qty, attributes_json, _delete}`. We
     * upsert by id (or create when null) and delete any rows the form
     * marked for removal.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncVariants(Product $product, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        DB::transaction(function () use ($product, $rows) {
            foreach ($rows as $row) {
                // Skip totally empty rows the form may submit when the
                // user clicks "Add" but doesn't fill anything in.
                if (empty($row['name']) && empty($row['id'])) {
                    continue;
                }

                // Soft-delete an existing variant the user toggled to remove.
                if (!empty($row['_delete']) && !empty($row['id'])) {
                    ProductVariant::where('product_id', $product->id)
                        ->where('id', $row['id'])
                        ->delete();
                    continue;
                }

                $attributes = null;
                if (!empty($row['attributes_json'])) {
                    $decoded = json_decode((string) $row['attributes_json'], true);
                    $attributes = is_array($decoded) ? $decoded : null;
                }

                $payload = [
                    'product_id'     => $product->id,
                    'name'           => (string) ($row['name'] ?? 'Default'),
                    'sku'            => $row['sku'] ?? null,
                    'attributes'     => $attributes,
                    'price_modifier' => (float) ($row['price_modifier'] ?? 0),
                    'stock_qty'      => isset($row['stock_qty']) && $row['stock_qty'] !== '' ? (int) $row['stock_qty'] : null,
                    'is_active'      => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];

                if (!empty($row['id'])) {
                    ProductVariant::where('product_id', $product->id)
                        ->where('id', $row['id'])
                        ->update($payload);
                } else {
                    ProductVariant::create($payload);
                }
            }
        });
    }

    /**
     * AJAX endpoint used by the product create/edit form to suggest HS
     * codes from the description in real time. Returns up to 3 candidates
     * from Claude (live mode) or the keyword fallback (offline mode).
     */
    public function suggestHsCode(Request $request, HsCodeClassificationService $service): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:1000'],
            'country'     => ['nullable', 'string', 'size:2'],
        ]);

        return response()->json($service->suggest($data['description'], $data['country'] ?? null));
    }
}
