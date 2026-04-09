<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\ExecutePrivacyErasureJob;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Notifications\DataBreachNotification;
use App\Services\Privacy\ConsentLedger;
use App\Services\Privacy\DataErasureService;
use App\Services\Privacy\DataExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2 (UAE Compliance Roadmap) — PDPL implementation regression
 * suite. Covers the four areas a PDPL audit would actually probe:
 *
 *   1. Encryption at rest — sensitive columns are unreadable as raw
 *      DB rows but transparent through the model.
 *   2. Consent ledger — append-only, captures IP+UA, withdrawal works.
 *   3. Data subject rights — DSAR builds an archive, erasure schedules
 *      with cooling period and respects active-contract blockers.
 *   4. Public + dashboard surfaces — privacy policy renders, cookie
 *      banner POST records consent, dashboard hub is gated.
 */
class PdplPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Fixtures
    // ─────────────────────────────────────────────────────────────────

    private function makeCompany(string $name, CompanyType $type = CompanyType::BUYER): Company
    {
        return Company::create([
            'name'                => $name,
            'registration_number' => 'REG-' . uniqid(),
            'tax_number'          => 'TRN-100200300400500', // will be encrypted by cast
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
            'last_name'  => 'Subject',
            'email'      => 'sub-' . uniqid() . '@t.test',
            'password'   => 'secret-pass',
            'role'       => $role,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. Encryption at rest
    // ─────────────────────────────────────────────────────────────────

    public function test_company_tax_number_is_encrypted_at_rest_but_transparent_through_model(): void
    {
        $company = $this->makeCompany('Encryption Co');

        // Read via the cast — plaintext
        $this->assertSame('TRN-100200300400500', $company->fresh()->tax_number);

        // Read via raw query — should NOT be plaintext
        $raw = DB::table('companies')->where('id', $company->id)->value('tax_number');
        $this->assertNotSame('TRN-100200300400500', $raw);
        $this->assertNotEmpty($raw);

        // Verify the raw value is actually decryptable Laravel ciphertext
        $this->assertSame('TRN-100200300400500', Crypt::decryptString($raw));
    }

    public function test_beneficial_owner_id_number_and_dob_are_encrypted(): void
    {
        $company = $this->makeCompany('BO Co');

        $bo = \App\Models\BeneficialOwner::create([
            'company_id'           => $company->id,
            'full_name'            => 'Test Owner',
            'nationality'          => 'AE',
            'date_of_birth'        => '1985-06-15',
            'id_type'              => 'emirates_id',
            'id_number'            => '784-1985-1234567-1',
            'ownership_percentage' => 75.0,
            'is_pep'               => false,
            'source_of_wealth'     => 'Inherited family business',
        ]);

        $reloaded = $bo->fresh();
        $this->assertSame('784-1985-1234567-1', $reloaded->id_number);
        $this->assertSame('Inherited family business', $reloaded->source_of_wealth);
        $this->assertSame('1985-06-15', $reloaded->dob?->format('Y-m-d'));

        // Raw column read returns ciphertext
        $rawId = DB::table('beneficial_owners')->where('id', $bo->id)->value('id_number');
        $this->assertNotSame('784-1985-1234567-1', $rawId);
        $this->assertSame('784-1985-1234567-1', Crypt::decryptString($rawId));
    }

    public function test_company_bank_detail_iban_and_holder_are_encrypted(): void
    {
        $company = $this->makeCompany('Bank Co');

        $bd = \App\Models\CompanyBankDetail::create([
            'company_id'  => $company->id,
            'holder_name' => 'Sensitive Holder',
            'bank_name'   => 'Mashreq',
            'branch'      => 'DIFC',
            'iban'        => 'AE070331234567890123456',
            'swift'       => 'BOMLAEAD',
            'currency'    => 'AED',
        ]);

        $this->assertSame('AE070331234567890123456', $bd->fresh()->iban);
        $this->assertSame('Sensitive Holder', $bd->fresh()->holder_name);

        $rawIban = DB::table('company_bank_details')->where('id', $bd->id)->value('iban');
        $this->assertNotSame('AE070331234567890123456', $rawIban);
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Consent ledger
    // ─────────────────────────────────────────────────────────────────

    public function test_consent_ledger_grants_and_records_capture_context(): void
    {
        $company = $this->makeCompany('Consent Co');
        $user    = $this->makeUser($company);
        $ledger  = $this->app->make(ConsentLedger::class);

        $consent = $ledger->grant($user, Consent::TYPE_MARKETING_EMAIL, '1.0');

        $this->assertNotNull($consent->granted_at);
        $this->assertNull($consent->withdrawn_at);
        $this->assertSame('marketing_email', $consent->consent_type);
        $this->assertSame('1.0', $consent->version);
        $this->assertTrue($ledger->hasActive($user, Consent::TYPE_MARKETING_EMAIL));
    }

    public function test_consent_withdrawal_stamps_existing_grant_and_inserts_marker(): void
    {
        $company = $this->makeCompany('Withdraw Co');
        $user    = $this->makeUser($company);
        $ledger  = $this->app->make(ConsentLedger::class);

        $original = $ledger->grant($user, Consent::TYPE_MARKETING_EMAIL, '1.0');
        $this->assertTrue($ledger->hasActive($user, Consent::TYPE_MARKETING_EMAIL));

        $affected = $ledger->withdraw($user, Consent::TYPE_MARKETING_EMAIL);
        $this->assertSame(1, $affected);

        // Original row now has withdrawn_at stamped
        $this->assertNotNull($original->fresh()->withdrawn_at);

        // A marker row was inserted with version 'withdrawal'
        $marker = Consent::where('user_id', $user->id)
            ->where('version', 'withdrawal')
            ->first();
        $this->assertNotNull($marker);
        $this->assertNotNull($marker->withdrawn_at);
        $this->assertNull($marker->granted_at);

        // hasActive now returns false
        $this->assertFalse($ledger->hasActive($user, Consent::TYPE_MARKETING_EMAIL));
    }

    public function test_consent_ledger_rejects_unknown_type(): void
    {
        $company = $this->makeCompany('Bad Type Co');
        $user    = $this->makeUser($company);
        $ledger  = $this->app->make(ConsentLedger::class);

        $this->expectException(\InvalidArgumentException::class);
        $ledger->grant($user, 'made_up_consent', '1.0');
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. Data Export Service (DSAR)
    // ─────────────────────────────────────────────────────────────────

    public function test_data_export_builds_archive_with_all_sections(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany('Export Co');
        $user    = $this->makeUser($company);

        $ledger = $this->app->make(ConsentLedger::class);
        $ledger->grant($user, Consent::TYPE_PRIVACY_POLICY, '1.0');

        $service = $this->app->make(DataExportService::class);
        $path = $service->buildArchive($user);

        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);

        // Open the archive and inspect contents
        $absolute = Storage::disk('local')->path($path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($absolute) === true);

        $expectedFiles = ['index.json', 'profile.json', 'company.json', 'consents.json', 'privacy_requests.json', 'audit_logs.json'];
        foreach ($expectedFiles as $name) {
            $this->assertNotFalse($zip->locateName($name), "Archive missing $name");
        }

        $profile = json_decode($zip->getFromName('profile.json'), true);
        $this->assertSame($user->id, $profile['id']);
        $this->assertSame($user->email, $profile['email']);

        $consents = json_decode($zip->getFromName('consents.json'), true);
        $this->assertCount(1, $consents);
        $this->assertSame('privacy_policy', $consents[0]['consent_type']);

        $zip->close();
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. Data Erasure Service
    // ─────────────────────────────────────────────────────────────────

    public function test_erasure_blocked_by_active_contract(): void
    {
        $buyerCompany = $this->makeCompany('Active Buyer');
        $buyer        = $this->makeUser($buyerCompany);

        Contract::create([
            'title'             => 'Active Contract',
            'buyer_company_id'  => $buyerCompany->id,
            'status'            => ContractStatus::ACTIVE,
            'total_amount'      => 100000,
            'currency'          => 'AED',
            'parties'           => [],
        ]);

        $service = $this->app->make(DataErasureService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/active contracts/');
        $service->scheduleErasure($buyer);
    }

    public function test_erasure_schedules_with_cooling_period_when_no_blockers(): void
    {
        $company = $this->makeCompany('Clean Buyer');
        $user    = $this->makeUser($company);

        $service = $this->app->make(DataErasureService::class);
        $request = $service->scheduleErasure($user);

        $this->assertSame(PrivacyRequest::TYPE_ERASURE, $request->request_type);
        $this->assertSame(PrivacyRequest::STATUS_PENDING, $request->status);
        $this->assertNotNull($request->scheduled_for);
        $this->assertGreaterThanOrEqual(29, now()->diffInDays($request->scheduled_for, false));
    }

    public function test_erasure_execution_anonymises_user_in_place(): void
    {
        $company = $this->makeCompany('Erase Buyer');
        $user    = $this->makeUser($company);
        $originalEmail = $user->email;
        $userId = $user->id;

        $service = $this->app->make(DataErasureService::class);
        $request = $service->scheduleErasure($user);

        $service->executeErasure($request);

        $reloaded = User::find($userId);
        $this->assertSame('Anonymised', $reloaded->first_name);
        $this->assertSame('User', $reloaded->last_name);
        $this->assertNotSame($originalEmail, $reloaded->email);
        $this->assertStringContainsString('@deleted.local', $reloaded->email);

        $this->assertSame(PrivacyRequest::STATUS_COMPLETED, $request->fresh()->status);
    }

    public function test_user_can_cancel_pending_erasure(): void
    {
        $company = $this->makeCompany('Cancel Buyer');
        $user    = $this->makeUser($company);

        $service = $this->app->make(DataErasureService::class);
        $request = $service->scheduleErasure($user);

        $cancelled = $service->cancel($request);

        $this->assertSame(PrivacyRequest::STATUS_WITHDRAWN, $cancelled->status);
        // User should NOT be anonymised
        $this->assertNotSame('Anonymised', $user->fresh()->first_name);
    }

    // ─────────────────────────────────────────────────────────────────
    //  5. Public + dashboard routes
    // ─────────────────────────────────────────────────────────────────

    public function test_privacy_policy_page_renders_publicly(): void
    {
        $this->get(route('public.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy');
    }

    public function test_dpa_page_renders_publicly(): void
    {
        $this->get(route('public.dpa'))
            ->assertOk();
    }

    public function test_cookie_consent_post_records_session_marker_for_guests(): void
    {
        $response = $this->post(route('public.privacy.cookies'), ['analytics' => 1]);
        // Should redirect (back) without an error
        $this->assertContains($response->status(), [302, 301]);
        $this->assertTrue(session()->get('cookie_consent_recorded'));
    }

    public function test_cookie_consent_post_records_consents_for_logged_in_user(): void
    {
        $company = $this->makeCompany('Cookie Co');
        $user    = $this->makeUser($company);

        $this->actingAs($user)
            ->post(route('public.privacy.cookies'), ['analytics' => 1]);

        $this->assertDatabaseHas('consents', [
            'user_id'      => $user->id,
            'consent_type' => Consent::TYPE_COOKIES_ESSENTIAL,
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id'      => $user->id,
            'consent_type' => Consent::TYPE_COOKIES_ANALYTICS,
        ]);
    }

    public function test_dashboard_privacy_hub_requires_auth(): void
    {
        $this->get(route('dashboard.privacy.index'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_privacy_hub_renders_for_authenticated_user(): void
    {
        $company = $this->makeCompany('Hub Co');
        $user    = $this->makeUser($company);

        $this->actingAs($user)
            ->get(route('dashboard.privacy.index'))
            ->assertOk()
            ->assertSee('Privacy & Data');
    }

    public function test_dashboard_export_request_creates_completed_record(): void
    {
        $company = $this->makeCompany('Export Hub Co');
        $user    = $this->makeUser($company);

        $this->actingAs($user)
            ->post(route('dashboard.privacy.export'))
            ->assertRedirect();

        $request = PrivacyRequest::where('user_id', $user->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(PrivacyRequest::TYPE_DATA_EXPORT, $request->request_type);
        $this->assertSame(PrivacyRequest::STATUS_COMPLETED, $request->status);
        $this->assertArrayHasKey('archive_path', $request->fulfillment_metadata);
    }

    public function test_dashboard_erasure_request_dispatches_delayed_job(): void
    {
        Bus::fake([ExecutePrivacyErasureJob::class]);

        $company = $this->makeCompany('Erasure Hub Co');
        $user    = $this->makeUser($company);

        $this->actingAs($user)
            ->post(route('dashboard.privacy.erasure'))
            ->assertRedirect();

        Bus::assertDispatched(ExecutePrivacyErasureJob::class);
        $this->assertDatabaseHas('privacy_requests', [
            'user_id'      => $user->id,
            'request_type' => PrivacyRequest::TYPE_ERASURE,
            'status'       => PrivacyRequest::STATUS_PENDING,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  6. Data breach notification command
    // ─────────────────────────────────────────────────────────────────

    public function test_report_breach_command_notifies_admins(): void
    {
        Notification::fake();

        // Need at least one admin to receive
        $adminCompany = $this->makeCompany('Admin Co');
        $admin = $this->makeUser($adminCompany, UserRole::ADMIN);

        $exitCode = $this->artisan('privacy:report-breach', [
            '--severity'    => 'high',
            '--affected'    => 42,
            '--description' => 'Test breach for unit tests',
            '--detection'   => 'audit_log',
            '--reporter'    => 'unit-test',
        ])->run();

        $this->assertSame(0, $exitCode);
        Notification::assertSentTo($admin, DataBreachNotification::class);
    }

    public function test_report_breach_command_rejects_unknown_severity(): void
    {
        $exitCode = $this->artisan('privacy:report-breach', [
            '--severity'    => 'extreme',
            '--description' => 'Test',
        ])->run();

        $this->assertSame(1, $exitCode);
    }
}
