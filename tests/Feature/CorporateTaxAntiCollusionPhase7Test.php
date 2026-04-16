<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\BeneficialOwner;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use App\Services\Procurement\AntiCollusionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorporateTaxAntiCollusionPhase7Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCompany(string $name, CompanyType $type = CompanyType::SUPPLIER): Company
    {
        return Company::create([
            'name' => $name,
            'registration_number' => 'REG-'.uniqid(),
            'tax_number' => 'TRN-'.random_int(100000, 999999),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'email' => strtolower(str_replace(' ', '', $name)).'@'.strtolower(str_replace(' ', '', $name)).'.ae',
            'phone' => '+97150'.random_int(1000000, 9999999),
            'address' => 'Dubai',
            'city' => 'Dubai',
            'country' => 'AE',
        ]);
    }

    private function makeUser(Company $company, UserRole $role = UserRole::SUPPLIER): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'u-'.uniqid().'@t.test',
            'password' => 'secret-pass',
            'role' => $role,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Corporate Tax model helpers
    // ─────────────────────────────────────────────────────────────────

    public function test_company_ct_annotation_returns_qfzp_label(): void
    {
        $company = $this->makeCompany('QFZP Co');
        $company->update(['corporate_tax_status' => 'qfzp']);
        $this->assertStringContainsString('QFZP', $company->fresh()->ctAnnotation());
    }

    public function test_company_ct_annotation_returns_exempt_label(): void
    {
        $company = $this->makeCompany('Small Co');
        $company->update(['corporate_tax_status' => 'exempt_below_threshold']);
        $this->assertStringContainsString('375,000', $company->fresh()->ctAnnotation());
    }

    public function test_company_ct_annotation_returns_null_for_standard_registered(): void
    {
        $company = $this->makeCompany('Normal Co');
        $company->update(['corporate_tax_status' => 'registered']);
        $this->assertNull($company->fresh()->ctAnnotation());
    }

    public function test_company_is_qfzp_helper(): void
    {
        $company = $this->makeCompany('QFZP Check');
        $company->update(['corporate_tax_status' => 'qfzp']);
        $this->assertTrue($company->fresh()->isQfzp());

        $company->update(['corporate_tax_status' => 'registered']);
        $this->assertFalse($company->fresh()->isQfzp());
    }

    // ─────────────────────────────────────────────────────────────────
    //  Anti-Collusion detection patterns
    // ─────────────────────────────────────────────────────────────────

    public function test_detects_shared_beneficial_owner_across_bidders(): void
    {
        $service = $this->app->make(AntiCollusionService::class);

        $buyer = $this->makeCompany('AC Buyer', CompanyType::BUYER);
        $suppA = $this->makeCompany('Supplier A');
        $suppB = $this->makeCompany('Supplier B');

        // Same beneficial owner in both companies
        BeneficialOwner::create([
            'company_id' => $suppA->id,
            'full_name' => 'Shared Owner',
            'nationality' => 'AE',
            'id_type' => 'emirates_id',
            'id_number' => '784-1980-1234567-1',
            'ownership_percentage' => 60,
            'is_pep' => false,
        ]);
        BeneficialOwner::create([
            'company_id' => $suppB->id,
            'full_name' => 'Shared Owner',
            'nationality' => 'AE',
            'id_type' => 'emirates_id',
            'id_number' => '784-1980-1234567-1', // same!
            'ownership_percentage' => 51,
            'is_pep' => false,
        ]);

        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-AC-1',
            'title' => 'Collusion Test',
            'company_id' => $buyer->id,
            'type' => RfqType::SUPPLIER,
            'status' => RfqStatus::OPEN,
            'items' => [['name' => 'Item', 'qty' => 1]],
            'budget' => 1000,
            'currency' => 'AED',
        ]);

        Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppA->id, 'provider_id' => $this->makeUser($suppA)->id, 'status' => BidStatus::SUBMITTED, 'price' => 900, 'currency' => 'AED']);
        Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppB->id, 'provider_id' => $this->makeUser($suppB)->id, 'status' => BidStatus::SUBMITTED, 'price' => 950, 'currency' => 'AED']);

        $findings = $service->analyzeRfq($rfq);

        $boCritical = $findings->where('type', 'shared_beneficial_owner')->where('severity', 'critical');
        $this->assertGreaterThan(0, $boCritical->count(), 'Should detect shared BO as critical');

        // PDPL: evidence must NOT contain raw id_number — only sha1 hash
        $evidence = $boCritical->first()['evidence'];
        $this->assertArrayHasKey('id_number_hash', $evidence);
        $this->assertSame(sha1('784-1980-1234567-1'), $evidence['id_number_hash']);
    }

    public function test_detects_shared_ip_across_bidders(): void
    {
        $service = $this->app->make(AntiCollusionService::class);

        $buyer = $this->makeCompany('IP Buyer', CompanyType::BUYER);
        $suppA = $this->makeCompany('IP Supplier A');
        $suppB = $this->makeCompany('IP Supplier B');

        $userA = $this->makeUser($suppA);
        $userB = $this->makeUser($suppB);

        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-IP-1',
            'title' => 'IP Test',
            'company_id' => $buyer->id,
            'type' => RfqType::SUPPLIER,
            'status' => RfqStatus::OPEN,
            'items' => [['name' => 'X', 'qty' => 1]],
            'budget' => 1000,
            'currency' => 'AED',
        ]);

        $bidA = Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppA->id, 'provider_id' => $userA->id, 'status' => BidStatus::SUBMITTED, 'price' => 900, 'currency' => 'AED']);
        $bidB = Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppB->id, 'provider_id' => $userB->id, 'status' => BidStatus::SUBMITTED, 'price' => 950, 'currency' => 'AED']);

        // The service reads IPs from audit_logs (no last_login_ip on
        // User schema). Simulate the audit rows the AuditLogObserver
        // would create during a real bid submit — same IP for both.
        AuditLog::create([
            'user_id' => $userA->id, 'company_id' => $suppA->id,
            'action' => AuditAction::CREATE,
            'resource_type' => 'Bid', 'resource_id' => $bidA->id,
            'ip_address' => '203.0.113.42', 'user_agent' => 'test',
            'status' => 'success',
        ]);
        AuditLog::create([
            'user_id' => $userB->id, 'company_id' => $suppB->id,
            'action' => AuditAction::CREATE,
            'resource_type' => 'Bid', 'resource_id' => $bidB->id,
            'ip_address' => '203.0.113.42', 'user_agent' => 'test',
            'status' => 'success',
        ]);

        $findings = $service->analyzeRfq($rfq);

        $ipHighs = $findings->where('type', 'shared_ip')->where('severity', 'high');
        $this->assertGreaterThan(0, $ipHighs->count());
        $this->assertSame('203.0.113.42', $ipHighs->first()['evidence']['ip']);
    }

    public function test_no_findings_when_single_bidder(): void
    {
        $service = $this->app->make(AntiCollusionService::class);
        $buyer = $this->makeCompany('Solo Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Solo Supplier');

        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-SOLO', 'title' => 'Solo', 'company_id' => $buyer->id,
            'type' => RfqType::SUPPLIER, 'status' => RfqStatus::OPEN,
            'items' => [['name' => 'X', 'qty' => 1]], 'budget' => 1000, 'currency' => 'AED',
        ]);

        Bid::create(['rfq_id' => $rfq->id, 'company_id' => $supplier->id, 'provider_id' => $this->makeUser($supplier)->id, 'status' => BidStatus::SUBMITTED, 'price' => 900, 'currency' => 'AED']);

        $this->assertCount(0, $service->analyzeRfq($rfq));
    }

    public function test_detects_shared_email_domain_excluding_generic(): void
    {
        $service = $this->app->make(AntiCollusionService::class);
        $buyer = $this->makeCompany('Domain Buyer', CompanyType::BUYER);

        // Two companies using the same corporate domain
        $suppA = $this->makeCompany('Domain A');
        $suppA->update(['email' => 'a@samecorp.ae']);
        $suppB = $this->makeCompany('Domain B');
        $suppB->update(['email' => 'b@samecorp.ae']);

        // Third company using gmail — should NOT trigger
        $suppC = $this->makeCompany('Gmail C');
        $suppC->update(['email' => 'c@gmail.com']);

        $rfq = Rfq::create([
            'rfq_number' => 'RFQ-DOM', 'title' => 'Domain Test', 'company_id' => $buyer->id,
            'type' => RfqType::SUPPLIER, 'status' => RfqStatus::OPEN,
            'items' => [['name' => 'X', 'qty' => 1]], 'budget' => 1000, 'currency' => 'AED',
        ]);

        Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppA->id, 'provider_id' => $this->makeUser($suppA)->id, 'status' => BidStatus::SUBMITTED, 'price' => 900, 'currency' => 'AED']);
        Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppB->id, 'provider_id' => $this->makeUser($suppB)->id, 'status' => BidStatus::SUBMITTED, 'price' => 950, 'currency' => 'AED']);
        Bid::create(['rfq_id' => $rfq->id, 'company_id' => $suppC->id, 'provider_id' => $this->makeUser($suppC)->id, 'status' => BidStatus::SUBMITTED, 'price' => 1000, 'currency' => 'AED']);

        $findings = $service->analyzeRfq($rfq);

        $domainFindings = $findings->where('type', 'shared_email_domain');
        $this->assertGreaterThan(0, $domainFindings->count());
        $this->assertSame('samecorp.ae', $domainFindings->first()['evidence']['domain']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Admin UI
    // ─────────────────────────────────────────────────────────────────

    public function test_admin_anti_collusion_page_renders(): void
    {
        $admin = $this->makeUser($this->makeCompany('Admin Co', CompanyType::BUYER), UserRole::ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.anti-collusion.index'))
            ->assertOk()
            ->assertSee('Anti-Collusion');
    }
}
