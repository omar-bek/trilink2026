<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SubmitEInvoiceJob;
use App\Models\Company;
use App\Models\Contract;
use App\Models\EInvoiceSubmission;
use App\Models\Payment;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\EInvoice\EInvoiceDispatcher;
use App\Services\EInvoice\PintAeMapper;
use App\Services\Tax\TaxInvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase 5 (UAE Compliance Roadmap) — e-Invoicing skeleton regression
 * suite. Verifies the four behaviours that justify the change:
 *
 *   1. PintAeMapper produces well-formed UBL 2.1 PINT-AE XML with
 *      every FTA-required header element present.
 *   2. EInvoiceDispatcher creates a submission row, hands it to the
 *      configured provider, and respects the master switch.
 *   3. The TaxInvoiceService → SubmitEInvoiceJob handoff fires when
 *      a tax invoice is issued (config-gated).
 *   4. Webhook controller validates the HMAC signature + 503s when
 *      the secret is unset.
 */
class EInvoicePhase5Test extends TestCase
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
            'tax_number'          => 'TRN-' . random_int(100000, 999999),
            'type'                => $type,
            'status'              => CompanyStatus::ACTIVE,
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

    private function makeTaxInvoice(): TaxInvoice
    {
        $supplier = $this->makeCompany('Phase5 Supplier', CompanyType::SUPPLIER);
        $buyer    = $this->makeCompany('Phase5 Buyer', CompanyType::BUYER);

        return TaxInvoice::create([
            'invoice_number'      => 'INV-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date'          => now()->toDateString(),
            'supply_date'         => now()->toDateString(),
            'supplier_company_id' => $supplier->id,
            'supplier_trn'        => 'TRN-100200300400500',
            'supplier_name'       => $supplier->name,
            'supplier_address'    => '101 Sheikh Zayed Road, Dubai',
            'supplier_country'    => 'AE',
            'buyer_company_id'    => $buyer->id,
            'buyer_trn'           => 'TRN-200300400500600',
            'buyer_name'          => $buyer->name,
            'buyer_address'       => '202 Hamdan St, Abu Dhabi',
            'buyer_country'       => 'AE',
            'line_items'          => [[
                'description'    => 'Project services — milestone 1',
                'quantity'       => 1,
                'unit'           => 'lump sum',
                'unit_price'     => 10000,
                'taxable_amount' => 10000,
                'tax_rate'       => 5,
                'tax_amount'     => 500,
                'line_total'     => 10500,
            ]],
            'subtotal_excl_tax'   => 10000,
            'total_tax'           => 500,
            'total_inclusive'     => 10500,
            'currency'            => 'AED',
            'status'              => TaxInvoice::STATUS_ISSUED,
            'issued_at'           => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. PintAeMapper
    // ─────────────────────────────────────────────────────────────────

    public function test_mapper_produces_wellformed_xml_with_pint_ae_headers(): void
    {
        $mapper  = $this->app->make(PintAeMapper::class);
        $invoice = $this->makeTaxInvoice();

        $xml = $mapper->toUbl($invoice);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('<?xml', $xml);

        // PINT-AE customisation header — the FTA validator looks for this
        $this->assertStringContainsString('urn:peppol:pint:billing-1@ae-1', $xml);

        // Invoice metadata
        $this->assertStringContainsString($invoice->invoice_number, $xml);
        $this->assertStringContainsString('AED', $xml);
        $this->assertStringContainsString('388', $xml); // standard tax invoice type code

        // Both parties + their TRNs
        $this->assertStringContainsString('TRN-100200300400500', $xml);
        $this->assertStringContainsString('TRN-200300400500600', $xml);

        // Tax category S = standard 5% VAT. Use a regex because
        // DOMDocument::createElementNS repeats the xmlns: prefix on
        // every element, so the literal substring "<cbc:ID>S</cbc:ID>"
        // doesn't appear — the actual rendering is `<cbc:ID xmlns:cbc="...">S</cbc:ID>`.
        $this->assertMatchesRegularExpression('/<cbc:ID[^>]*>S<\/cbc:ID>/', $xml);

        // Validate it actually parses + the structural shape via XPath.
        // XPath proves the namespaced elements really do live in the
        // CBC namespace, regardless of how the prefix is repeated in
        // the source.
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $this->assertSame('Invoice', $doc->documentElement->localName);

        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        // Both PartyTaxScheme entries (supplier + buyer) carry an "S" tax category id.
        $sNodes = $xp->query("//cac:TaxCategory/cbc:ID[text()='S']");
        $this->assertNotFalse($sNodes);
        $this->assertGreaterThan(0, $sNodes->length);
    }

    public function test_mapper_includes_total_amounts(): void
    {
        $mapper  = $this->app->make(PintAeMapper::class);
        $invoice = $this->makeTaxInvoice();

        $xml = $mapper->toUbl($invoice);

        $this->assertStringContainsString('LineExtensionAmount', $xml);
        $this->assertStringContainsString('TaxInclusiveAmount', $xml);
        $this->assertStringContainsString('PayableAmount', $xml);
        $this->assertStringContainsString('10000.00', $xml); // subtotal
        $this->assertStringContainsString('500.00', $xml);   // tax
        $this->assertStringContainsString('10500.00', $xml); // total
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Dispatcher
    // ─────────────────────────────────────────────────────────────────

    public function test_dispatcher_returns_null_when_einvoice_disabled(): void
    {
        config()->set('einvoice.enabled', false);

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $invoice    = $this->makeTaxInvoice();

        $result = $dispatcher->dispatchFor($invoice);

        $this->assertNull($result);
        $this->assertSame(0, EInvoiceSubmission::where('tax_invoice_id', $invoice->id)->count());
    }

    public function test_dispatcher_creates_submission_and_marks_accepted_via_mock_provider(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.default_provider', 'mock');

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $invoice    = $this->makeTaxInvoice();

        $submission = $dispatcher->dispatchFor($invoice);

        $this->assertNotNull($submission);
        $this->assertSame(EInvoiceSubmission::STATUS_ACCEPTED, $submission->status);
        $this->assertSame('mock', $submission->asp_provider);
        $this->assertNotEmpty($submission->payload_xml);
        $this->assertNotEmpty($submission->payload_sha256);
        $this->assertNotEmpty($submission->fta_clearance_id);
        $this->assertStringStartsWith('FTA-MOCK-', $submission->fta_clearance_id);
    }

    public function test_dispatcher_throws_on_unknown_provider(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.default_provider', 'made_up_provider');

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $invoice    = $this->makeTaxInvoice();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown e-invoice provider/');
        $dispatcher->dispatchFor($invoice);
    }

    public function test_avalara_provider_marks_failed_when_unconfigured(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.default_provider', 'avalara');
        config()->set('einvoice.providers.avalara.enabled', false);
        config()->set('einvoice.providers.avalara.api_key', null);

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $invoice    = $this->makeTaxInvoice();

        $submission = $dispatcher->dispatchFor($invoice);

        $this->assertSame(EInvoiceSubmission::STATUS_FAILED, $submission->status);
        $this->assertStringContainsString('not configured', $submission->error_message);
    }

    public function test_dispatcher_retry_increments_counter_and_resubmits(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.default_provider', 'mock');

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $invoice    = $this->makeTaxInvoice();

        $submission = $dispatcher->dispatchFor($invoice);
        // Force into "failed" so retry is allowed
        $submission->update(['status' => EInvoiceSubmission::STATUS_FAILED]);

        $retried = $dispatcher->retry($submission->fresh());

        $this->assertSame(1, $retried->retries);
        $this->assertSame(EInvoiceSubmission::STATUS_ACCEPTED, $retried->status);
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. TaxInvoiceService → SubmitEInvoiceJob handoff
    // ─────────────────────────────────────────────────────────────────

    public function test_issuing_a_tax_invoice_dispatches_einvoice_job(): void
    {
        Bus::fake([SubmitEInvoiceJob::class]);

        $supplier = $this->makeCompany('Auto Supplier', CompanyType::SUPPLIER);
        $buyer    = $this->makeCompany('Auto Buyer', CompanyType::BUYER);
        $buyerUser = $this->makeUser($buyer, UserRole::BUYER);

        $contract = Contract::create([
            'title'             => 'Auto Contract',
            'buyer_company_id'  => $buyer->id,
            'status'            => \App\Enums\ContractStatus::ACTIVE,
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
            'milestone'            => 'Milestone 1',
            'approved_at'          => now(),
        ]);

        $service = $this->app->make(TaxInvoiceService::class);
        $service->issueFor($payment->fresh());

        Bus::assertDispatched(SubmitEInvoiceJob::class);
    }

    public function test_submit_einvoice_job_short_circuits_when_disabled(): void
    {
        config()->set('einvoice.enabled', false);

        $invoice = $this->makeTaxInvoice();
        $job = new SubmitEInvoiceJob($invoice->id);

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $job->handle($dispatcher);

        // No submission row should have been created
        $this->assertSame(0, EInvoiceSubmission::where('tax_invoice_id', $invoice->id)->count());
    }

    public function test_submit_einvoice_job_creates_submission_when_enabled(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.default_provider', 'mock');

        $invoice = $this->makeTaxInvoice();
        $job = new SubmitEInvoiceJob($invoice->id);

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $job->handle($dispatcher);

        $this->assertSame(1, EInvoiceSubmission::where('tax_invoice_id', $invoice->id)->count());
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. Webhook
    // ─────────────────────────────────────────────────────────────────

    public function test_webhook_rejects_when_secret_unset(): void
    {
        config()->set('einvoice.webhook_secret', null);

        $this->postJson('/api/webhooks/e-invoice/mock', ['submission_id' => 'foo'])
            ->assertStatus(503);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('einvoice.webhook_secret', 'shared-secret');

        $this->postJson(
            '/api/webhooks/e-invoice/mock',
            ['submission_id' => 'foo'],
            ['X-EInvoice-Signature' => 'wrong-signature']
        )->assertStatus(400);
    }

    public function test_webhook_acks_valid_signature_and_updates_submission(): void
    {
        config()->set('einvoice.enabled', true);
        config()->set('einvoice.webhook_secret', 'shared-secret');
        config()->set('einvoice.default_provider', 'mock');

        $dispatcher = $this->app->make(EInvoiceDispatcher::class);
        $invoice    = $this->makeTaxInvoice();
        $submission = $dispatcher->dispatchFor($invoice);

        // The mock leaves the row already in "accepted"; force it to
        // submitted so the webhook has something to do.
        $submission->update([
            'status' => EInvoiceSubmission::STATUS_SUBMITTED,
            'asp_submission_id' => 'WH-TEST-001',
            'fta_clearance_id'  => null,
        ]);

        $payload = [
            'submission_id'    => 'WH-TEST-001',
            'status'           => 'accepted',
            'clearance_id'     => 'FTA-CLEAR-99',
            'acknowledgment_id'=> 'WH-ACK-99',
        ];
        $body = json_encode($payload);
        $sig  = hash_hmac('sha256', $body, 'shared-secret');

        $this->call(
            method: 'POST',
            uri: '/api/webhooks/e-invoice/mock',
            server: [
                'CONTENT_TYPE'             => 'application/json',
                'HTTP_X_EINVOICE_SIGNATURE' => $sig,
            ],
            content: $body,
        )->assertOk();

        $this->assertSame(
            EInvoiceSubmission::STATUS_ACCEPTED,
            $submission->fresh()->status
        );
        $this->assertSame('FTA-CLEAR-99', $submission->fresh()->fta_clearance_id);
    }

    // ─────────────────────────────────────────────────────────────────
    //  5. Admin queue route
    // ─────────────────────────────────────────────────────────────────

    public function test_admin_queue_renders(): void
    {
        $admin = $this->makeUser($this->makeCompany('Admin Co'), UserRole::ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.e-invoice.index'))
            ->assertOk()
            ->assertSee('e-Invoice');
    }
}
