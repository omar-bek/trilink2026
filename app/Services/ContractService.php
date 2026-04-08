<?php

namespace App\Services;

use App\Enums\AmendmentStatus;
use App\Enums\ContractStatus;
use App\Enums\RfqType;
use App\Events\ContractSigned;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractVersion;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\ContractSignedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ContractService
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return Contract::query()
            ->when($filters['buyer_company_id'] ?? null, fn ($q, $v) => $q->where('buyer_company_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('buyer_company_id', $v)
                    ->orWhereJsonContains('parties', ['company_id' => $v]);
            }))
            ->with('buyerCompany')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Contract
    {
        return Contract::with(['buyerCompany', 'purchaseRequest', 'payments', 'shipments', 'disputes'])->find($id);
    }

    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $contract = Contract::create($data);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => 1,
                'snapshot' => $contract->toArray(),
                'created_by' => auth()->id(),
            ]);

            return $contract->load('buyerCompany');
        });
    }

    public function update(int $id, array $data): ?Contract
    {
        $contract = Contract::find($id);
        if (!$contract) return null;

        $contract->update($data);
        return $contract->fresh('buyerCompany');
    }

    public function delete(int $id): bool
    {
        $contract = Contract::find($id);
        return $contract ? $contract->delete() : false;
    }

    public function sign(int $id, int $userId, int $companyId, ?string $signature = null): Contract|string
    {
        $contract = Contract::find($id);
        if (!$contract) return 'Contract not found';

        if (!in_array($contract->status, [ContractStatus::DRAFT, ContractStatus::PENDING_SIGNATURES])) {
            return 'Contract is not in a signable state';
        }

        // Authorization: only a company that is actually a party of the
        // contract may sign. Without this check any user holding the
        // generic `contract.sign` permission could attach a forged
        // signature to a contract their company is not party to.
        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (!in_array($companyId, $partyCompanyIds, true)) {
            return 'Your company is not a party to this contract';
        }

        $signatures = $contract->signatures ?? [];
        $alreadySigned = collect($signatures)->where('company_id', $companyId)->isNotEmpty();

        if ($alreadySigned) {
            return 'This party has already signed';
        }

        $signatures[] = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'signature' => $signature,
            'signed_at' => now()->toISOString(),
        ];

        $contract->update([
            'signatures' => $signatures,
            'status' => ContractStatus::PENDING_SIGNATURES,
        ]);

        if ($contract->allPartiesHaveSigned()) {
            $contract->update(['status' => ContractStatus::ACTIVE]);

            // The moment all parties have signed, materialise the
            // payment_schedule into real Payment records (PENDING_APPROVAL)
            // so the buyer's finance team can act on the milestones via the
            // existing Payment dashboard. Idempotent inside the service.
            $this->paymentService->generateFromSchedule($contract->fresh());

            // Phase 3 / Sprint 13 — fire the ContractSigned event so the
            // ReleaseEscrowOnSignature listener can drain the on_signature
            // milestones (typically the advance) without coupling the
            // service to the escrow layer directly.
            ContractSigned::dispatch($contract->fresh(), $companyId);
        }

        // Notify every user belonging to a party of the contract that a new
        // signature was applied. Each user sees who signed; the formatter
        // routes them to the contract page on click.
        $signer = User::find($userId);
        $signerName = $signer
            ? trim(($signer->first_name ?? '') . ' ' . ($signer->last_name ?? ''))
            : 'A party';

        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (!empty($partyCompanyIds)) {
            $recipients = User::whereIn('company_id', $partyCompanyIds)->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ContractSignedNotification($contract, $signerName ?: 'A party'));
            }
        }

        return $contract->fresh('buyerCompany');
    }

    /**
     * Generate a contract from an accepted bid.
     *
     * Idempotent: if a contract already exists for the same RFQ + supplier
     * pair we return that one instead of creating a duplicate. This is the
     * function the buyer hits the moment they accept a bid — it builds the
     * party list from the buyer's company + bid's supplier company, parses
     * the bid's payment_terms string into a real milestone schedule, and
     * stamps a default boilerplate Terms & Conditions block so the contract
     * is signable on render.
     */
    public function createFromBid(Bid $bid): Contract
    {
        return DB::transaction(function () use ($bid) {
            $bid->loadMissing(['rfq.purchaseRequest', 'rfq.company', 'company']);

            $rfq = $bid->rfq;
            if (!$rfq) {
                throw new \RuntimeException('Bid has no associated RFQ.');
            }

            // For sales-side RFQs the publishing company is the SELLER and
            // the bidder is the BUYER, so we swap the roles before building
            // the contract parties. Every other RFQ type keeps the standard
            // "RFQ author = buyer" mapping.
            $rfqType = $rfq->type instanceof RfqType ? $rfq->type : RfqType::tryFrom((string) $rfq->type);
            $isSalesOffer = $rfqType?->bidderIsBuyer() ?? false;

            $buyerCompanyId    = $isSalesOffer ? $bid->company_id : $rfq->company_id;
            $supplierCompanyId = $isSalesOffer ? $rfq->company_id : $bid->company_id;

            // Idempotency check: if this RFQ already has a contract for the
            // winning supplier, return it instead of double-creating.
            $existing = Contract::query()
                ->where('buyer_company_id', $buyerCompanyId)
                ->when($rfq->purchase_request_id, fn ($q) => $q->where('purchase_request_id', $rfq->purchase_request_id))
                ->whereJsonContains('parties', ['company_id' => $supplierCompanyId])
                ->first();

            if ($existing) {
                return $existing;
            }

            $price        = (float) $bid->price;
            $currency     = $bid->currency ?? 'AED';
            $deliveryDays = (int) ($bid->delivery_time_days ?? 30);
            $startDate    = Carbon::now()->startOfDay();
            $endDate      = $startDate->copy()->addDays($deliveryDays);

            // Phase 2: prefer the bid's own pre-computed VAT snapshot when
            // it exists. The supplier filled in the form with a specific
            // tax treatment (exclusive / inclusive / not_applicable), the
            // form computed subtotal+tax+total at submit time, and we
            // stored those values verbatim. The contract MUST honour what
            // the supplier saw on screen — recomputing here would silently
            // shift the totals if the tax_rates table has changed since
            // submission, or if the supplier picked "inclusive" but we
            // re-treated their price as a subtotal.
            //
            // Legacy bids submitted before the trade-fields migration have
            // these columns NULL, so we fall back to the old behaviour
            // (auto-resolve current rate, treat price as subtotal).
            if ($bid->subtotal_excl_tax !== null && $bid->total_incl_tax !== null) {
                $subtotal    = (float) $bid->subtotal_excl_tax;
                $taxRate     = (float) ($bid->tax_rate_snapshot ?? 0);
                $taxAmount   = (float) ($bid->tax_amount ?? 0);
                $totalAmount = (float) $bid->total_incl_tax;
            } else {
                $taxRate     = TaxRate::resolveFor($rfq->category_id, $rfq->company?->country);
                $subtotal    = $price;
                $taxAmount   = round($price * $taxRate / 100, 2);
                $totalAmount = round($price + $taxAmount, 2);
            }

            $contract = Contract::create([
                'title'               => $rfq->title,
                'description'         => $rfq->description,
                'purchase_request_id' => $rfq->purchase_request_id,
                'buyer_company_id'    => $buyerCompanyId,
                'status'              => ContractStatus::PENDING_SIGNATURES,
                'parties'             => [
                    [
                        'company_id' => $buyerCompanyId,
                        'role'       => 'buyer',
                        'name'       => $isSalesOffer ? $bid->company?->name : $rfq->company?->name,
                    ],
                    [
                        'company_id' => $supplierCompanyId,
                        'role'       => 'supplier',
                        'name'       => $isSalesOffer ? $rfq->company?->name : $bid->company?->name,
                    ],
                ],
                'amounts' => [
                    'subtotal'      => $subtotal,
                    'tax_rate'      => $taxRate,
                    'tax'           => $taxAmount,
                    'total'         => $totalAmount,
                    // Phase 2 — preserve the supplier's declared treatment
                    // and the trade context so the contract show page can
                    // render an accurate Tax Invoice header (TRN, Incoterm,
                    // country of origin, exemption reason).
                    'tax_treatment' => $bid->tax_treatment ?? 'exclusive',
                    'tax_exemption_reason' => $bid->tax_exemption_reason,
                    'incoterm'      => $bid->incoterm,
                    'country_of_origin' => $bid->country_of_origin,
                    'hs_code'       => $bid->hs_code,
                ],
                'total_amount'     => $totalAmount,
                'currency'         => $currency,
                'payment_schedule' => $this->buildPaymentScheduleFromBid($bid, $startDate, $endDate, $taxRate, $subtotal),
                'signatures'       => [],
                'terms'            => json_encode(
                    $this->buildContractTerms($bid, $rfq, $deliveryDays),
                    JSON_UNESCAPED_UNICODE
                ),
                'start_date'       => $startDate,
                'end_date'         => $endDate,
                'version'          => 1,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version'     => 1,
                'snapshot'    => $contract->toArray(),
                'created_by'  => auth()->id() ?? $bid->provider_id,
            ]);

            return $contract->load('buyerCompany');
        });
    }

    /**
     * Generate a contract directly from a catalog Product (Buy-Now flow).
     *
     * Bypasses the RFQ → Bid → Acceptance round-trip for standard goods.
     * The buyer picks a quantity, we compute the line total, look up the
     * platform tax rate, build a 30/70 advance/delivery payment schedule,
     * and create a PENDING_SIGNATURES contract — same downstream pipeline
     * as createFromBid (signing → ACTIVE → auto Payment generation).
     *
     * Idempotency: each call creates a fresh contract. The Buy-Now action
     * is intentionally a one-shot purchase, not a draft cart.
     */
    public function createFromProduct(Product $product, int $buyerCompanyId, int $buyerUserId, int $quantity): Contract
    {
        return DB::transaction(function () use ($product, $buyerCompanyId, $buyerUserId, $quantity) {
            $product->loadMissing(['company', 'category']);

            $supplierCompanyId = $product->company_id;
            $unitPrice         = (float) $product->base_price;
            $subtotal          = round($unitPrice * $quantity, 2);
            $currency          = $product->currency ?? 'AED';
            $leadTime          = max(1, (int) $product->lead_time_days);
            $startDate         = Carbon::now()->startOfDay();
            $endDate           = $startDate->copy()->addDays($leadTime);

            // Same tax precedence used by createFromBid — keeps Buy-Now and
            // RFQ-driven contracts taxed identically.
            $taxRate     = TaxRate::resolveFor($product->category_id, $product->company?->country);
            $taxAmount   = round($subtotal * $taxRate / 100, 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $contract = Contract::create([
                'title'               => $product->name,
                'description'         => $product->description,
                'purchase_request_id' => null,
                'buyer_company_id'    => $buyerCompanyId,
                'status'              => ContractStatus::PENDING_SIGNATURES,
                'parties'             => [
                    [
                        'company_id' => $buyerCompanyId,
                        'role'       => 'buyer',
                        'name'       => null,
                    ],
                    [
                        'company_id' => $supplierCompanyId,
                        'role'       => 'supplier',
                        'name'       => $product->company?->name,
                    ],
                ],
                'amounts' => [
                    'unit_price' => $unitPrice,
                    'quantity'   => $quantity,
                    'subtotal'   => $subtotal,
                    'tax_rate'   => $taxRate,
                    'tax'        => $taxAmount,
                    'total'      => $totalAmount,
                ],
                'total_amount'     => $totalAmount,
                'currency'         => $currency,
                'payment_schedule' => $this->buildBuyNowSchedule($subtotal, $currency, $startDate, $endDate, $taxRate),
                'signatures'       => [],
                'terms'            => json_encode(
                    $this->buildUaeContractTerms(
                        scopeTitle:       __('catalog.term_buy_now_scope', [
                            'qty'     => $quantity,
                            'unit'    => $product->unit,
                            'product' => $product->name,
                        ]),
                        totalValueLabel:  $currency . ' ' . number_format($totalAmount, 2),
                        paymentBreakdown: __('catalog.term_buy_now_payment'),
                        deliveryDays:     $leadTime,
                    ),
                    JSON_UNESCAPED_UNICODE
                ),
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'version'    => 1,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version'     => 1,
                'snapshot'    => $contract->toArray(),
                'created_by'  => $buyerUserId,
            ]);

            // Decrement stock if the supplier tracks it. Concurrent buyers
            // racing the same SKU is rare for now; once volume picks up we
            // can move this to a row-level lock.
            if ($product->stock_qty !== null) {
                $product->decrement('stock_qty', $quantity);
            }

            return $contract->load('buyerCompany');
        });
    }

    /**
     * Phase 4 / Sprint 17 — multi-supplier checkout. Splits the user's
     * cart by supplier_company_id and creates ONE Contract per supplier.
     * Each contract carries the full set of line items in its `amounts`
     * JSON so the contract show page can render the breakdown without
     * needing a separate contract_items table (Phase 5+).
     *
     * Atomicity: all contracts are created inside a single transaction.
     * If any one supplier fails (sanctions hit, exclusive supplier rule,
     * tax lookup error), the entire checkout rolls back.
     *
     * @return Contract[] freshly-created contracts, one per supplier
     */
    public function createFromCart(Cart $cart, User $user): array
    {
        $cart->loadMissing(['items.product.company', 'items.variant']);

        if ($cart->items->isEmpty()) {
            return [];
        }

        // Group items by supplier_company_id. Per supplier we'll spin up
        // one PENDING_SIGNATURES contract — all in a single transaction
        // so the buyer never ends up with a half-checked-out cart.
        $bySupplier = $cart->items->groupBy('supplier_company_id');

        return DB::transaction(function () use ($bySupplier, $user) {
            $contracts = [];
            foreach ($bySupplier as $supplierCompanyId => $items) {
                $contracts[] = $this->createCartContractForSupplier(
                    items: $items,
                    buyerCompanyId: $user->company_id,
                    buyerUserId: $user->id,
                    supplierCompanyId: (int) $supplierCompanyId,
                );
            }
            return $contracts;
        });
    }

    /**
     * Build a single per-supplier contract from a slice of cart items
     * sharing the same supplier. Helper for createFromCart() — kept
     * private because it makes a bunch of decisions (tax lookup,
     * payment schedule shape) that should never be invoked outside the
     * cart-checkout pipeline.
     */
    private function createCartContractForSupplier($items, int $buyerCompanyId, int $buyerUserId, int $supplierCompanyId): Contract
    {
        // All items in this slice share the same currency by definition
        // — they were added from the same product catalog. Lead time is
        // the maximum across the slice (delivery is constrained by the
        // slowest item).
        $currency = $items->first()->currency ?: 'AED';
        $supplierCompany = \App\Models\Company::find($supplierCompanyId);
        $supplierCountry = $supplierCompany?->country;

        // Subtotal = Σ(quantity × unit_price). VAT applies on top using
        // the same TaxRate::resolveFor precedence as the single-product
        // Buy-Now flow.
        $subtotal = 0.0;
        $lineSnapshots = [];
        foreach ($items as $item) {
            $line = round($item->quantity * (float) $item->unit_price, 2);
            $subtotal += $line;
            $lineSnapshots[] = [
                'product_id'         => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name'               => $item->name_snapshot,
                'attributes'         => $item->attributes_snapshot,
                'quantity'           => $item->quantity,
                'unit_price'         => (float) $item->unit_price,
                'currency'           => $item->currency,
                'line_total'         => $line,
            ];
        }
        $subtotal = round($subtotal, 2);

        // Lead time = max across all lines (we deliver when the slowest
        // SKU is ready). Defaults to 7 days if a product has no lead time.
        $leadTime = max(7, $items->max(fn ($i) => (int) ($i->product?->lead_time_days ?? 7)));
        $startDate = Carbon::now()->startOfDay();
        $endDate   = $startDate->copy()->addDays($leadTime);

        // Use the first product's category for tax lookup — fine for the
        // overwhelming majority of multi-line orders that share a category.
        $primaryCategoryId = $items->first()->product?->category_id;
        $taxRate    = TaxRate::resolveFor($primaryCategoryId, $supplierCountry);
        $taxAmount  = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $title = $items->count() === 1
            ? $items->first()->name_snapshot
            : sprintf('%s + %d more', $items->first()->name_snapshot, $items->count() - 1);

        $contract = Contract::create([
            'title'               => $title,
            'description'         => null,
            'purchase_request_id' => null,
            'buyer_company_id'    => $buyerCompanyId,
            'status'              => ContractStatus::PENDING_SIGNATURES,
            'parties'             => [
                ['company_id' => $buyerCompanyId,    'role' => 'buyer',    'name' => null],
                ['company_id' => $supplierCompanyId, 'role' => 'supplier', 'name' => $supplierCompany?->name],
            ],
            'amounts' => [
                'lines'    => $lineSnapshots,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax'      => $taxAmount,
                'total'    => $totalAmount,
            ],
            'total_amount'     => $totalAmount,
            'currency'         => $currency,
            'payment_schedule' => $this->buildBuyNowSchedule($subtotal, $currency, $startDate, $endDate, $taxRate),
            'signatures'       => [],
            'terms'            => json_encode(
                $this->buildUaeContractTerms(
                    scopeTitle:       collect($lineSnapshots)
                        ->map(fn ($l) => sprintf('%d × %s', $l['quantity'], $l['name']))
                        ->implode(' • '),
                    totalValueLabel:  $currency . ' ' . number_format($totalAmount, 2),
                    paymentBreakdown: __('catalog.term_buy_now_payment'),
                    deliveryDays:     $leadTime,
                ),
                JSON_UNESCAPED_UNICODE
            ),
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'version'    => 1,
        ]);

        ContractVersion::create([
            'contract_id' => $contract->id,
            'version'     => 1,
            'snapshot'    => $contract->toArray(),
            'created_by'  => $buyerUserId,
        ]);

        // Decrement stock per line, mirroring the single-product Buy-Now
        // path. Variants take priority over the parent product when both
        // track stock.
        foreach ($items as $item) {
            $variant = $item->variant;
            $product = $item->product;
            if ($variant && $variant->stock_qty !== null) {
                $variant->decrement('stock_qty', $item->quantity);
            } elseif ($product && $product->stock_qty !== null) {
                $product->decrement('stock_qty', $item->quantity);
            }
        }

        return $contract->load('buyerCompany');
    }

    /**
     * Two-milestone schedule for Buy-Now contracts: 30% advance on signing,
     * 70% on delivery. Mirrors what most catalog suppliers ask for and keeps
     * the Buy-Now UX a one-click purchase.
     */
    private function buildBuyNowSchedule(float $subtotal, string $currency, Carbon $startDate, Carbon $endDate, float $taxRate): array
    {
        return [
            [
                'milestone'         => 'advance',
                'percentage'        => 30,
                'amount'            => round($subtotal * 0.30, 2),
                'tax_rate'          => $taxRate,
                'tax_amount'        => round($subtotal * 0.30 * $taxRate / 100, 2),
                'currency'          => $currency,
                'due_date'          => $startDate->toDateString(),
                // Phase 3 / Sprint 13 / task 3.10 — escrow release rule.
                // Buy-Now advances always release the moment all parties
                // sign so the supplier sees money immediately.
                'release_condition' => 'on_signature',
            ],
            [
                'milestone'         => 'delivery',
                'percentage'        => 70,
                'amount'            => round($subtotal * 0.70, 2),
                'tax_rate'          => $taxRate,
                'tax_amount'        => round($subtotal * 0.70 * $taxRate / 100, 2),
                'currency'          => $currency,
                'due_date'          => $endDate->toDateString(),
                'release_condition' => 'on_delivery',
            ],
        ];
    }

    /**
     * Parse the bid's free-form payment_terms (e.g. "30% advance, 50% on
     * production, 20% on delivery") into a structured milestone schedule
     * the contract show view can render. Falls back to a 30/70 split when
     * no percentages can be parsed.
     *
     * Each milestone carries the resolved tax rate so the auto payment
     * generator can compute VAT consistently with the parent contract.
     */
    private function buildPaymentScheduleFromBid(Bid $bid, Carbon $startDate, Carbon $endDate, float $taxRate = 0.0, ?float $subtotalOverride = null): array
    {
        $terms = (string) ($bid->payment_terms ?? '');

        $percentages = [];
        if (preg_match_all('/(\d+)\s*%/', $terms, $m)) {
            $percentages = array_map('intval', $m[1]);
        }

        if (empty($percentages)) {
            $percentages = [30, 70];
        }

        // Force the schedule to sum to exactly 100% by absorbing any rounding
        // drift into the last milestone.
        $sum = array_sum($percentages);
        if ($sum !== 100 && $sum > 0) {
            $percentages[count($percentages) - 1] += (100 - $sum);
        }

        // Conventional milestone keys — match what ContractController.show
        // already maps for display labels.
        $milestoneKeys = ['advance', 'production', 'delivery', 'final'];

        $count = count($percentages);
        // Use the explicit subtotal if the caller provided one (Phase 2 path
        // — the supplier may have submitted a VAT-inclusive price, in which
        // case `bid->price` is the gross total, not the subtotal). Falls
        // back to the legacy behaviour for old bids.
        $price = $subtotalOverride !== null
            ? (float) $subtotalOverride
            : (float) ($bid->subtotal_excl_tax ?? $bid->price);
        $currency = $bid->currency ?? 'AED';
        $totalDays = max(1, (int) $startDate->diffInDays($endDate));

        $schedule = [];
        foreach ($percentages as $i => $pct) {
            $key = $milestoneKeys[$i] ?? 'milestone_' . ($i + 1);

            // Spread milestone due dates evenly across the contract window:
            // first one due at start, last one at end, the rest interpolated.
            $offset = $count <= 1 ? 0 : (int) round(($totalDays * $i) / ($count - 1));
            $dueDate = $startDate->copy()->addDays($offset);

            $amount = round($price * $pct / 100, 2);

            // Phase 3 / Sprint 13 / task 3.10 — pick a default escrow
            // release condition for each milestone key. The buyer can edit
            // this on the contract show page before activating escrow if
            // they want a different cadence (e.g. require inspection).
            $releaseCondition = match ($key) {
                'advance'    => 'on_signature',
                'production' => 'on_inspection_pass',
                'delivery'   => 'on_delivery',
                'final'      => 'manual',
                default      => 'manual',
            };

            $schedule[] = [
                'milestone'         => $key,
                'percentage'        => $pct,
                'amount'            => $amount,
                'tax_rate'          => $taxRate,
                'tax_amount'        => round($amount * $taxRate / 100, 2),
                'currency'          => $currency,
                'due_date'          => $dueDate->toDateString(),
                'release_condition' => $releaseCondition,
            ];
        }

        return $schedule;
    }

    /**
     * Build the boilerplate Terms & Conditions sections for an auto-generated
     * contract. The values are translation keys so each section reads in the
     * user's language; bid-specific values (price, delivery days, payment
     * terms) are interpolated as parameters.
     *
     * The clause set is drafted to be enforceable under UAE federal law:
     * Civil Transactions Code (Federal Law 5/1985), Commercial Transactions
     * Law (Federal Decree-Law 50/2022), VAT Law (Federal Decree-Law 8/2017),
     * Electronic Transactions & Trust Services Law (Federal Decree-Law
     * 46/2021), PDPL (Federal Decree-Law 45/2021), AML (Federal Decree-Law
     * 20/2018) and the bankruptcy regime (Federal Decree-Law 51/2023).
     * Disputes default to DIAC seated in Dubai. The Arabic version of the
     * Agreement prevails before UAE courts (Federal Law 26/1981).
     */
    private function buildContractTerms(Bid $bid, $rfq, int $deliveryDays): array
    {
        $price    = number_format((float) $bid->price, 2);
        $currency = $bid->currency ?? 'AED';
        $totalRef = $currency . ' ' . $price;

        return $this->buildUaeContractTerms(
            scopeTitle:       (string) $rfq->title,
            totalValueLabel:  $totalRef,
            paymentBreakdown: (string) ($bid->payment_terms ?? '—'),
            deliveryDays:     $deliveryDays,
        );
    }

    /**
     * Single source of truth for the UAE-grade clause set. Used by every
     * contract-creation path (bid acceptance, Buy-Now, Cart checkout) so
     * the legal text stays identical regardless of how the contract was
     * spawned. Returned as an array of {title, items} sections — each
     * value is a translation key so the contract show + PDF render in
     * the user's current locale.
     */
    private function buildUaeContractTerms(
        string $scopeTitle,
        string $totalValueLabel,
        string $paymentBreakdown,
        int $deliveryDays,
    ): array {
        return [
            [
                'title' => __('contracts.scope_of_work'),
                'items' => [
                    __('contracts.term_scope_work', ['title' => $scopeTitle]),
                    __('contracts.term_scope_quality'),
                ],
            ],
            [
                'title' => __('contracts.payment_terms'),
                'items' => [
                    __('contracts.term_total_value', ['amount' => $totalValueLabel]),
                    __('contracts.term_payment_breakdown', ['terms' => $paymentBreakdown]),
                    __('contracts.term_payment_method'),
                ],
            ],
            [
                'title' => __('contracts.tax_vat'),
                'items' => [
                    __('contracts.term_vat_1'),
                    __('contracts.term_vat_2'),
                ],
            ],
            [
                'title' => __('contracts.delivery_terms'),
                'items' => [
                    __('contracts.term_delivery_days', ['days' => $deliveryDays]),
                    __('contracts.term_delivery_inspection'),
                    __('contracts.term_delivery_risk'),
                ],
            ],
            [
                'title' => __('contracts.warranty_support'),
                'items' => [
                    __('contracts.term_warranty_period'),
                    __('contracts.term_warranty_scope'),
                ],
            ],
            [
                'title' => __('contracts.confidentiality'),
                'items' => [
                    __('contracts.term_confidentiality_1'),
                    __('contracts.term_confidentiality_2'),
                ],
            ],
            [
                'title' => __('contracts.data_protection'),
                'items' => [
                    __('contracts.term_pdpl_1'),
                    __('contracts.term_pdpl_2'),
                ],
            ],
            [
                'title' => __('contracts.compliance_aml'),
                'items' => [
                    __('contracts.term_aml_1'),
                    __('contracts.term_aml_2'),
                ],
            ],
            [
                'title' => __('contracts.force_majeure'),
                'items' => [
                    __('contracts.term_force_majeure_1'),
                    __('contracts.term_force_majeure_2'),
                ],
            ],
            [
                'title' => __('contracts.termination'),
                'items' => [
                    __('contracts.term_termination_1'),
                    __('contracts.term_termination_2'),
                ],
            ],
            [
                'title' => __('contracts.electronic_signature'),
                'items' => [
                    __('contracts.term_esign_1'),
                    __('contracts.term_esign_2'),
                ],
            ],
            [
                'title' => __('contracts.notices'),
                'items' => [
                    __('contracts.term_notices_1'),
                ],
            ],
            [
                'title' => __('contracts.assignment'),
                'items' => [
                    __('contracts.term_assignment_1'),
                ],
            ],
            [
                'title' => __('contracts.severability_entire'),
                'items' => [
                    __('contracts.term_severability_1'),
                    __('contracts.term_severability_2'),
                ],
            ],
            [
                'title' => __('contracts.governing_law'),
                'items' => [
                    __('contracts.term_governing_law_1'),
                    __('contracts.term_governing_law_2'),
                ],
            ],
            [
                'title' => __('contracts.dispute_resolution'),
                'items' => [
                    __('contracts.term_disputes_negotiation'),
                    __('contracts.term_disputes_jurisdiction'),
                ],
            ],
            [
                'title' => __('contracts.language_clause'),
                'items' => [
                    __('contracts.term_language_1'),
                ],
            ],
        ];
    }

    public function createAmendment(int $contractId, array $data): ContractAmendment
    {
        $contract = Contract::findOrFail($contractId);

        return ContractAmendment::create([
            'contract_id' => $contractId,
            'from_version' => $contract->version,
            'changes' => $data['changes'],
            'reason' => $data['reason'] ?? null,
            'status' => AmendmentStatus::DRAFT,
            'requested_by' => auth()->id(),
        ]);
    }

    public function getAmendments(int $contractId): LengthAwarePaginator
    {
        return ContractAmendment::where('contract_id', $contractId)
            ->with('requestedBy')
            ->latest()
            ->paginate(15);
    }

    public function approveAmendment(int $amendmentId): Contract
    {
        return DB::transaction(function () use ($amendmentId) {
            $amendment = ContractAmendment::findOrFail($amendmentId);
            $contract = $amendment->contract;

            $amendment->update(['status' => AmendmentStatus::APPROVED]);

            $contract->update(array_merge(
                $amendment->changes,
                ['version' => $contract->version + 1]
            ));

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => $contract->version,
                'snapshot' => $contract->fresh()->toArray(),
                'created_by' => auth()->id(),
            ]);

            return $contract->fresh('buyerCompany');
        });
    }
}
