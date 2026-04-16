<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationLevel;
use App\Models\Bid;
use App\Models\Company;
use App\Models\IcvCertificate;
use App\Models\Rfq;
use App\Models\User;
use App\Services\Procurement\IcvScoringService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 4 (UAE Compliance Roadmap) — In-Country Value scoring regression
 * suite. Covers the four behaviours that justify the change:
 *
 *   1. Composite score formula collapses to pure price when weight = 0
 *   2. Composite score blends price + ICV correctly when weight > 0
 *   3. Disqualification by minimum ICV score works + sorts to bottom
 *   4. Supplier upload → admin verification flow + Company.latestActiveIcvScore()
 */
class IcvScoringPhase4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCompany(string $name, CompanyType $type = CompanyType::SUPPLIER, ?float $icvScore = null): Company
    {
        $company = Company::create([
            'name' => $name,
            'registration_number' => 'REG-'.uniqid(),
            'tax_number' => 'TRN-'.random_int(100000, 999999),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'verification_level' => VerificationLevel::BRONZE,
            'email' => strtolower(str_replace(' ', '', $name)).'@t.test',
            'address' => '101 Sheikh Zayed Road',
            'city' => 'Dubai',
            'country' => 'AE',
        ]);

        if ($icvScore !== null) {
            IcvCertificate::create([
                'company_id' => $company->id,
                'issuer' => IcvCertificate::ISSUER_MOIAT,
                'certificate_number' => 'CERT-'.uniqid(),
                'score' => $icvScore,
                'issued_date' => now()->subMonths(6),
                'expires_date' => now()->addMonths(6),
                'status' => IcvCertificate::STATUS_VERIFIED,
                'verified_at' => now(),
            ]);
        }

        return $company;
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

    private function makeRfq(Company $buyer, int $icvWeight = 0, ?float $icvMinimum = null): Rfq
    {
        return Rfq::create([
            'rfq_number' => 'RFQ-'.uniqid(),
            'title' => 'Test RFQ',
            'company_id' => $buyer->id,
            'type' => RfqType::SUPPLIER,
            'status' => RfqStatus::OPEN,
            'items' => [['name' => 'Widget', 'qty' => 10]],
            'budget' => 100000,
            'currency' => 'AED',
            'icv_weight_percentage' => $icvWeight,
            'icv_minimum_score' => $icvMinimum,
        ]);
    }

    private function makeBid(Rfq $rfq, Company $supplier, float $price): Bid
    {
        return Bid::create([
            'rfq_id' => $rfq->id,
            'company_id' => $supplier->id,
            'provider_id' => $this->makeUser($supplier)->id,
            'status' => BidStatus::SUBMITTED,
            'price' => $price,
            'currency' => 'AED',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. Composite score formula
    // ─────────────────────────────────────────────────────────────────

    public function test_composite_score_collapses_to_pure_price_when_weight_zero(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer = $this->makeCompany('Z-Weight Buyer', CompanyType::BUYER);
        $cheap = $this->makeCompany('Cheap Supplier', CompanyType::SUPPLIER, icvScore: 90);
        $pricey = $this->makeCompany('Pricey Supplier', CompanyType::SUPPLIER, icvScore: 10);

        $rfq = $this->makeRfq($buyer, icvWeight: 0);
        $cheapBid = $this->makeBid($rfq, $cheap, 1000);
        $priceyBid = $this->makeBid($rfq, $pricey, 1500);

        $bids = $rfq->bids()->with('company')->get();
        $cheapScore = $service->scoreBid($cheapBid->fresh(), $bids, $rfq);
        $priceyScore = $service->scoreBid($priceyBid->fresh(), $bids, $rfq);

        // Pure price scoring: cheap = 100, pricey = 1000/1500*100 = 66.67
        $this->assertSame(100.0, $cheapScore['composite']);
        $this->assertEquals(66.67, $priceyScore['composite']);
        // ICV scores still surface, just don't influence composite.
        $this->assertEquals(90.0, $cheapScore['icv_score']);
        $this->assertEquals(10.0, $priceyScore['icv_score']);
    }

    public function test_composite_score_blends_price_and_icv_when_weight_positive(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer = $this->makeCompany('Buyer X', CompanyType::BUYER);
        $cheap = $this->makeCompany('Low ICV Cheap', CompanyType::SUPPLIER, icvScore: 20);
        $highIcv = $this->makeCompany('High ICV Pricey', CompanyType::SUPPLIER, icvScore: 80);

        $rfq = $this->makeRfq($buyer, icvWeight: 30);
        $cheapBid = $this->makeBid($rfq, $cheap, 1000);
        $highIcvBid = $this->makeBid($rfq, $highIcv, 1200);

        $bids = $rfq->bids()->with('company')->get();
        $a = $service->scoreBid($cheapBid->fresh(), $bids, $rfq);
        $b = $service->scoreBid($highIcvBid->fresh(), $bids, $rfq);

        // Cheap: price=100, icv=20, composite = 0.7*100 + 0.3*20 = 76
        $this->assertSame(100.0, $a['price_score']);
        $this->assertEquals(20.0, $a['icv_score']);
        $this->assertEquals(76.0, $a['composite']);

        // HighIcv: price = 1000/1200*100 = 83.33, icv=80, composite = 0.7*83.33 + 0.3*80 = 82.33
        $this->assertEquals(83.33, $b['price_score']);
        $this->assertEquals(80.0, $b['icv_score']);
        $this->assertEquals(82.33, $b['composite']);

        // The higher-ICV bid wins on composite even though it costs more.
        $this->assertGreaterThan($a['composite'], $b['composite']);
    }

    public function test_supplier_with_no_icv_certificate_scores_zero_on_icv_axis(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer = $this->makeCompany('Buyer Y', CompanyType::BUYER);
        $supplier = $this->makeCompany('No Cert Supplier', CompanyType::SUPPLIER); // no ICV
        $rfq = $this->makeRfq($buyer, icvWeight: 30);
        $bid = $this->makeBid($rfq, $supplier, 1000);

        $bids = $rfq->bids()->with('company')->get();
        $score = $service->scoreBid($bid->fresh(), $bids, $rfq);

        $this->assertSame(0.0, $score['icv_score']);
        // composite = 0.7 * 100 + 0.3 * 0 = 70
        $this->assertEquals(70.0, $score['composite']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Disqualification + ranking
    // ─────────────────────────────────────────────────────────────────

    public function test_bids_below_minimum_icv_are_disqualified_and_sink_to_bottom(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer = $this->makeCompany('Picky Buyer', CompanyType::BUYER);
        $low = $this->makeCompany('Low ICV Cheap', CompanyType::SUPPLIER, icvScore: 25);
        $okIcv = $this->makeCompany('OK ICV Pricey', CompanyType::SUPPLIER, icvScore: 55);

        // Minimum 40 — `low` is below cutoff
        $rfq = $this->makeRfq($buyer, icvWeight: 30, icvMinimum: 40);
        $this->makeBid($rfq, $low, 800);
        $this->makeBid($rfq, $okIcv, 1500);

        $bids = $rfq->bids()->with('company')->get();
        $ranking = $service->rankBids($bids, $rfq);

        $this->assertCount(2, $ranking);

        // Even though `low` has the better composite score numerically,
        // disqualified bids are pushed to the bottom — the OK supplier
        // ranks #1.
        $this->assertSame($okIcv->id, $ranking[0]['company_id']);
        $this->assertFalse($ranking[0]['disqualified']);
        $this->assertSame(1, $ranking[0]['rank']);

        $this->assertSame($low->id, $ranking[1]['company_id']);
        $this->assertTrue($ranking[1]['disqualified']);
        $this->assertSame(2, $ranking[1]['rank']);
    }

    public function test_ranking_respects_composite_when_no_minimum_set(): void
    {
        $service = $this->app->make(IcvScoringService::class);

        $buyer = $this->makeCompany('Buyer R', CompanyType::BUYER);
        $a = $this->makeCompany('A — high icv', CompanyType::SUPPLIER, icvScore: 90);
        $b = $this->makeCompany('B — low icv', CompanyType::SUPPLIER, icvScore: 10);
        $c = $this->makeCompany('C — no cert', CompanyType::SUPPLIER);

        $rfq = $this->makeRfq($buyer, icvWeight: 50);
        $this->makeBid($rfq, $a, 1000);
        $this->makeBid($rfq, $b, 1000);
        $this->makeBid($rfq, $c, 1000);

        $bids = $rfq->bids()->with('company')->get();
        $ranking = $service->rankBids($bids, $rfq);

        // Same price → composite is purely the ICV score.
        $this->assertSame($a->id, $ranking[0]['company_id']);
        $this->assertSame($b->id, $ranking[1]['company_id']);
        $this->assertSame($c->id, $ranking[2]['company_id']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. Company.latestActiveIcvScore()
    // ─────────────────────────────────────────────────────────────────

    public function test_company_returns_highest_active_icv_score_across_issuers(): void
    {
        $company = $this->makeCompany('Multi-issuer Supplier');

        IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'M-1',
            'score' => 30,
            'issued_date' => now()->subYear(),
            'expires_date' => now()->addMonths(3),
            'status' => IcvCertificate::STATUS_VERIFIED,
        ]);
        IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => IcvCertificate::ISSUER_ADNOC,
            'certificate_number' => 'A-1',
            'score' => 65,
            'issued_date' => now()->subMonths(3),
            'expires_date' => now()->addMonths(9),
            'status' => IcvCertificate::STATUS_VERIFIED,
        ]);

        $this->assertEquals(65.0, $company->latestActiveIcvScore());
    }

    public function test_expired_certificates_are_ignored(): void
    {
        $company = $this->makeCompany('Expired Supplier');

        IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'EXP-1',
            'score' => 80,
            'issued_date' => now()->subYears(2),
            'expires_date' => now()->subDays(1), // expired yesterday
            'status' => IcvCertificate::STATUS_VERIFIED,
        ]);

        $this->assertNull($company->latestActiveIcvScore());
    }

    public function test_pending_certificates_are_ignored(): void
    {
        $company = $this->makeCompany('Pending Supplier');

        IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'PEND-1',
            'score' => 75,
            'issued_date' => now()->subDays(5),
            'expires_date' => now()->addMonths(11),
            'status' => IcvCertificate::STATUS_PENDING, // not yet verified
        ]);

        $this->assertNull($company->latestActiveIcvScore());
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. Upload + admin verification HTTP flows
    // ─────────────────────────────────────────────────────────────────

    public function test_supplier_can_upload_icv_certificate(): void
    {
        Storage::fake('local');
        $company = $this->makeCompany('Uploader Co');
        $user = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        $response = $this->actingAs($user)->post(route('dashboard.icv-certificates.store'), [
            'issuer' => 'moiat',
            'certificate_number' => 'ICV-2026-99999',
            'score' => 42.50,
            'issued_date' => now()->subDay()->toDateString(),
            'expires_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect(route('dashboard.icv-certificates.index'));
        $this->assertDatabaseHas('icv_certificates', [
            'company_id' => $company->id,
            'certificate_number' => 'ICV-2026-99999',
            'status' => 'pending',
        ]);
    }

    public function test_supplier_cannot_upload_duplicate_certificate(): void
    {
        Storage::fake('local');
        $company = $this->makeCompany('Dup Co');
        $user = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => 'moiat',
            'certificate_number' => 'DUP-1',
            'score' => 50,
            'issued_date' => now()->subDays(10),
            'expires_date' => now()->addMonths(11),
            'status' => IcvCertificate::STATUS_VERIFIED,
        ]);

        $this->actingAs($user)->post(route('dashboard.icv-certificates.store'), [
            'issuer' => 'moiat',
            'certificate_number' => 'DUP-1',
            'score' => 60,
            'issued_date' => now()->toDateString(),
            'expires_date' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('cert.pdf', 100),
        ])->assertSessionHasErrors('certificate_number');
    }

    public function test_admin_can_approve_pending_certificate(): void
    {
        $company = $this->makeCompany('To-Approve Co');
        $admin = $this->makeUser($this->makeCompany('Admin Co', CompanyType::BUYER), UserRole::ADMIN);

        $cert = IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => 'moiat',
            'certificate_number' => 'APPROVE-1',
            'score' => 55,
            'issued_date' => now()->subDays(2),
            'expires_date' => now()->addYear(),
            'status' => IcvCertificate::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.icv-certificates.approve', $cert->id))
            ->assertRedirect();

        $this->assertSame(IcvCertificate::STATUS_VERIFIED, $cert->fresh()->status);
        $this->assertNotNull($cert->fresh()->verified_at);
    }

    public function test_admin_can_reject_pending_certificate_with_reason(): void
    {
        $company = $this->makeCompany('To-Reject Co');
        $admin = $this->makeUser($this->makeCompany('Admin Co', CompanyType::BUYER), UserRole::ADMIN);

        $cert = IcvCertificate::create([
            'company_id' => $company->id,
            'issuer' => 'moiat',
            'certificate_number' => 'REJECT-1',
            'score' => 99,
            'issued_date' => now()->subDays(2),
            'expires_date' => now()->addYear(),
            'status' => IcvCertificate::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.icv-certificates.reject', $cert->id), [
                'reason' => 'Score does not match published value',
            ])
            ->assertRedirect();

        $this->assertSame(IcvCertificate::STATUS_REJECTED, $cert->fresh()->status);
        $this->assertSame('Score does not match published value', $cert->fresh()->rejection_reason);
    }

    public function test_supplier_dashboard_renders(): void
    {
        $company = $this->makeCompany('Dashboard Co');
        $user = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        $this->actingAs($user)
            ->get(route('dashboard.icv-certificates.index'))
            ->assertOk()
            ->assertSee('In-Country Value');
    }
}
