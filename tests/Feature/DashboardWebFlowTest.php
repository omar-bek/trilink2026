<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\ShipmentLocationUpdated;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * End-to-end web (Blade) flow tests for the dashboard.
 *
 * Each test boots a fresh in-memory SQLite database, seeds Spatie roles, then
 * exercises one slice of the procurement workflow through HTTP requests.
 */
class DashboardWebFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Create a buyer company + buyer user, log them in.
     */
    private function loginAsBuyer(): array
    {
        $company = Company::create([
            'name' => 'Acme Buyers LLC',
            'registration_number' => 'TRN-'.uniqid(),
            'type' => CompanyType::BUYER,
            'status' => CompanyStatus::ACTIVE,
            'email' => 'buyer@acme.test',
            'phone' => '+971500000001',
            'city' => 'Dubai',
            'country' => 'UAE',
        ]);

        $this->attachValidTradeLicense($company);

        $user = User::create([
            'first_name' => 'Bob',
            'last_name' => 'Buyer',
            'email' => 'bob@acme.test',
            'password' => 'secret-pass',
            'phone' => '+971500000002',
            'role' => UserRole::BUYER,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);

        $this->actingAs($user);

        return [$user, $company];
    }

    private function loginAsSupplier(): array
    {
        $company = Company::create([
            'name' => 'Widgets Co',
            'registration_number' => 'TRN-'.uniqid(),
            'type' => CompanyType::SUPPLIER,
            'status' => CompanyStatus::ACTIVE,
            'email' => 'sup@widgets.test',
            'phone' => '+971500000003',
            'city' => 'Sharjah',
            'country' => 'UAE',
        ]);

        $this->attachValidTradeLicense($company);

        $user = User::create([
            'first_name' => 'Sara',
            'last_name' => 'Supplier',
            'email' => 'sara@widgets.test',
            'password' => 'secret-pass',
            'phone' => '+971500000004',
            'role' => UserRole::SUPPLIER,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);

        $this->actingAs($user);

        return [$user, $company];
    }

    /**
     * Sprint A.5 — every test company needs a valid trade license
     * because ContractService::createFromBid() and PaymentService /
     * EscrowService all re-check at action time. In production the
     * license is always present (registration gates on it); test
     * fixtures must mirror that invariant or the bid → contract →
     * payment flow blocks.
     */
    private function attachValidTradeLicense(Company $company): void
    {
        CompanyDocument::create([
            'company_id' => $company->id,
            'type' => DocumentType::TRADE_LICENSE,
            'label' => 'Trade License',
            'file_path' => 'test/trade-license.pdf',
            'status' => CompanyDocument::STATUS_VERIFIED,
            'issued_at' => now()->subYear(),
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_landing_page_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_register_form_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        [$user] = $this->loginAsBuyer();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($user->first_name);
    }

    public function test_purchase_request_index_lists_company_prs_only(): void
    {
        [, $company] = $this->loginAsBuyer();

        // Mine
        PurchaseRequest::create([
            'title' => 'My PR',
            'company_id' => $company->id,
            'buyer_id' => auth()->id(),
            'status' => PurchaseRequestStatus::DRAFT,
            'budget' => 50000,
            'currency' => 'AED',
            'items' => [],
            'required_date' => now()->addDays(30),
        ]);

        // Other company's PR — must NOT appear.
        $other = Company::create([
            'name' => 'Other LLC',
            'registration_number' => 'TRN-OTHER',
            'type' => CompanyType::BUYER,
            'status' => CompanyStatus::ACTIVE,
        ]);
        PurchaseRequest::create([
            'title' => 'Hidden PR',
            'company_id' => $other->id,
            'buyer_id' => auth()->id(),
            'status' => PurchaseRequestStatus::DRAFT,
            'budget' => 99999,
            'currency' => 'AED',
            'items' => [],
            'required_date' => now()->addDays(30),
        ]);

        $this->get('/dashboard/purchase-requests')
            ->assertOk()
            ->assertSee('My PR')
            ->assertDontSee('Hidden PR');
    }

    public function test_buyer_can_create_purchase_request_via_form(): void
    {
        $this->loginAsBuyer();

        // StorePurchaseRequestRequest requires category_id (exists check).
        $category = Category::create(['name' => 'Office Furniture', 'slug' => 'office-furniture-'.uniqid()]);

        $payload = [
            'title' => 'Office Chairs',
            'description' => 'Ergonomic chairs for new branch',
            'category_id' => $category->id,
            'budget' => 25000,
            'currency' => 'AED',
            'required_date' => now()->addDays(45)->format('Y-m-d'),
            'delivery_address' => 'Dubai Silicon Oasis',
            'delivery_city' => 'Dubai',
            'items' => [
                ['name' => 'Mesh Chair', 'qty' => 50, 'unit' => 'pcs', 'spec' => 'Adjustable'],
            ],
        ];

        $this->post('/dashboard/purchase-requests', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_requests', [
            'title' => 'Office Chairs',
            'budget' => 25000,
            'buyer_id' => auth()->id(),
        ]);
    }

    public function test_supplier_cannot_create_purchase_request(): void
    {
        $this->loginAsSupplier();

        $this->post('/dashboard/purchase-requests', [
            'title' => 'Forbidden',
            'budget' => 100,
            'currency' => 'AED',
            'required_date' => now()->addDays(10)->format('Y-m-d'),
        ])->assertForbidden();
    }

    public function test_supplier_can_submit_bid_on_open_rfq(): void
    {
        // Buyer creates company + RFQ.
        $buyerCompany = Company::create([
            'name' => 'BuyCo',
            'registration_number' => 'TRN-BUY',
            'type' => CompanyType::BUYER,
            'status' => CompanyStatus::ACTIVE,
        ]);

        $rfq = Rfq::create([
            'title' => 'Need Steel',
            'company_id' => $buyerCompany->id,
            'type' => RfqType::SUPPLIER,
            'status' => RfqStatus::OPEN,
            'budget' => 100000,
            'currency' => 'AED',
            'items' => [],
            'deadline' => now()->addDays(15),
        ]);

        $this->loginAsSupplier();

        // Phase 2 trade fields are mandatory: incoterm + country_of_origin
        // + tax_treatment are validated by StoreBidRequest. Without them
        // the form fails validation and no bid row is created.
        $payload = [
            'price' => 95000,
            'currency' => 'AED',
            'delivery_time_days' => 30,
            'payment_terms' => '30/70',
            'validity_date' => now()->addDays(30)->format('Y-m-d'),
            'notes' => 'High-quality steel',
            'incoterm' => 'CIF',
            'country_of_origin' => 'AE',
            'tax_treatment' => 'exclusive',
        ];

        $this->post("/dashboard/rfqs/{$rfq->id}/bids", $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('bids', [
            'rfq_id' => $rfq->id,
            'price' => 95000,
            'provider_id' => auth()->id(),
        ]);
    }

    public function test_buyer_can_accept_bid_and_others_get_rejected(): void
    {
        [$buyerUser, $buyerCompany] = $this->loginAsBuyer();

        $supplierA = Company::create(['name' => 'A', 'registration_number' => 'A', 'type' => CompanyType::SUPPLIER, 'status' => CompanyStatus::ACTIVE]);
        $supplierB = Company::create(['name' => 'B', 'registration_number' => 'B', 'type' => CompanyType::SUPPLIER, 'status' => CompanyStatus::ACTIVE]);
        // Both inline supplier companies need a valid trade license so
        // ContractService::createFromBid can issue the contract on accept.
        $this->attachValidTradeLicense($supplierA);
        $this->attachValidTradeLicense($supplierB);

        $rfq = Rfq::create([
            'title' => 'Cables',
            'company_id' => $buyerCompany->id,
            'type' => RfqType::SUPPLIER,
            'status' => RfqStatus::OPEN,
            'budget' => 50000,
            'currency' => 'AED',
            'items' => [],
            'deadline' => now()->addDays(10),
        ]);

        $bidA = Bid::create([
            'rfq_id' => $rfq->id,
            'company_id' => $supplierA->id,
            'provider_id' => $buyerUser->id,
            'status' => BidStatus::SUBMITTED,
            'price' => 48000,
            'currency' => 'AED',
            'delivery_time_days' => 20,
            'validity_date' => now()->addDays(20),
        ]);

        $bidB = Bid::create([
            'rfq_id' => $rfq->id,
            'company_id' => $supplierB->id,
            'provider_id' => $buyerUser->id,
            'status' => BidStatus::SUBMITTED,
            'price' => 49000,
            'currency' => 'AED',
            'delivery_time_days' => 25,
            'validity_date' => now()->addDays(20),
        ]);

        $this->post("/dashboard/bids/{$bidA->id}/accept")->assertRedirect();

        $this->assertEquals(BidStatus::ACCEPTED->value, $bidA->fresh()->status->value);
        $this->assertEquals(BidStatus::REJECTED->value, $bidB->fresh()->status->value);
    }

    public function test_logistics_user_cannot_accept_bid(): void
    {
        $logisticsCompany = Company::create(['name' => 'Logi', 'registration_number' => 'L', 'type' => CompanyType::LOGISTICS, 'status' => CompanyStatus::ACTIVE]);
        $user = User::create([
            'first_name' => 'Lana', 'last_name' => 'Logi',
            'email' => 'lana@logi.test', 'password' => 'secret-pass',
            'role' => UserRole::LOGISTICS, 'status' => UserStatus::ACTIVE,
            'company_id' => $logisticsCompany->id,
        ]);

        $this->actingAs($user);

        // Create a bid to attempt accepting.
        $buyer = Company::create(['name' => 'B', 'registration_number' => 'B2', 'type' => CompanyType::BUYER, 'status' => CompanyStatus::ACTIVE]);
        $rfq = Rfq::create(['title' => 'X', 'company_id' => $buyer->id, 'type' => RfqType::SUPPLIER, 'status' => RfqStatus::OPEN, 'budget' => 1, 'currency' => 'AED', 'items' => [], 'deadline' => now()->addDay()]);
        $bid = Bid::create(['rfq_id' => $rfq->id, 'company_id' => $logisticsCompany->id, 'provider_id' => $user->id, 'status' => BidStatus::SUBMITTED, 'price' => 1, 'currency' => 'AED', 'delivery_time_days' => 1, 'validity_date' => now()->addDay()]);

        $this->post("/dashboard/bids/{$bid->id}/accept")->assertForbidden();
    }

    public function test_contract_pdf_download_returns_pdf_for_party(): void
    {
        [, $company] = $this->loginAsBuyer();

        $contract = Contract::create([
            'title' => 'Steel Supply',
            'buyer_company_id' => $company->id,
            'status' => ContractStatus::ACTIVE,
            'parties' => [['company_id' => $company->id, 'role' => 'buyer']],
            'total_amount' => 100000,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        $response = $this->get("/dashboard/contracts/{$contract->id}/pdf");
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_contract_pdf_forbidden_for_non_party(): void
    {
        $other = Company::create(['name' => 'OutCo', 'registration_number' => 'OUT', 'type' => CompanyType::BUYER, 'status' => CompanyStatus::ACTIVE]);
        $contract = Contract::create([
            'title' => 'Private Contract',
            'buyer_company_id' => $other->id,
            'status' => ContractStatus::ACTIVE,
            'parties' => [['company_id' => $other->id, 'role' => 'buyer']],
            'total_amount' => 1,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        $this->loginAsBuyer();

        $this->get("/dashboard/contracts/{$contract->id}/pdf")->assertForbidden();
    }

    public function test_payment_show_renders_with_breakdown(): void
    {
        [, $company] = $this->loginAsBuyer();

        $contract = Contract::create([
            'title' => 'C', 'buyer_company_id' => $company->id,
            'status' => ContractStatus::ACTIVE, 'parties' => [],
            'total_amount' => 10000, 'currency' => 'AED',
            'start_date' => now(), 'end_date' => now()->addMonth(),
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $company->id,
            'recipient_company_id' => $company->id,
            'buyer_id' => auth()->id(),
            'status' => PaymentStatus::PENDING_APPROVAL,
            'amount' => 5000,
            'vat_rate' => 5,
            'currency' => 'AED',
            'milestone' => 'Advance Payment',
        ]);

        $this->get("/dashboard/payments/{$payment->id}")
            ->assertOk()
            ->assertSee('Advance Payment');
    }

    public function test_shipment_track_event_dispatches_broadcast(): void
    {
        Event::fake([ShipmentLocationUpdated::class]);

        $logisticsCompany = Company::create(['name' => 'Log', 'registration_number' => 'L9', 'type' => CompanyType::LOGISTICS, 'status' => CompanyStatus::ACTIVE]);
        $user = User::create([
            'first_name' => 'L', 'last_name' => 'L',
            'email' => 'log@l.test', 'password' => 'secret-pass',
            'role' => UserRole::LOGISTICS, 'status' => UserStatus::ACTIVE,
            'company_id' => $logisticsCompany->id,
        ]);
        $this->actingAs($user);

        $contract = Contract::create([
            'title' => 'C',
            'buyer_company_id' => $logisticsCompany->id,
            'status' => ContractStatus::ACTIVE,
            'parties' => [],
            'total_amount' => 1,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        $shipment = Shipment::create([
            'contract_id' => $contract->id,
            'company_id' => $logisticsCompany->id,
            'status' => ShipmentStatus::IN_TRANSIT,
            'origin' => ['city' => 'Dubai'],
            'destination' => ['city' => 'AbuDhabi'],
        ]);

        $this->post("/dashboard/shipments/{$shipment->id}/track", [
            'status' => 'in_transit',
            'description' => 'Crossed Sharjah',
            'lat' => 25.31,
            'lng' => 55.50,
        ])->assertRedirect();

        Event::assertDispatched(ShipmentLocationUpdated::class);
    }
}
