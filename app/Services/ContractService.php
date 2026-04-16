<?php

namespace App\Services;

use App\Enums\AmendmentStatus;
use App\Enums\ContractStatus;
use App\Enums\LegalJurisdiction;
use App\Enums\RfqType;
use App\Events\ContractSigned;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractVersion;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\ContractCreatedNotification;
use App\Notifications\ContractSignedNotification;
use App\Services\Signing\SignatureGradeResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ContractService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SignatureGradeResolver $gradeResolver,
    ) {}

    /**
     * Phase 6 (UAE Compliance Roadmap) — refuse the signature when the
     * achieved grade is weaker than the legal floor for this contract.
     * Returns the resolved {@see SignatureGrade} on success, or a
     * user-facing error string on failure (matches the rest of the
     * sign() return contract).
     */
    private function resolveAndAssertGrade(Contract $contract, array $auditContext): \App\Enums\SignatureGrade|string
    {
        $required = $this->gradeResolver->requiredFor($contract);

        // The achieved grade comes from the auditContext. The
        // controller is responsible for setting it correctly:
        //   - Plain step-up password flow → 'simple'
        //   - UAE Pass callback           → 'advanced'
        //   - TSP-issued envelope         → 'qualified'
        // Default to 'simple' for backwards compatibility — that's
        // what the platform was always producing pre-Phase-6.
        $achievedRaw = (string) ($auditContext['signature_grade'] ?? 'simple');
        $achieved = \App\Enums\SignatureGrade::tryFrom($achievedRaw);
        if (! $achieved) {
            return "Invalid signature grade: {$achievedRaw}";
        }

        if (! $achieved->satisfies($required)) {
            return sprintf(
                'This contract requires a %s but the signature provided is only %s. %s',
                $required->label(),
                $achieved->label(),
                $this->gradeResolver->reasonFor($contract)
            );
        }

        return $achieved;
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
        $contract = Contract::findOrFail($id);

        $contract->update($data);

        return $contract->fresh('buyerCompany');
    }

    public function delete(int $id): bool
    {
        return Contract::findOrFail($id)->delete();
    }

    public function sign(int $id, int $userId, int $companyId, ?string $signature = null, array $auditContext = []): Contract|string
    {
        $contract = Contract::find($id);
        if (! $contract) {
            return 'Contract not found';
        }

        if (! in_array($contract->status, [ContractStatus::DRAFT, ContractStatus::PENDING_SIGNATURES])) {
            return 'Contract is not in a signable state';
        }

        // Phase 6 (UAE Compliance Roadmap) — refuse the signature when
        // it doesn't meet the grade required by the resolver. This is
        // the legal floor under Federal Decree-Law 46/2021: a Simple
        // signature on a government contract is unenforceable.
        //
        // The achieved grade is whatever auditContext['signature_grade']
        // says — the controller stamps it based on the source. UAE Pass
        // → Advanced, TSP-issued → Qualified, plain step-up → Simple.
        $grade = $this->resolveAndAssertGrade($contract, $auditContext);
        if (is_string($grade)) {
            return $grade; // grade-mismatch error message
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

        if (! in_array($companyId, $partyCompanyIds, true)) {
            return 'Your company is not a party to this contract';
        }

        $signatures = $contract->signatures ?? [];
        $alreadySigned = collect($signatures)->where('company_id', $companyId)->isNotEmpty();

        if ($alreadySigned) {
            return 'This party has already signed';
        }

        // UAE Federal Decree-Law 46/2021 (Electronic Transactions Law)
        // Article 18 requires that an electronic signature be uniquely
        // linked to the signatory and reliably identify them. We capture
        // every piece of context the controller can give us — IP, UA,
        // a SHA-256 hash of the contract terms at the moment of signing,
        // and the explicit consent text the user acknowledged — and
        // store it in the signature row so the audit trail can survive
        // any later challenge in court.
        $contractHash = hash('sha256', json_encode([
            'contract_number' => $contract->contract_number,
            'version' => $contract->version,
            'terms' => $contract->terms,
            'amounts' => $contract->amounts,
            'parties' => $contract->parties,
        ], JSON_UNESCAPED_UNICODE));

        $signatures[] = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'signature' => $signature,
            'signed_at' => now()->toIso8601String(),
            'ip_address' => $auditContext['ip_address'] ?? null,
            'user_agent' => $auditContext['user_agent'] ?? null,
            'consent_text' => $auditContext['consent_text'] ?? null,
            'consent_at' => $auditContext['consent_at'] ?? now()->toIso8601String(),
            'contract_hash' => $contractHash,
            'contract_version' => $contract->version,
            // Phase 6 (UAE Compliance Roadmap) — grade + provider trail
            // for the public verify endpoint and any future court audit.
            'signature_grade' => $grade->value,
            'uae_pass_user_id' => $auditContext['uae_pass_user_id'] ?? null,
            'uae_pass_full_name' => $auditContext['uae_pass_full_name'] ?? null,
            'tsp_provider' => $auditContext['tsp_provider'] ?? null,
            'tsp_certificate_id' => $auditContext['tsp_certificate_id'] ?? null,
            'signature_format' => $auditContext['signature_format'] ?? null,
            'signature_payload' => $auditContext['signature_payload'] ?? null,
            'timestamp_token' => $auditContext['timestamp_token'] ?? null,
        ];

        $contract->update([
            'signatures' => $signatures,
            'status' => ContractStatus::PENDING_SIGNATURES,
            // Cache the resolved required grade so future reads + the
            // contract show page don't re-run the resolver per request.
            'signature_grade_required' => $contract->signature_grade_required
                ?? $this->gradeResolver->requiredFor($contract)->value,
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
            ? trim(($signer->first_name ?? '').' '.($signer->last_name ?? ''))
            : 'A party';

        $partyCompanyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if (! empty($partyCompanyIds)) {
            $recipients = User::whereIn('company_id', $partyCompanyIds)->active()->get();
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
            if (! $rfq) {
                throw new \RuntimeException('Bid has no associated RFQ.');
            }

            // For sales-side RFQs the publishing company is the SELLER and
            // the bidder is the BUYER, so we swap the roles before building
            // the contract parties. Every other RFQ type keeps the standard
            // "RFQ author = buyer" mapping.
            $rfqType = $rfq->type instanceof RfqType ? $rfq->type : RfqType::tryFrom((string) $rfq->type);
            $isSalesOffer = $rfqType?->bidderIsBuyer() ?? false;

            $buyerCompanyId = $isSalesOffer ? $bid->company_id : $rfq->company_id;
            $supplierCompanyId = $isSalesOffer ? $rfq->company_id : $bid->company_id;

            // Phase 3.5 (UAE Compliance Roadmap — post-implementation
            // hardening). A company without a valid trade license is not
            // a legal entity capable of binding itself to a contract
            // under Federal Decree-Law 50/2022 Article 5. Refusing to
            // build the contract is the safer default — the alternative
            // is creating an unenforceable contract that exposes the
            // platform to liability if either party later contests it.
            //
            // The check runs AFTER the role swap so the error message
            // points at the correct party. We don't enforce on existing
            // contracts (the idempotency check below) because the
            // license may have been valid at the original sign and
            // expired since — that's a renewal problem, not a creation
            // problem, and is handled by the documents:expire job.
            $this->assertTradeLicensesValid($buyerCompanyId, $supplierCompanyId);

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

            $price = (float) $bid->price;
            $currency = $bid->currency ?? 'AED';
            $deliveryDays = (int) ($bid->delivery_time_days ?? 30);
            $startDate = Carbon::now()->startOfDay();
            $endDate = $startDate->copy()->addDays($deliveryDays);

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
                $subtotal = (float) $bid->subtotal_excl_tax;
                $taxRate = (float) ($bid->tax_rate_snapshot ?? 0);
                $taxAmount = (float) ($bid->tax_amount ?? 0);
                $totalAmount = (float) $bid->total_incl_tax;
            } else {
                $taxRate = TaxRate::resolveFor($rfq->category_id, $rfq->company?->country);
                $subtotal = $price;
                $taxAmount = round($price * $taxRate / 100, 2);
                $totalAmount = round($price + $taxAmount, 2);
            }

            // Approval routing — if the buyer company has set an
            // approval_threshold_aed and the total contract value
            // exceeds it, the contract enters PENDING_INTERNAL_APPROVAL
            // and waits for an internal approver before being released
            // to the supplier for signature. Null threshold = current
            // behaviour (every contract goes straight to signatures).
            $buyerCompany = Company::find($buyerCompanyId);
            $threshold = $buyerCompany?->approval_threshold_aed ? (float) $buyerCompany->approval_threshold_aed : null;
            $needsInternalApproval = $threshold !== null && $totalAmount > $threshold;
            $initialStatus = $needsInternalApproval
                ? ContractStatus::PENDING_INTERNAL_APPROVAL
                : ContractStatus::PENDING_SIGNATURES;

            $contract = Contract::create([
                'title' => $rfq->title,
                'description' => $rfq->description,
                'purchase_request_id' => $rfq->purchase_request_id,
                'buyer_company_id' => $buyerCompanyId,
                'status' => $initialStatus,
                'parties' => [
                    [
                        'company_id' => $buyerCompanyId,
                        'role' => 'buyer',
                        'name' => $isSalesOffer ? $bid->company?->name : $rfq->company?->name,
                    ],
                    [
                        'company_id' => $supplierCompanyId,
                        'role' => 'supplier',
                        'name' => $isSalesOffer ? $rfq->company?->name : $bid->company?->name,
                    ],
                ],
                'amounts' => [
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax' => $taxAmount,
                    'total' => $totalAmount,
                    // Phase 2 — preserve the supplier's declared treatment
                    // and the trade context so the contract show page can
                    // render an accurate Tax Invoice header (TRN, Incoterm,
                    // country of origin, exemption reason).
                    'tax_treatment' => $bid->tax_treatment ?? 'exclusive',
                    'tax_exemption_reason' => $bid->tax_exemption_reason,
                    'incoterm' => $bid->incoterm,
                    'country_of_origin' => $bid->country_of_origin,
                    'hs_code' => $bid->hs_code,
                ],
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'payment_schedule' => $this->buildPaymentScheduleFromBid($bid, $startDate, $endDate, $taxRate, $subtotal),
                'signatures' => [],
                'terms' => json_encode(
                    $this->buildContractTerms($bid, $rfq, $deliveryDays),
                    JSON_UNESCAPED_UNICODE
                ),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'version' => 1,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => 1,
                'snapshot' => $contract->toArray(),
                'created_by' => auth()->id() ?? $bid->provider_id,
            ]);

            // ContractCreatedNotification fan-out is handled by
            // {@see \App\Observers\ContractObserver} so every contract
            // creation path (RFQ accept, Buy-Now, cart checkout) gets
            // the same notification without each entry point having to
            // remember to call it.

            return $contract->load('buyerCompany');
        });
    }

    /**
     * Clone an existing contract into a fresh PENDING_SIGNATURES
     * draft for renewal. Used when a contract is approaching its
     * end date or has just expired and the buyer wants to issue
     * a new one with the same terms, supplier, and milestone
     * structure but new start / end dates.
     *
     * The clone:
     *   - Reuses parties, terms, payment_schedule percentages,
     *     amounts (currency, tax_treatment, etc.) and the
     *     description.
     *   - Resets signatures, escrow_account_id, supplier_documents,
     *     progress_percentage and progress_updates to empty so the
     *     new contract starts clean.
     *   - Recomputes payment_schedule due_dates against the new
     *     contract window so the milestones land in the future.
     *   - Generates a new contract_number and version=1 + records
     *     a fresh ContractVersion v1 snapshot.
     *
     * The renewal flag is stored in the description for traceability
     * (e.g. "Renewed from CTR-ABC123 on 2026-04-09") so a human
     * reader of the new contract can trace it back to the original.
     */
    public function renewContract(int $sourceId, int $extendDays): Contract
    {
        return DB::transaction(function () use ($sourceId, $extendDays) {
            $source = Contract::findOrFail($sourceId);

            $newStart = Carbon::now()->startOfDay();
            $newEnd = $newStart->copy()->addDays(max(1, $extendDays));

            // Recompute payment_schedule due dates against the new
            // contract window. Milestone keys + percentages + tax
            // metadata are preserved verbatim.
            $oldSchedule = $source->payment_schedule ?? [];
            $count = max(1, count($oldSchedule));
            $totalDays = max(1, (int) $newStart->diffInDays($newEnd));
            $newSchedule = [];
            foreach (array_values($oldSchedule) as $i => $row) {
                $offset = $count <= 1 ? 0 : (int) round(($totalDays * $i) / ($count - 1));
                $row['due_date'] = $newStart->copy()->addDays($offset)->toDateString();
                // Reset retention release date if applicable.
                if (! empty($row['is_retention']) && ! empty($row['release_after_days'])) {
                    $row['due_date'] = $newEnd->copy()->addDays((int) $row['release_after_days'])->toDateString();
                }
                $newSchedule[] = $row;
            }

            $renewalNote = "[RENEWED FROM {$source->contract_number} on ".now()->toDateString().']';
            $description = trim(($source->description ?? '')."\n\n".$renewalNote);

            $contract = Contract::create([
                'title' => $source->title,
                'description' => $description,
                'purchase_request_id' => $source->purchase_request_id,
                'buyer_company_id' => $source->buyer_company_id,
                'status' => ContractStatus::PENDING_SIGNATURES,
                'parties' => $source->parties,
                'amounts' => $source->amounts,
                'total_amount' => $source->total_amount,
                'currency' => $source->currency,
                'payment_schedule' => $newSchedule,
                'signatures' => [],
                'terms' => is_string($source->terms) ? $source->terms : json_encode($source->terms, JSON_UNESCAPED_UNICODE),
                'start_date' => $newStart,
                'end_date' => $newEnd,
                'version' => 1,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => 1,
                'snapshot' => $contract->toArray(),
                'created_by' => auth()->id(),
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
            $unitPrice = (float) $product->base_price;
            $subtotal = round($unitPrice * $quantity, 2);
            $currency = $product->currency ?? 'AED';
            $leadTime = max(1, (int) $product->lead_time_days);
            $startDate = Carbon::now()->startOfDay();
            $endDate = $startDate->copy()->addDays($leadTime);

            // Same tax precedence used by createFromBid — keeps Buy-Now and
            // RFQ-driven contracts taxed identically.
            $taxRate = TaxRate::resolveFor($product->category_id, $product->company?->country);
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $contract = Contract::create([
                'title' => $product->name,
                'description' => $product->description,
                'purchase_request_id' => null,
                'buyer_company_id' => $buyerCompanyId,
                'status' => ContractStatus::PENDING_SIGNATURES,
                'parties' => [
                    [
                        'company_id' => $buyerCompanyId,
                        'role' => 'buyer',
                        'name' => null,
                    ],
                    [
                        'company_id' => $supplierCompanyId,
                        'role' => 'supplier',
                        'name' => $product->company?->name,
                    ],
                ],
                'amounts' => [
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax' => $taxAmount,
                    'total' => $totalAmount,
                ],
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'payment_schedule' => $this->buildBuyNowSchedule($subtotal, $currency, $startDate, $endDate, $taxRate),
                'signatures' => [],
                'terms' => json_encode(
                    $this->buildBilingualUaeContractTerms(
                        scopeTitle: __('catalog.term_buy_now_scope', [
                            'qty' => $quantity,
                            'unit' => $product->unit,
                            'product' => $product->name,
                        ]),
                        totalValueLabel: $currency.' '.number_format($totalAmount, 2),
                        paymentBreakdown: __('catalog.term_buy_now_payment'),
                        deliveryDays: $leadTime,
                    ),
                    JSON_UNESCAPED_UNICODE
                ),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'version' => 1,
            ]);

            ContractVersion::create([
                'contract_id' => $contract->id,
                'version' => 1,
                'snapshot' => $contract->toArray(),
                'created_by' => $buyerUserId,
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
        $supplierCompany = Company::find($supplierCompanyId);
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
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->name_snapshot,
                'attributes' => $item->attributes_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'currency' => $item->currency,
                'line_total' => $line,
            ];
        }
        $subtotal = round($subtotal, 2);

        // Lead time = max across all lines (we deliver when the slowest
        // SKU is ready). Defaults to 7 days if a product has no lead time.
        $leadTime = max(7, $items->max(fn ($i) => (int) ($i->product?->lead_time_days ?? 7)));
        $startDate = Carbon::now()->startOfDay();
        $endDate = $startDate->copy()->addDays($leadTime);

        // Use the first product's category for tax lookup — fine for the
        // overwhelming majority of multi-line orders that share a category.
        $primaryCategoryId = $items->first()->product?->category_id;
        $taxRate = TaxRate::resolveFor($primaryCategoryId, $supplierCountry);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $title = $items->count() === 1
            ? $items->first()->name_snapshot
            : sprintf('%s + %d more', $items->first()->name_snapshot, $items->count() - 1);

        $contract = Contract::create([
            'title' => $title,
            'description' => null,
            'purchase_request_id' => null,
            'buyer_company_id' => $buyerCompanyId,
            'status' => ContractStatus::PENDING_SIGNATURES,
            'parties' => [
                ['company_id' => $buyerCompanyId,    'role' => 'buyer',    'name' => null],
                ['company_id' => $supplierCompanyId, 'role' => 'supplier', 'name' => $supplierCompany?->name],
            ],
            'amounts' => [
                'lines' => $lineSnapshots,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $taxAmount,
                'total' => $totalAmount,
            ],
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'payment_schedule' => $this->buildBuyNowSchedule($subtotal, $currency, $startDate, $endDate, $taxRate),
            'signatures' => [],
            'terms' => json_encode(
                $this->buildBilingualUaeContractTerms(
                    scopeTitle: collect($lineSnapshots)
                        ->map(fn ($l) => sprintf('%d × %s', $l['quantity'], $l['name']))
                        ->implode(' • '),
                    totalValueLabel: $currency.' '.number_format($totalAmount, 2),
                    paymentBreakdown: __('catalog.term_buy_now_payment'),
                    deliveryDays: $leadTime,
                ),
                JSON_UNESCAPED_UNICODE
            ),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'version' => 1,
        ]);

        ContractVersion::create([
            'contract_id' => $contract->id,
            'version' => 1,
            'snapshot' => $contract->toArray(),
            'created_by' => $buyerUserId,
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
                'milestone' => 'advance',
                'percentage' => 30,
                'amount' => round($subtotal * 0.30, 2),
                'tax_rate' => $taxRate,
                'tax_amount' => round($subtotal * 0.30 * $taxRate / 100, 2),
                'currency' => $currency,
                'due_date' => $startDate->toDateString(),
                // Phase 3 / Sprint 13 / task 3.10 — escrow release rule.
                // Buy-Now advances always release the moment all parties
                // sign so the supplier sees money immediately.
                'release_condition' => 'on_signature',
            ],
            [
                'milestone' => 'delivery',
                'percentage' => 70,
                'amount' => round($subtotal * 0.70, 2),
                'tax_rate' => $taxRate,
                'tax_amount' => round($subtotal * 0.70 * $taxRate / 100, 2),
                'currency' => $currency,
                'due_date' => $endDate->toDateString(),
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

        // Detect a retention/holdback request in the payment_terms
        // free text. UAE construction & manufacturing contracts
        // routinely retain 5-10% for a warranty period (typically
        // 12 months) before releasing the final tranche. Pattern:
        //   "10% retention", "5% holdback for 12 months", etc.
        $retentionPct = 0;
        $retentionDays = 365; // default: 12-month warranty period
        if (preg_match('/(\d+)\s*%[^,;.]*\b(retention|holdback)\b/iu', $terms, $rm)) {
            $retentionPct = max(0, min(20, (int) $rm[1]));
        }
        if (preg_match('/(\d+)\s*month/iu', $terms, $rd)) {
            $retentionDays = max(30, (int) $rd[1] * 30);
        }

        $percentages = [];
        if (preg_match_all('/(\d+)\s*%/', $terms, $m)) {
            $percentages = array_map('intval', $m[1]);
        }

        if (empty($percentages)) {
            $percentages = [30, 70];
        }

        // If retention is in play, strip the retention percentage
        // out of the active milestone list before normalising — it
        // becomes its own dedicated trailing entry below so the
        // escrow release logic and the contract show view treat it
        // as a separate stage with its own release condition.
        if ($retentionPct > 0) {
            // Remove the LAST occurrence equal to retentionPct (the
            // user wrote "70% delivery, 10% retention" → drop the 10
            // from the active list).
            $idx = array_search($retentionPct, array_reverse($percentages, true), true);
            if ($idx !== false) {
                unset($percentages[$idx]);
                $percentages = array_values($percentages);
            }
        }

        // Force the active schedule to sum to exactly (100 - retention)
        // by absorbing any rounding drift into the last milestone.
        $activeTarget = 100 - $retentionPct;
        $sum = array_sum($percentages);
        if ($sum !== $activeTarget && $sum > 0) {
            $percentages[count($percentages) - 1] += ($activeTarget - $sum);
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
            $key = $milestoneKeys[$i] ?? 'milestone_'.($i + 1);

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
                'advance' => 'on_signature',
                'production' => 'on_inspection_pass',
                'delivery' => 'on_delivery',
                'final' => 'manual',
                default => 'manual',
            };

            $schedule[] = [
                'milestone' => $key,
                'percentage' => $pct,
                'amount' => $amount,
                'tax_rate' => $taxRate,
                'tax_amount' => round($amount * $taxRate / 100, 2),
                'currency' => $currency,
                'due_date' => $dueDate->toDateString(),
                'release_condition' => $releaseCondition,
                'is_retention' => false,
                'release_after_days' => 0,
            ];
        }

        // Trailing retention milestone — held by escrow until the
        // warranty period expires, then released. The release_condition
        // is `retention_period_elapsed` so the SweepEscrowReleases
        // command can pick it up after end_date + release_after_days.
        if ($retentionPct > 0) {
            $retentionAmount = round($price * $retentionPct / 100, 2);
            $schedule[] = [
                'milestone' => 'retention',
                'percentage' => $retentionPct,
                'amount' => $retentionAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => round($retentionAmount * $taxRate / 100, 2),
                'currency' => $currency,
                'due_date' => $endDate->copy()->addDays($retentionDays)->toDateString(),
                'release_condition' => 'retention_period_elapsed',
                'is_retention' => true,
                'release_after_days' => $retentionDays,
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
        $price = number_format((float) $bid->price, 2);
        $currency = $bid->currency ?? 'AED';
        $totalRef = $currency.' '.$price;

        // Phase 3 (UAE Compliance Roadmap) — pull the buyer + supplier
        // companies so the bilingual builder can pick the right
        // jurisdiction (federal / DIFC / ADGM) and the right VAT clause
        // (mainland / designated-zone supply / cross-zone reverse charge).
        $rfq->loadMissing('company');
        $bid->loadMissing('company');
        $buyerCompany = $rfq->company;
        $supplierCompany = $bid->company;

        return $this->buildBilingualUaeContractTerms(
            scopeTitle: (string) $rfq->title,
            totalValueLabel: $totalRef,
            paymentBreakdown: (string) ($bid->payment_terms ?? '—'),
            deliveryDays: $deliveryDays,
            buyerCompany: $buyerCompany,
            supplierCompany: $supplierCompany,
        );
    }

    /**
     * Bilingual companion for buildUaeContractTerms() — returns both the
     * Arabic and English clause sets in a single envelope so a contract
     * persists with both language versions and the PDF download can pick
     * the right one without re-running translations or losing user
     * amendments. New contracts ALWAYS go through this so the bilingual
     * shape is enforced at creation time.
     *
     * Phase 3 — accepts optional buyer/supplier companies so the clause
     * generator can pick the right jurisdiction (federal / DIFC / ADGM)
     * and the right VAT treatment (designated-zone supply / mainland /
     * cross-zone reverse charge). When the parties are not provided,
     * federal-mainland defaults are used so legacy callers (Buy-Now,
     * tests) keep working without changes.
     *
     * @return array{en: array, ar: array}
     */
    public function buildBilingualUaeContractTerms(
        string $scopeTitle,
        string $totalValueLabel,
        string $paymentBreakdown,
        int $deliveryDays,
        ?Company $buyerCompany = null,
        ?Company $supplierCompany = null,
    ): array {
        $jurisdiction = LegalJurisdiction::resolveForPair(
            $buyerCompany?->jurisdiction(),
            $supplierCompany?->jurisdiction()
        );

        $vatCase = $this->resolveVatCase($buyerCompany, $supplierCompany);

        return [
            'en' => $this->withLocale('en', fn () => $this->buildUaeContractTerms(
                $scopeTitle, $totalValueLabel, $paymentBreakdown, $deliveryDays, $jurisdiction, $vatCase
            )),
            'ar' => $this->withLocale('ar', fn () => $this->buildUaeContractTerms(
                $scopeTitle, $totalValueLabel, $paymentBreakdown, $deliveryDays, $jurisdiction, $vatCase
            )),
            // Phase 3 metadata — surface the resolved jurisdiction +
            // VAT case as part of the contract envelope so audit, the
            // PDF, and admin reviews can read it without re-running
            // the resolution logic.
            'jurisdiction' => $jurisdiction->value,
            'vat_case' => $vatCase,
        ];
    }

    /**
     * Determine which VAT clause set applies to a contract under
     * Cabinet Decision 59/2017. Returns one of:
     *
     *   - 'designated_zone_internal' — both parties in Designated Zones
     *     (e.g. DAFZA-DAFZA, JAFZA-KIZAD). Goods supplied between two
     *     Designated Zones are outside the scope of UAE VAT.
     *   - 'reverse_charge' — only the buyer is in a Designated Zone and
     *     the supplier is mainland (or vice versa). Reverse-charge
     *     mechanism applies — the recipient self-accounts.
     *   - 'standard' — everything else. Standard 5% VAT.
     *
     * Returns 'standard' when either company is null so legacy callers
     * keep getting the safe default.
     */
    private function resolveVatCase(?Company $buyer, ?Company $supplier): string
    {
        if (! $buyer || ! $supplier) {
            return 'standard';
        }

        $buyerDz = $buyer->isInDesignatedZone();
        $supplierDz = $supplier->isInDesignatedZone();

        if ($buyerDz && $supplierDz) {
            return 'designated_zone_internal';
        }

        if ($buyerDz xor $supplierDz) {
            return 'reverse_charge';
        }

        return 'standard';
    }

    /**
     * Phase 3.5 (UAE Compliance Roadmap — post-implementation hardening) —
     * refuse to build the contract when either party's trade license is
     * missing, expired, or unverified. See createFromBid() for the
     * legal background. Caller passes the resolved buyer + supplier
     * company ids (already swapped for sales-side RFQs).
     *
     * Throws RuntimeException with a clear, user-facing message that
     * names the offending party. The exception bubbles up to the
     * BidController accept flow, which Laravel converts to a flash
     * error on the bid show page.
     */
    private function assertTradeLicensesValid(int $buyerCompanyId, int $supplierCompanyId): void
    {
        $companies = Company::query()
            ->whereIn('id', [$buyerCompanyId, $supplierCompanyId])
            ->get()
            ->keyBy('id');

        $buyer = $companies->get($buyerCompanyId);
        $supplier = $companies->get($supplierCompanyId);

        $missing = [];
        if ($buyer && ! $buyer->hasValidTradeLicense()) {
            $missing[] = $buyer->name.' (buyer)';
        }
        if ($supplier && ! $supplier->hasValidTradeLicense()) {
            $missing[] = $supplier->name.' (supplier)';
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'Cannot create contract — trade license missing, expired or unverified for: '
                .implode(', ', $missing)
                .'. Renew the trade license document in the company profile before retrying.'
            );
        }
    }

    /**
     * Run a callback with a temporary application locale, restoring the
     * previous locale before returning. Used by buildBilingualUaeContractTerms()
     * to render the same clause set twice — once per language.
     */
    private function withLocale(string $locale, callable $fn)
    {
        $previous = App::getLocale();
        try {
            App::setLocale($locale);

            return $fn();
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * Regenerate the standard clause set for a given contract in the
     * requested locale, using whatever scope / value / delivery data the
     * contract row already carries. This is the legacy-contract fallback
     * for the PDF download when `contract->terms` was baked in a single
     * language at creation time (pre-bilingual migration). It produces a
     * fresh single-locale array of `{title, items}` sections — never
     * bilingual.
     *
     * @return array<int, array{title: string, items: array<int, string>}>
     */
    public function regenerateTermsForLocale(Contract $contract, string $locale): array
    {
        $deliveryDays = $contract->start_date && $contract->end_date
            ? max(1, (int) $contract->start_date->diffInDays($contract->end_date))
            : 30;

        $currency = $contract->currency ?? 'AED';
        $totalLabel = $currency.' '.number_format((float) $contract->total_amount, 2);

        // Best-effort payment breakdown reconstructed from the schedule.
        $breakdown = '—';
        if (! empty($contract->payment_schedule)) {
            $parts = [];
            foreach ((array) $contract->payment_schedule as $row) {
                $pct = $row['percentage'] ?? null;
                $milestone = $row['milestone'] ?? null;
                if ($pct !== null && $milestone) {
                    $parts[] = ((int) $pct).'% '.$milestone;
                }
            }
            if ($parts) {
                $breakdown = implode(' · ', $parts);
            }
        }

        return $this->withLocale($locale, fn () => $this->buildUaeContractTerms(
            $contract->title ?: '—',
            $totalLabel,
            $breakdown,
            $deliveryDays,
        ));
    }

    /**
     * Single source of truth for the UAE-grade clause set. Used by every
     * contract-creation path (bid acceptance, Buy-Now, Cart checkout) so
     * the legal text stays identical regardless of how the contract was
     * spawned. Returned as an array of {title, items} sections — each
     * value is a translation key so the contract show + PDF render in
     * the user's current locale.
     *
     * Phase 3 — accepts an optional jurisdiction + vat case so the
     * VAT, governing-law and dispute-resolution sections can swap
     * between the federal civil-law clauses (default) and the DIFC /
     * ADGM common-law clauses. Mainland-vs-designated-zone VAT logic
     * is also dispatched here.
     */
    private function buildUaeContractTerms(
        string $scopeTitle,
        string $totalValueLabel,
        string $paymentBreakdown,
        int $deliveryDays,
        ?LegalJurisdiction $jurisdiction = null,
        string $vatCase = 'standard',
    ): array {
        $jurisdiction = $jurisdiction ?? LegalJurisdiction::FEDERAL;

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
                'items' => match ($vatCase) {
                    // Cabinet Decision 59/2017 — supplies of goods between
                    // two Designated Zones are outside the scope of UAE VAT.
                    'designated_zone_internal' => [
                        __('contracts.term_vat_designated_zone_1'),
                        __('contracts.term_vat_designated_zone_2'),
                    ],
                    // Cross-zone supply (DZ ↔ mainland) — recipient
                    // self-accounts via reverse charge per Article 48.
                    'reverse_charge' => [
                        __('contracts.term_vat_reverse_charge_1'),
                        __('contracts.term_vat_reverse_charge_2'),
                    ],
                    default => [
                        __('contracts.term_vat_1'),
                        __('contracts.term_vat_2'),
                    ],
                },
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
                'items' => match ($jurisdiction) {
                    // DIFC operates under its own English-style common law
                    // (DIFC Contract Law No. 6 of 2004 + DIFC Law No. 7
                    // of 2005). Disputes go to DIFC Courts.
                    LegalJurisdiction::DIFC => [
                        __('contracts.term_governing_law_difc_1'),
                        __('contracts.term_governing_law_difc_2'),
                    ],
                    // ADGM Application Regulations 2015 incorporate
                    // English common law and equity directly. Disputes
                    // go to ADGM Courts.
                    LegalJurisdiction::ADGM => [
                        __('contracts.term_governing_law_adgm_1'),
                        __('contracts.term_governing_law_adgm_2'),
                    ],
                    default => [
                        __('contracts.term_governing_law_1'),
                        __('contracts.term_governing_law_2'),
                    ],
                },
            ],
            [
                'title' => __('contracts.dispute_resolution'),
                'items' => match ($jurisdiction) {
                    LegalJurisdiction::DIFC => [
                        __('contracts.term_disputes_negotiation'),
                        __('contracts.term_disputes_difc_courts'),
                    ],
                    LegalJurisdiction::ADGM => [
                        __('contracts.term_disputes_negotiation'),
                        __('contracts.term_disputes_adgm_courts'),
                    ],
                    default => [
                        __('contracts.term_disputes_negotiation'),
                        __('contracts.term_disputes_jurisdiction'),
                    ],
                },
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
