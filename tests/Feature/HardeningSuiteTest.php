<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SubmitEInvoiceCreditNoteJob;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Contract;
use App\Models\EInvoiceSubmission;
use App\Models\IcvCertificate;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\ContractService;
use App\Services\EInvoice\EInvoiceDispatcher;
use App\Services\EInvoice\PintAeMapper;
use App\Services\Procurement\IcvScoringService;
use App\Services\Tax\TaxInvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hardening regression suite covering the post-implementation review
 * fixes (Phases 1.5 → 5.5). Each section corresponds to one expert
 * review item from the appendix.
 */
class HardeningSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCompany(string $name, CompanyType $type = CompanyType::BUYER): Company
    {
        return Company::create([
            'name'                => $name,
            'registration_number' => 'REG-' . uniqid(),
            'tax_number'          => 'TRN-100200300400500',
            'type'                => $type,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => strtolower(str_replace(' ', '', $name)) . '@t.test',
            'address'             => '101 Sheikh Zayed Road',
            'city'                => 'Dubai',
            'country'             => 'AE',
        ]);
    }

    private function makeUser(Company $company, UserRole $role = UserRole::BUYER): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'u-' . uniqid() . '@t.test',
            'password'   => 'secret-pass',
            'role'       => $role,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    private function attachValidTradeLicense(Company $company, ?\Carbon\Carbon $expiresAt = null): CompanyDocument
    {
        return CompanyDocument::create([
            'company_id' => $company->id,
            'type'       => DocumentType::TRADE_LICENSE,
            'label'      => 'Trade License',
            'file_path'  => 'test/trade-license.pdf',
            'status'     => CompanyDocument::STATUS_VERIFIED,
            'issued_at'  => now()->subYear(),
            'expires_at' => $expiresAt ?? now()->addYear(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase 2.5 — audit_logs encryption + chain integrity
    // ─────────────────────────────────────────────────────────────────

    public function test_audit_log_ip_address_is_encrypted_at_rest_but_transparent_through_model(): void
    {
        $company = $this->makeCompany('Audit Co');
        $user = $this->makeUser($company);

        $log = AuditLog::create([
            'user_id'       => $user->id,
            'company_id'    => $company->id,
            'action'        => \App\Enums\AuditAction::CREATE,
            'resource_type' => 'TestResource',
            'resource_id'   => 1,
            'before'        => null,
            'after'         => ['email' => 'sensitive@example.com', 'phone' => '+971501234567'],
            'ip_address'    => '203.0.113.42',
            'user_agent'    => 'Mozilla/5.0 (test)',
            'status'        => 'success',
        ]);

        $reloaded = $log->fresh();
        $this->assertSame('203.0.113.42', $reloaded->ip_address);
        $this->assertSame('Mozilla/5.0 (test)', $reloaded->user_agent);
        $this->assertSame('sensitive@example.com', $reloaded->after['email']);

        // Raw column read returns ciphertext
        $rawIp = DB::table('audit_logs')->where('id', $log->id)->value('ip_address');
        $this->assertNotSame('203.0.113.42', $rawIp);
        $this->assertSame('203.0.113.42', Crypt::decryptString($rawIp));
    }

    public function test_audit_chain_remains_consistent_with_encrypted_columns(): void
    {
        $company = $this->makeCompany('Chain Co');
        $user = $this->makeUser($company);

        // Create three rows in sequence
        $rows = [];
        for ($i = 0; $i < 3; $i++) {
            $rows[] = AuditLog::create([
                'user_id'       => $user->id,
                'company_id'    => $company->id,
                'action'        => \App\Enums\AuditAction::CREATE,
                'resource_type' => 'Test',
                'resource_id'   => $i + 1,
                'before'        => null,
                'after'         => ['n' => $i],
                'ip_address'    => '10.0.0.' . ($i + 1),
                'user_agent'    => 'agent-' . $i,
                'status'        => 'success',
            ]);
        }

        // Verify each row's stored hash matches what canonicalize+computeHash
        // produces from its raw (encrypted) bytes — same recipe used by
        // verify-chain command.
        $previousHash = null;
        foreach ($rows as $r) {
            $fresh = (array) DB::table('audit_logs')->where('id', $r->id)->first();
            $expected = AuditLog::computeHash(
                AuditLog::canonicalize($fresh),
                $previousHash
            );
            $this->assertSame($expected, $fresh['hash'], "Row {$r->id} hash mismatch");
            $previousHash = $fresh['hash'];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase 3.5 — Trade license expiry gate
    // ─────────────────────────────────────────────────────────────────

    public function test_contract_creation_blocks_when_supplier_trade_license_expired(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyerCompany    = $this->makeCompany('Gate Buyer', CompanyType::BUYER);
        $supplierCompany = $this->makeCompany('Gate Supplier', CompanyType::SUPPLIER);
        $supplierUser    = $this->makeUser($supplierCompany, UserRole::SUPPLIER);

        // Buyer has a valid license, supplier's is EXPIRED.
        $this->attachValidTradeLicense($buyerCompany);
        CompanyDocument::create([
            'company_id' => $supplierCompany->id,
            'type'       => DocumentType::TRADE_LICENSE,
            'label'      => 'Trade License',
            'file_path'  => 'test/expired.pdf',
            'status'     => CompanyDocument::STATUS_VERIFIED,
            'expires_at' => now()->subDays(5), // expired
        ]);

        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-' . uniqid(),
            'title'      => 'Gate Test',
            'company_id' => $buyerCompany->id,
            'type'       => RfqType::SUPPLIER,
            'status'     => RfqStatus::OPEN,
            'items'      => [['name' => 'Item', 'qty' => 1]],
            'budget'     => 1000,
            'currency'   => 'AED',
        ]);

        $bid = Bid::create([
            'rfq_id'      => $rfq->id,
            'company_id'  => $supplierCompany->id,
            'provider_id' => $supplierUser->id,
            'status'      => BidStatus::SUBMITTED,
            'price'       => 900,
            'currency'    => 'AED',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/trade license/i');
        $service->createFromBid($bid->fresh());
    }

    public function test_contract_creation_succeeds_when_both_trade_licenses_valid(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyerCompany    = $this->makeCompany('OK Buyer', CompanyType::BUYER);
        $supplierCompany = $this->makeCompany('OK Supplier', CompanyType::SUPPLIER);
        $supplierUser    = $this->makeUser($supplierCompany, UserRole::SUPPLIER);

        $this->attachValidTradeLicense($buyerCompany);
        $this->attachValidTradeLicense($supplierCompany);

        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-' . uniqid(),
            'title'      => 'OK Test',
            'company_id' => $buyerCompany->id,
            'type'       => RfqType::SUPPLIER,
            'status'     => RfqStatus::OPEN,
            'items'      => [['name' => 'Item', 'qty' => 1]],
            'budget'     => 1000,
            'currency'   => 'AED',
        ]);

        $bid = Bid::create([
            'rfq_id'      => $rfq->id,
            'company_id'  => $supplierCompany->id,
            'provider_id' => $supplierUser->id,
            'status'      => BidStatus::SUBMITTED,
            'price'       => 900,
            'currency'    => 'AED',
        ]);

        $contract = $service->createFromBid($bid->fresh());
        $this->assertNotNull($contract);
        $this->assertSame((int) $buyerCompany->id, (int) $contract->buyer_company_id);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase 1.5 — Reverse charge marking on tax invoices
    // ─────────────────────────────────────────────────────────────────

    public function test_tax_invoice_inherits_vat_treatment_from_contract_envelope(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('VAT Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('VAT Supplier', CompanyType::SUPPLIER);
        $buyerUser = $this->makeUser($buyer, UserRole::BUYER);

        // Synthesize a contract whose terms envelope already has
        // vat_case = reverse_charge (the Phase 3 ContractService work
        // would set this naturally for cross-zone bids).
        $contract = Contract::create([
            'title'             => 'RC Test',
            'buyer_company_id'  => $buyer->id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 1000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->id, 'role' => 'supplier']],
            'terms'             => json_encode([
                'en'           => [],
                'ar'           => [],
                'jurisdiction' => 'federal',
                'vat_case'     => 'reverse_charge',
            ]),
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id'             => $buyerUser->id,
            'status'               => PaymentStatus::COMPLETED,
            'amount'               => 1000,
            'vat_rate'             => 0, // RC: supplier doesn't charge
            'currency'             => 'AED',
            'milestone'            => 'M1',
            'approved_at'          => now(),
        ]);

        // Issue directly via the service so the test isn't entangled
        // with the Observer flow; the mapper picks the same field
        // either way.
        $invoice = $service->issueFor($payment->fresh());

        $this->assertSame(TaxInvoice::VAT_REVERSE_CHARGE, $invoice->vat_treatment);
    }

    public function test_tax_invoice_defaults_to_standard_when_contract_has_no_vat_case(): void
    {
        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('Std Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Std Supplier', CompanyType::SUPPLIER);
        $buyerUser = $this->makeUser($buyer, UserRole::BUYER);

        $contract = Contract::create([
            'title'             => 'Std Test',
            'buyer_company_id'  => $buyer->id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 1000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->id, 'role' => 'supplier']],
            // No terms — legacy contract.
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id'             => $buyerUser->id,
            'status'               => PaymentStatus::COMPLETED,
            'amount'               => 1000,
            'vat_rate'             => 5,
            'currency'             => 'AED',
            'milestone'            => 'M1',
            'approved_at'          => now(),
        ]);

        $invoice = $service->issueFor($payment->fresh());
        $this->assertSame(TaxInvoice::VAT_STANDARD, $invoice->vat_treatment);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase 5.5 — Credit notes in e-invoice pipeline
    // ─────────────────────────────────────────────────────────────────

    public function test_issuing_credit_note_dispatches_einvoice_credit_note_job(): void
    {
        Bus::fake([SubmitEInvoiceCreditNoteJob::class]);

        $service = $this->app->make(TaxInvoiceService::class);

        $buyer = $this->makeCompany('CN Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('CN Supplier', CompanyType::SUPPLIER);
        $buyerUser = $this->makeUser($buyer, UserRole::BUYER);

        $contract = Contract::create([
            'title'             => 'CN Test',
            'buyer_company_id'  => $buyer->id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 1000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->id, 'role' => 'supplier']],
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id'             => $buyerUser->id,
            'status'               => PaymentStatus::COMPLETED,
            'amount'               => 1000,
            'vat_rate'             => 5,
            'currency'             => 'AED',
            'milestone'            => 'M1',
            'approved_at'          => now(),
        ]);

        $invoice = $service->issueFor($payment->fresh());
        $cn = $service->issueCreditNote($invoice, TaxCreditNote::REASON_REFUND, null, 'Test refund');

        Bus::assertDispatched(SubmitEInvoiceCreditNoteJob::class, function ($job) use ($cn) {
            return $job->taxCreditNoteId === $cn->id;
        });
    }

    public function test_pint_ae_mapper_produces_credit_note_xml_with_billing_reference(): void
    {
        $mapper = $this->app->make(PintAeMapper::class);

        $supplier = $this->makeCompany('CN-XML Supplier', CompanyType::SUPPLIER);
        $buyer    = $this->makeCompany('CN-XML Buyer', CompanyType::BUYER);

        $invoice = TaxInvoice::create([
            'invoice_number'      => 'INV-2026-555000',
            'issue_date'          => now()->toDateString(),
            'supply_date'         => now()->toDateString(),
            'supplier_company_id' => $supplier->id,
            'supplier_trn'        => 'TRN-100200300400500',
            'supplier_name'       => $supplier->name,
            'supplier_address'    => 'Dubai',
            'supplier_country'    => 'AE',
            'buyer_company_id'    => $buyer->id,
            'buyer_trn'           => 'TRN-200300400500600',
            'buyer_name'          => $buyer->name,
            'buyer_address'       => 'Abu Dhabi',
            'buyer_country'       => 'AE',
            'line_items'          => [[
                'description' => 'Test', 'quantity' => 1, 'unit' => 'each',
                'unit_price' => 1000, 'taxable_amount' => 1000,
                'tax_rate' => 5, 'tax_amount' => 50, 'line_total' => 1050,
            ]],
            'subtotal_excl_tax'   => 1000,
            'total_tax'           => 50,
            'total_inclusive'     => 1050,
            'currency'            => 'AED',
            'status'              => TaxInvoice::STATUS_ISSUED,
            'issued_at'           => now(),
        ]);

        $cn = TaxCreditNote::create([
            'credit_note_number'  => 'CN-2026-555000',
            'original_invoice_id' => $invoice->id,
            'issue_date'          => now()->toDateString(),
            'reason'              => TaxCreditNote::REASON_REFUND,
            'line_items'          => $invoice->line_items,
            'subtotal_excl_tax'   => 1000,
            'total_tax'           => 50,
            'total_inclusive'     => 1050,
            'currency'            => 'AED',
            'issued_at'           => now(),
        ]);

        $xml = $mapper->toCreditNoteUbl($cn->fresh(['originalInvoice']));

        // Root element + type code distinguish credit notes from invoices
        $this->assertStringContainsString('<CreditNote', $xml);
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode', $xml);
        $this->assertStringContainsString('>381<', $xml);

        // BillingReference back to the original invoice
        $this->assertStringContainsString('<cac:BillingReference>', $xml);
        $this->assertStringContainsString('INV-2026-555000', $xml);

        // Credit note number is the document ID
        $this->assertStringContainsString('CN-2026-555000', $xml);

        // Both parties carry their TRN in the EndpointID (PEPPOL PID)
        $this->assertStringContainsString('schemeID="0235"', $xml);
        $this->assertStringContainsString('TRN-100200300400500', $xml);
        $this->assertStringContainsString('TRN-200300400500600', $xml);
    }

    public function test_dispatcher_creates_credit_note_submission_when_enabled(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.default_provider', 'mock');

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);

        $supplier = $this->makeCompany('Disp CN Supplier', CompanyType::SUPPLIER);
        $buyer    = $this->makeCompany('Disp CN Buyer', CompanyType::BUYER);

        $invoice = TaxInvoice::create([
            'invoice_number'      => 'INV-2026-666000',
            'issue_date'          => now()->toDateString(),
            'supply_date'         => now()->toDateString(),
            'supplier_company_id' => $supplier->id,
            'supplier_trn'        => 'TRN-1', 'supplier_name' => $supplier->name,
            'supplier_address' => 'Dubai', 'supplier_country' => 'AE',
            'buyer_company_id'    => $buyer->id,
            'buyer_trn'           => 'TRN-2', 'buyer_name' => $buyer->name,
            'buyer_address' => 'AUH', 'buyer_country' => 'AE',
            'line_items'          => [[
                'description' => 'X', 'quantity' => 1, 'unit' => 'each',
                'unit_price' => 100, 'taxable_amount' => 100,
                'tax_rate' => 5, 'tax_amount' => 5, 'line_total' => 105,
            ]],
            'subtotal_excl_tax'   => 100, 'total_tax' => 5, 'total_inclusive' => 105,
            'currency'            => 'AED', 'status' => TaxInvoice::STATUS_ISSUED,
            'issued_at'           => now(),
        ]);

        $cn = TaxCreditNote::create([
            'credit_note_number'  => 'CN-2026-666000',
            'original_invoice_id' => $invoice->id,
            'issue_date'          => now()->toDateString(),
            'reason'              => TaxCreditNote::REASON_REFUND,
            'line_items'          => $invoice->line_items,
            'subtotal_excl_tax'   => 100,
            'total_tax'           => 5,
            'total_inclusive'     => 105,
            'currency'            => 'AED',
            'issued_at'           => now(),
        ]);

        $submission = $dispatcher->dispatchForCreditNote($cn->fresh(['originalInvoice']));

        $this->assertNotNull($submission);
        $this->assertSame(EInvoiceSubmission::DOC_CREDIT_NOTE, $submission->document_type);
        $this->assertSame((int) $cn->id, (int) $submission->tax_credit_note_id);
        $this->assertSame(EInvoiceSubmission::STATUS_ACCEPTED, $submission->status);
        $this->assertNotEmpty($submission->payload_xml);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase 5.5 — PEPPOL Participant Identifier
    // ─────────────────────────────────────────────────────────────────

    public function test_company_peppol_participant_id_is_derived_from_trn(): void
    {
        $company = $this->makeCompany('Peppol Co');
        $this->assertSame('0235:TRN-100200300400500', $company->peppolParticipantId());
    }

    public function test_pint_ae_mapper_emits_endpoint_id_with_uae_scheme(): void
    {
        $mapper = $this->app->make(PintAeMapper::class);

        $supplier = $this->makeCompany('Endpoint Supplier', CompanyType::SUPPLIER);
        $buyer    = $this->makeCompany('Endpoint Buyer', CompanyType::BUYER);

        $invoice = TaxInvoice::create([
            'invoice_number'      => 'INV-2026-777000',
            'issue_date'          => now()->toDateString(),
            'supply_date'         => now()->toDateString(),
            'supplier_company_id' => $supplier->id,
            'supplier_trn'        => 'TRN-AAAA1111',
            'supplier_name'       => $supplier->name,
            'supplier_address'    => 'Dubai', 'supplier_country' => 'AE',
            'buyer_company_id'    => $buyer->id,
            'buyer_trn'           => 'TRN-BBBB2222',
            'buyer_name'          => $buyer->name,
            'buyer_address'       => 'AUH', 'buyer_country' => 'AE',
            'line_items'          => [[
                'description' => 'x', 'quantity' => 1, 'unit' => 'each',
                'unit_price' => 100, 'taxable_amount' => 100,
                'tax_rate' => 5, 'tax_amount' => 5, 'line_total' => 105,
            ]],
            'subtotal_excl_tax'   => 100, 'total_tax' => 5, 'total_inclusive' => 105,
            'currency'            => 'AED', 'status' => TaxInvoice::STATUS_ISSUED,
            'issued_at'           => now(),
        ]);

        $xml = $mapper->toUbl($invoice);

        // EndpointID with the 0235 scheme appears for both parties
        $this->assertMatchesRegularExpression(
            '/<cbc:EndpointID[^>]*schemeID="0235"[^>]*>TRN-AAAA1111<\/cbc:EndpointID>/',
            $xml
        );
        $this->assertMatchesRegularExpression(
            '/<cbc:EndpointID[^>]*schemeID="0235"[^>]*>TRN-BBBB2222<\/cbc:EndpointID>/',
            $xml
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phase 4.5 — ICV issuer-specific scoring
    // ─────────────────────────────────────────────────────────────────

    public function test_icv_scoring_filters_by_required_issuers(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer    = $this->makeCompany('ICV Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Multi-issuer Supplier', CompanyType::SUPPLIER);

        // Supplier has TWO certs: high MoIAT score, low ADNOC score
        IcvCertificate::create([
            'company_id'         => $supplier->id,
            'issuer'             => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'M-1',
            'score'              => 90,
            'issued_date'        => now()->subMonths(2),
            'expires_date'       => now()->addMonths(10),
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);
        IcvCertificate::create([
            'company_id'         => $supplier->id,
            'issuer'             => IcvCertificate::ISSUER_ADNOC,
            'certificate_number' => 'A-1',
            'score'              => 25,
            'issued_date'        => now()->subMonths(2),
            'expires_date'       => now()->addMonths(10),
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);

        // ADNOC-only RFQ — supplier should only score 25, not 90
        $rfq = Rfq::create([
            'rfq_number'           => 'RFQ-' . uniqid(),
            'title'                => 'ADNOC tender',
            'company_id'           => $buyer->id,
            'type'                 => RfqType::SUPPLIER,
            'status'               => RfqStatus::OPEN,
            'items'                => [['name' => 'X', 'qty' => 1]],
            'budget'               => 1000,
            'currency'             => 'AED',
            'icv_weight_percentage'=> 30,
            'icv_required_issuers' => ['adnoc'],
        ]);

        $bid = Bid::create([
            'rfq_id'      => $rfq->id,
            'company_id'  => $supplier->id,
            'provider_id' => $this->makeUser($supplier, UserRole::SUPPLIER)->id,
            'status'      => BidStatus::SUBMITTED,
            'price'       => 1000,
            'currency'    => 'AED',
        ]);

        $bids = $rfq->bids()->with('company')->get();
        $score = $service->scoreBid($bid->fresh(), $bids, $rfq);

        // ICV score should be 25 (ADNOC only), NOT 90 (MoIAT)
        $this->assertEquals(25.0, $score['icv_score']);
    }

    public function test_icv_scoring_falls_back_to_best_when_no_issuer_filter(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer    = $this->makeCompany('Open Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Open Supplier', CompanyType::SUPPLIER);

        IcvCertificate::create([
            'company_id'         => $supplier->id,
            'issuer'             => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'M-1',
            'score'              => 80,
            'issued_date'        => now()->subMonths(2),
            'expires_date'       => now()->addMonths(10),
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);

        // Open RFQ — no issuer restriction
        $rfq = Rfq::create([
            'rfq_number'           => 'RFQ-' . uniqid(),
            'title'                => 'Open tender',
            'company_id'           => $buyer->id,
            'type'                 => RfqType::SUPPLIER,
            'status'               => RfqStatus::OPEN,
            'items'                => [['name' => 'X', 'qty' => 1]],
            'budget'               => 1000,
            'currency'             => 'AED',
            'icv_weight_percentage'=> 30,
            'icv_required_issuers' => null,
        ]);

        $bid = Bid::create([
            'rfq_id'      => $rfq->id,
            'company_id'  => $supplier->id,
            'provider_id' => $this->makeUser($supplier, UserRole::SUPPLIER)->id,
            'status'      => BidStatus::SUBMITTED,
            'price'       => 1000,
            'currency'    => 'AED',
        ]);

        $bids = $rfq->bids()->with('company')->get();
        $score = $service->scoreBid($bid->fresh(), $bids, $rfq);

        $this->assertEquals(80.0, $score['icv_score']);
    }

    public function test_icv_scoring_returns_zero_when_supplier_has_no_matching_issuer(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer    = $this->makeCompany('Picky Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('MoIAT-only Supplier', CompanyType::SUPPLIER);

        IcvCertificate::create([
            'company_id'         => $supplier->id,
            'issuer'             => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'M-only',
            'score'              => 95,
            'issued_date'        => now()->subMonths(1),
            'expires_date'       => now()->addMonths(11),
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);

        // Mubadala-only RFQ — supplier has no Mubadala cert
        $rfq = Rfq::create([
            'rfq_number'           => 'RFQ-' . uniqid(),
            'title'                => 'Mubadala tender',
            'company_id'           => $buyer->id,
            'type'                 => RfqType::SUPPLIER,
            'status'               => RfqStatus::OPEN,
            'items'                => [['name' => 'X', 'qty' => 1]],
            'budget'               => 1000,
            'currency'             => 'AED',
            'icv_weight_percentage'=> 30,
            'icv_required_issuers' => ['mubadala'],
        ]);

        $bid = Bid::create([
            'rfq_id'      => $rfq->id,
            'company_id'  => $supplier->id,
            'provider_id' => $this->makeUser($supplier, UserRole::SUPPLIER)->id,
            'status'      => BidStatus::SUBMITTED,
            'price'       => 1000,
            'currency'    => 'AED',
        ]);

        $bids = $rfq->bids()->with('company')->get();
        $score = $service->scoreBid($bid->fresh(), $bids, $rfq);

        $this->assertEquals(0.0, $score['icv_score']);
    }
}
