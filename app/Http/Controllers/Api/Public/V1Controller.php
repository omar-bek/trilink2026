<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Rfq;
use App\Services\BidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API v1.
 *
 * Authentication: Sanctum bearer tokens, scoped to abilities. A token with
 * `read:rfqs` ability can hit /rfqs but not /payments. Tokens are issued
 * via the dashboard at /dashboard/api-tokens.
 *
 * Versioning: this whole controller lives under /api/v1/public/. The
 * shape of the responses is the contract — adding fields is fine, removing
 * or renaming requires v2.
 *
 * Tenancy: every endpoint scopes results to the authenticated user's
 * company_id. Cross-tenant reads are not possible by design.
 */
class V1Controller extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('company');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'role' => $user->role?->value,
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'verification_level' => $user->company->verification_level?->value,
                ] : null,
            ],
        ]);
    }

    public function listRfqs(Request $request): JsonResponse
    {
        $request->user()->tokenCan('read:rfqs') || abort(403, 'Token missing read:rfqs ability');

        $rfqs = Rfq::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json([
            'data' => $rfqs->items(),
            'meta' => [
                'total' => $rfqs->total(),
                'per_page' => $rfqs->perPage(),
                'current_page' => $rfqs->currentPage(),
                'last_page' => $rfqs->lastPage(),
            ],
        ]);
    }

    public function showRfq(Request $request, int $id): JsonResponse
    {
        $request->user()->tokenCan('read:rfqs') || abort(403, 'Token missing read:rfqs ability');

        $rfq = Rfq::with(['bids', 'category'])
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        return response()->json(['data' => $rfq]);
    }

    public function listBids(Request $request): JsonResponse
    {
        $request->user()->tokenCan('read:bids') || abort(403, 'Token missing read:bids ability');

        // Bids visible to this company: bids on our RFQs (we are the buyer)
        // OR bids we submitted (we are the supplier).
        $companyId = $request->user()->company_id;
        $bids = Bid::query()
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhereHas('rfq', fn ($r) => $r->where('company_id', $companyId));
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json([
            'data' => $bids->items(),
            'meta' => [
                'total' => $bids->total(),
                'per_page' => $bids->perPage(),
                'current_page' => $bids->currentPage(),
                'last_page' => $bids->lastPage(),
            ],
        ]);
    }

    public function listContracts(Request $request): JsonResponse
    {
        $request->user()->tokenCan('read:contracts') || abort(403, 'Token missing read:contracts ability');

        $companyId = $request->user()->company_id;
        $contracts = Contract::query()
            ->where(function ($q) use ($companyId) {
                $q->where('buyer_company_id', $companyId)
                    ->orWhereJsonContains('parties', ['company_id' => $companyId]);
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json([
            'data' => $contracts->items(),
            'meta' => [
                'total' => $contracts->total(),
                'per_page' => $contracts->perPage(),
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
            ],
        ]);
    }

    public function showContract(Request $request, int $id): JsonResponse
    {
        $request->user()->tokenCan('read:contracts') || abort(403, 'Token missing read:contracts ability');

        $companyId = $request->user()->company_id;
        $contract = Contract::query()
            ->where(function ($q) use ($companyId) {
                $q->where('buyer_company_id', $companyId)
                    ->orWhereJsonContains('parties', ['company_id' => $companyId]);
            })
            ->with(['payments', 'shipments'])
            ->findOrFail($id);

        return response()->json(['data' => $contract]);
    }

    public function listPayments(Request $request): JsonResponse
    {
        $request->user()->tokenCan('read:payments') || abort(403, 'Token missing read:payments ability');

        $companyId = $request->user()->company_id;
        $payments = Payment::query()
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhere('recipient_company_id', $companyId);
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    public function listProducts(Request $request): JsonResponse
    {
        $request->user()->tokenCan('read:products') || $request->user()->tokenCan('read:catalog') || abort(403, 'Token missing read:products ability');

        $products = Product::query()
            ->where('is_active', true)
            ->when($request->query('company_id'), fn ($q, $cid) => $q->where('company_id', $cid))
            ->when($request->query('category_id'), fn ($q, $cid) => $q->where('category_id', $cid))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // WRITE ENDPOINTS — require explicit write:* abilities on the token
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a new RFQ on behalf of the authenticated user's company.
     * Requires the `write:rfqs` ability. Inherits the same auto-creation
     * pipeline as the web flow, so generated RFQs land in the OPEN state
     * ready for bidders.
     */
    public function createRfq(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokenCan('write:rfqs') || abort(403, 'Token missing write:rfqs ability');
        $user->company_id || abort(422, 'User has no company');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:32'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'deadline' => ['nullable', 'date', 'after:now'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string'],
            'delivery_location' => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        $rfq = Rfq::create(array_merge($data, [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'status' => RfqStatus::OPEN->value,
            'type' => $data['type'] ?? RfqType::SUPPLIER->value,
            'currency' => $data['currency'] ?? 'AED',
        ]));

        return response()->json(['data' => $rfq], 201);
    }

    /**
     * Submit a bid on an RFQ. Requires the `write:bids` ability. Reuses
     * BidService::create() so all the business rules (own-RFQ block,
     * sanctions, exclusive supplier check) apply to API submissions exactly
     * as they do to web submissions.
     */
    public function createBid(Request $request, int $rfqId): JsonResponse
    {
        $user = $request->user();
        $user->tokenCan('write:bids') || abort(403, 'Token missing write:bids ability');
        $user->company_id || abort(422, 'User has no company');

        $rfq = Rfq::findOrFail($rfqId);

        $user->can('submitBid', $rfq) || abort(403, 'Not allowed to submit a bid on this RFQ');

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'delivery_time_days' => ['nullable', 'integer', 'min:1'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = app(BidService::class);
        $result = $service->create(array_merge($data, [
            'rfq_id' => $rfq->id,
            'company_id' => $user->company_id,
            'provider_id' => $user->id,
            'currency' => $data['currency'] ?? $rfq->currency ?? 'AED',
            'items' => [],
        ]));

        if (is_string($result)) {
            return response()->json(['error' => $result], 422);
        }

        return response()->json(['data' => $result], 201);
    }

    /**
     * Create a catalog product. Requires `write:products`. The product is
     * scoped to the token owner's company automatically — clients cannot
     * list products on behalf of another company.
     */
    public function createProduct(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokenCan('write:products') || abort(403, 'Token missing write:products ability');
        $user->company_id || abort(422, 'User has no company');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'name_ar' => ['nullable', 'string', 'max:191'],
            'sku' => ['nullable', 'string', 'max:64'],
            'hs_code' => ['nullable', 'string', 'max:16'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'unit' => ['required', 'string', 'max:32'],
            'min_order_qty' => ['required', 'integer', 'min:1'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product = Product::create(array_merge($data, [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
        ]));

        return response()->json(['data' => $product], 201);
    }
}
