<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the security hardening pass that closed the
 * IDOR holes, the webhook signature gap, the bid-accept race window
 * and the escrow webhook idempotency drift.
 *
 * Each test asserts a CONCRETE attack surface: a malicious
 * authenticated user from Company A trying to read or mutate data
 * that belongs to Company B. The fix should respond with 404 (not
 * 403) so the attacker cannot enumerate ids.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Test fixtures
    // ─────────────────────────────────────────────────────────────────

    private function makeBuyerUser(string $companyName = 'Buyer Co'): User
    {
        $company = Company::create([
            'name'                => $companyName,
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => CompanyType::BUYER,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => strtolower(str_replace(' ', '', $companyName)) . '@t.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);

        return User::create([
            'first_name' => 'Buyer',
            'last_name'  => 'User',
            'email'      => 'buyer-' . uniqid() . '@t.test',
            'password'   => 'secret-pass',
            'role'       => UserRole::BUYER,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    private function makeSupplierUser(string $companyName = 'Supplier Co'): User
    {
        $company = Company::create([
            'name'                => $companyName,
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => CompanyType::SUPPLIER,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => strtolower(str_replace(' ', '', $companyName)) . '@t.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);

        return User::create([
            'first_name' => 'Supplier',
            'last_name'  => 'User',
            'email'      => 'sup-' . uniqid() . '@t.test',
            'password'   => 'secret-pass',
            'role'       => UserRole::SUPPLIER,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Payment IDOR — show page
    // ─────────────────────────────────────────────────────────────────

    public function test_unrelated_buyer_cannot_view_anothers_payment(): void
    {
        $alice = $this->makeBuyerUser('Alice Buyer');
        $bob   = $this->makeBuyerUser('Bob Buyer');
        $supplier = $this->makeSupplierUser('Bob Supplier');

        // Alice owns the contract and the payment.
        $contract = Contract::create([
            'title'             => 'Alice Contract',
            'buyer_company_id'  => $alice->company_id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 1000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->company_id, 'role' => 'supplier']],
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $alice->company_id,
            'recipient_company_id' => $supplier->company_id,
            'buyer_id'             => $alice->id,
            'status'               => PaymentStatus::PENDING_APPROVAL,
            'amount'               => 500,
            'currency'             => 'AED',
        ]);

        // Bob is unrelated. Should get a 404 (not 403, not 200).
        $this->actingAs($bob)
            ->get(route('dashboard.payments.show', ['id' => $payment->id]))
            ->assertNotFound();
    }

    public function test_recipient_supplier_can_view_their_payment(): void
    {
        $alice = $this->makeBuyerUser('Alice Buyer');
        $supplier = $this->makeSupplierUser('Alice Supplier');
        // Suppliers do not get payment.view by default in the seeded role
        // matrix. The per-user `permissions` allowlist takes precedence
        // over role defaults (User::resolvePermissionKeys), so seeding it
        // with payment.view is the documented way to grant a one-off
        // capability. Emulate the company manager ticking the box.
        $supplier->update(['permissions' => ['payment.view']]);

        $contract = Contract::create([
            'title'             => 'Alice Contract',
            'buyer_company_id'  => $alice->company_id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 1000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->company_id, 'role' => 'supplier']],
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $alice->company_id,
            'recipient_company_id' => $supplier->company_id,
            'buyer_id'             => $alice->id,
            'status'               => PaymentStatus::PENDING_APPROVAL,
            'amount'               => 500,
            'currency'             => 'AED',
        ]);

        // The supplier is the recipient — they MUST be able to read.
        $this->actingAs($supplier)
            ->get(route('dashboard.payments.show', ['id' => $payment->id]))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Contract IDOR — show page
    // ─────────────────────────────────────────────────────────────────

    public function test_unrelated_buyer_cannot_view_anothers_contract(): void
    {
        $alice = $this->makeBuyerUser('Alice Buyer');
        $bob   = $this->makeBuyerUser('Bob Buyer');
        $supplier = $this->makeSupplierUser('Alice Supplier');

        $contract = Contract::create([
            'title'             => 'Confidential Contract',
            'buyer_company_id'  => $alice->company_id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 99000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->company_id, 'role' => 'supplier']],
        ]);

        $this->actingAs($bob)
            ->get(route('dashboard.contracts.show', ['id' => $contract->id]))
            ->assertNotFound();
    }

    public function test_party_supplier_can_view_their_contract(): void
    {
        $alice    = $this->makeBuyerUser('Alice Buyer');
        $supplier = $this->makeSupplierUser('Their Supplier');

        $contract = Contract::create([
            'title'             => 'Joint Contract',
            'buyer_company_id'  => $alice->company_id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 99000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->company_id, 'role' => 'supplier']],
        ]);

        $this->actingAs($supplier)
            ->get(route('dashboard.contracts.show', ['id' => $contract->id]))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Shipment IDOR — show page
    // ─────────────────────────────────────────────────────────────────

    public function test_unrelated_user_cannot_view_anothers_shipment(): void
    {
        $alice    = $this->makeBuyerUser('Alice Buyer');
        $bob      = $this->makeBuyerUser('Bob Buyer');
        $supplier = $this->makeSupplierUser('Alice Supplier');

        $contract = Contract::create([
            'title'             => 'Contract',
            'buyer_company_id'  => $alice->company_id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 1000,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->company_id, 'role' => 'supplier']],
        ]);

        $shipment = Shipment::create([
            'contract_id'       => $contract->id,
            'company_id'        => $alice->company_id,
            'status'            => ShipmentStatus::IN_TRANSIT,
            'origin'            => ['city' => 'Dubai'],
            'destination'       => ['city' => 'Riyadh'],
        ]);

        $this->actingAs($bob)
            ->get(route('dashboard.shipments.show', ['id' => $shipment->id]))
            ->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Bid race condition — accept twice
    // ─────────────────────────────────────────────────────────────────

    public function test_double_bid_accept_yields_409_on_second_call(): void
    {
        $buyer    = $this->makeBuyerUser('Race Buyer');
        $supplier = $this->makeSupplierUser('Race Supplier');

        // Grant the buyer the bid.accept permission via the Spatie role
        // that the seeder hands out — buyers already have it by default
        // (the seeder seeds the standard buyer role with bid.accept).
        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-RACE',
            'title'      => 'Race RFQ',
            'company_id' => $buyer->company_id,
            'type'       => RfqType::SUPPLIER,
            'status'     => RfqStatus::OPEN,
            'items'      => [['name' => 'Widget', 'qty' => 1]],
            'budget'     => 1000,
            'currency'   => 'AED',
        ]);

        $bid = Bid::create([
            'rfq_id'      => $rfq->id,
            'company_id'  => $supplier->company_id,
            'provider_id' => $supplier->id,
            'status'      => BidStatus::SUBMITTED,
            'price'       => 900,
            'currency'    => 'AED',
        ]);

        // First acceptance — should succeed (303/302 redirect to contract).
        $first = $this->actingAs($buyer)
            ->post(route('dashboard.bids.accept', ['id' => $bid->id]));
        $first->assertRedirect();

        // Bid is now ACCEPTED. A second accept on the SAME bid should
        // hit the in-transaction status check and abort with 409.
        $second = $this->actingAs($buyer)
            ->post(route('dashboard.bids.accept', ['id' => $bid->id]));
        $second->assertStatus(409);

        // Only ONE contract should exist for this RFQ.
        $contracts = Contract::query()
            ->where('buyer_company_id', $buyer->company_id)
            ->get();
        $this->assertCount(1, $contracts);
    }

    // ─────────────────────────────────────────────────────────────────
    //  PayPal webhook — signature + replay protection
    // ─────────────────────────────────────────────────────────────────

    public function test_paypal_webhook_rejects_request_when_secret_not_configured(): void
    {
        config()->set('services.paypal.webhook_secret', null);

        $this->postJson('/api/webhooks/paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'])
            ->assertStatus(503);
    }

    public function test_paypal_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.paypal.webhook_secret', 'test-secret');

        $this->postJson(
            '/api/webhooks/paypal',
            ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'],
            ['Paypal-Transmission-Sig' => 'wrong-signature'],
        )->assertStatus(400);
    }

    public function test_paypal_webhook_accepts_valid_signature_and_records_event(): void
    {
        config()->set('services.paypal.webhook_secret', 'test-secret');

        $payload = ['id' => 'EVT-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];
        $body    = json_encode($payload);
        $sig     = hash_hmac('sha256', $body, 'test-secret');

        // Use server() variant so the raw body matches what PayPal sends.
        $this->call(
            method: 'POST',
            uri: '/api/webhooks/paypal',
            server: [
                'CONTENT_TYPE'             => 'application/json',
                'HTTP_PAYPAL_TRANSMISSION_SIG' => $sig,
            ],
            content: $body,
        )->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'paypal',
            'event_id' => 'EVT-1',
        ]);
    }

    public function test_paypal_webhook_replay_short_circuits(): void
    {
        config()->set('services.paypal.webhook_secret', 'test-secret');

        // Pre-claim the event id so the second call must short-circuit.
        WebhookEvent::create([
            'provider'     => 'paypal',
            'event_id'     => 'EVT-REPLAY',
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'payload'      => [],
            'processed_at' => now(),
        ]);

        $payload = ['id' => 'EVT-REPLAY', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];
        $body    = json_encode($payload);
        $sig     = hash_hmac('sha256', $body, 'test-secret');

        $this->call(
            method: 'POST',
            uri: '/api/webhooks/paypal',
            server: [
                'CONTENT_TYPE'             => 'application/json',
                'HTTP_PAYPAL_TRANSMISSION_SIG' => $sig,
            ],
            content: $body,
        )
            ->assertOk()
            ->assertJson(['received' => true, 'replay' => true]);
    }
}
