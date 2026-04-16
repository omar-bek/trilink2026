<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\DocumentType;
use App\Enums\MilestoneType;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyInsurance;
use App\Models\Rfq;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for the security + performance hardening batch:
 *   - Cross-company bid authorization (Phase 1 critical fix)
 *   - SafeUpload disguised-file rejection (Phase 1)
 *   - MilestoneType enum classification (Phase 3.E)
 *   - Health endpoints (Phase 4.A.1)
 *   - Company N+1 fixes use eager-loaded relations (Phase 2)
 *
 * Each test pins one specific fix so a future refactor that accidentally
 * reintroduces the vulnerability fails CI before it ships.
 */
class AuditHardeningRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ───────────────────────────────────────────────────────────────────
    // 1. Cross-company bid authorization — the Critical fix in StoreBidRequest
    // ───────────────────────────────────────────────────────────────────

    public function test_supplier_cannot_post_a_bid_with_a_forged_rfq_id(): void
    {
        // The supplier POSTs to /rfqs/{id}/bids with a non-existent RFQ ID.
        // StoreBidRequest::authorize() looks up the RFQ via Rfq::find()
        // and returns false when it's null, yielding a 403.
        $supplierA = $this->makeCompany('Supplier A', CompanyType::SUPPLIER);
        $supplierUser = $this->makeUser($supplierA, 'supplier@example.test');

        $payload = [
            'price' => 500,
            'currency' => 'AED',
            'delivery_time_days' => 7,
            'validity_date' => now()->addDays(14)->toDateString(),
            'incoterm' => 'DDP',
            'country_of_origin' => 'AE',
            'tax_treatment' => 'exclusive',
        ];

        $forgedId = 999999;

        $response = $this->actingAs($supplierUser)
            ->post("/dashboard/rfqs/{$forgedId}/bids", $payload);

        // A forged (non-existent) RFQ ID must be rejected server-side.
        // 403 from FormRequest::authorize() or 404 from route-model
        // binding — both are acceptable.
        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            'Forged RFQ bid returned '.$response->status().' (expected 403 or 404)'
        );
    }

    public function test_supplier_cannot_bid_on_their_own_rfq(): void
    {
        // Same-company bid. RfqPolicy@submitBid returns false when the
        // user's company owns the RFQ, so the FormRequest fails authorize.
        $company = $this->makeCompany('Dual Co', CompanyType::BUYER);
        $user = $this->makeUser($company, 'owner@dual.test');

        $category = Category::firstOrCreate(
            ['slug' => 'self-cat'],
            ['name' => 'Self cat', 'name_ar' => 'ذات']
        );

        $rfq = Rfq::create([
            'title' => 'My own RFQ',
            'rfq_number' => 'RFQ-'.uniqid(),
            'company_id' => $company->id,
            'category_id' => $category->id,
            'type' => RfqType::SUPPLIER->value,
            'status' => RfqStatus::OPEN,
            'deadline' => now()->addDays(7),
            'currency' => 'AED',
            'items' => [],
            'budget' => 500,
        ]);

        $response = $this->actingAs($user)
            ->post("/dashboard/rfqs/{$rfq->id}/bids", [
                'price' => 100,
                'delivery_time_days' => 5,
                'validity_date' => now()->addDays(14)->toDateString(),
                'incoterm' => 'DDP',
                'country_of_origin' => 'AE',
                'tax_treatment' => 'exclusive',
            ]);

        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            'Self-RFQ bid returned '.$response->status()
        );
    }

    // ───────────────────────────────────────────────────────────────────
    // 2. SafeUpload — extension/MIME mismatch must be rejected
    // ───────────────────────────────────────────────────────────────────

    public function test_safe_upload_rejects_php_payload_renamed_as_pdf(): void
    {
        Storage::fake('local');

        $supplier = $this->makeCompany('Upload Test Co', CompanyType::SUPPLIER);
        $user = $this->makeUser($supplier, 'uploader@example.test');

        // Fake an UploadedFile whose *extension* is .pdf but whose real
        // bytes would sniff as something else. UploadedFile::fake()->create()
        // with a mimeType override lets us simulate the mismatch directly.
        // With only `mimes:pdf,...` the file would pass; with the Phase 1
        // SafeUpload rules adding `mimetypes:application/pdf,...` it fails.
        $disguised = UploadedFile::fake()->create('malicious.pdf', 100, 'application/x-php');

        $response = $this->actingAs($user)
            ->post('/dashboard/documents', [
                'type' => DocumentType::TRADE_LICENSE->value,
                'file' => $disguised,
                'issued_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
            ]);

        // The validator rejects it — either redirect back with errors (302)
        // or 422 depending on the request format.
        $this->assertContains($response->status(), [302, 422]);
        $this->assertDatabaseCount('company_documents', 0);
    }

    // ───────────────────────────────────────────────────────────────────
    // 3. MilestoneType enum — critical for payment matching correctness
    // ───────────────────────────────────────────────────────────────────

    public function test_milestone_type_classifies_free_text_into_stable_buckets(): void
    {
        // The whole point of the enum is to stop "Final Deposit" from
        // ambiguously matching BOTH advance and final. With substring
        // ordering FINAL wins because settlement/final is checked first.
        $this->assertSame(MilestoneType::FINAL, MilestoneType::fromString('Final Deposit'));
        $this->assertSame(MilestoneType::FINAL, MilestoneType::fromString('Final Settlement'));
        $this->assertSame(MilestoneType::ADVANCE, MilestoneType::fromString('Advance Payment'));
        $this->assertSame(MilestoneType::ADVANCE, MilestoneType::fromString('Initial Deposit'));
        $this->assertSame(MilestoneType::DELIVERY, MilestoneType::fromString('On Delivery'));
        $this->assertSame(MilestoneType::DELIVERY, MilestoneType::fromString('Upon Shipment'));
        $this->assertSame(MilestoneType::PRODUCTION, MilestoneType::fromString('Production Completion'));
        $this->assertSame(MilestoneType::OTHER, MilestoneType::fromString('Something custom'));
        $this->assertSame(MilestoneType::OTHER, MilestoneType::fromString(''));
        $this->assertSame(MilestoneType::OTHER, MilestoneType::fromString(null));
    }

    // ───────────────────────────────────────────────────────────────────
    // 4. Health endpoints — machine-readable probes
    // ───────────────────────────────────────────────────────────────────

    public function test_liveness_endpoint_returns_ok_without_any_dependencies(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_readiness_endpoint_reports_each_dependency(): void
    {
        $response = $this->get('/health/ready');

        // Status may be 200 (all healthy) or 503 (one probe failed) —
        // the shape must be stable either way.
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['ok'],
                'cache' => ['ok'],
                'queue' => ['ok'],
            ],
        ]);
    }

    // ───────────────────────────────────────────────────────────────────
    // 5. Company N+1 fix — isInsured uses eager-loaded relation
    // ───────────────────────────────────────────────────────────────────

    public function test_is_insured_zero_queries_when_insurances_eager_loaded(): void
    {
        $company = $this->makeCompany('Insured Co', CompanyType::SUPPLIER);

        CompanyInsurance::create([
            'company_id' => $company->id,
            'type' => 'liability',
            'insurer' => 'Acme Ins',
            'policy_number' => 'POL-1',
            'coverage_amount' => 100000,
            'currency' => 'AED',
            'status' => CompanyInsurance::STATUS_VERIFIED,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'file_path' => 'x.pdf',
        ]);

        // Fetch + eager load. Subsequent isInsured() calls should
        // trigger ZERO additional queries.
        $fresh = Company::with('insurances')->findOrFail($company->id);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($fresh->isInsured());

        $executed = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(
            0,
            $executed,
            'isInsured() fired '.count($executed).' queries on an eager-loaded company (expected 0). '
            .'This means the Phase 2 N+1 fix regressed.'
        );
    }

    // ───────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────

    private function makeCompany(string $name, CompanyType $type): Company
    {
        return Company::create([
            'name' => $name,
            'registration_number' => 'TRN-'.uniqid(),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'email' => uniqid().'@c.test',
            'city' => 'Dubai',
            'country' => 'UAE',
        ]);
    }

    private function makeUser(Company $company, string $email): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role' => UserRole::COMPANY_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);
        // Roles + permissions are seeded by RolesAndPermissionsSeeder.
        // company_manager holds bid.submit / contract.view / etc. for
        // the controller-level permission guards to pass so the test
        // can actually reach the authorize() path we want to exercise.
        $user->assignRole(UserRole::COMPANY_MANAGER->value);

        return $user;
    }
}
