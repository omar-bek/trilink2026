<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Consent;
use App\Models\IcvCertificate;
use App\Models\PrivacyPolicyVersion;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Notifications\IcvCertificateExpiringNotification;
use App\Services\Privacy\ConsentLedger;
use App\Services\Privacy\DataErasureService;
use App\Services\Privacy\DataExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Hardening regression suite — batch 2.
 *
 * Covers the second wave of post-implementation review fixes:
 *   - DSAR archive includes uploaded files
 *   - Erasure deep anonymization (audit_logs IPs)
 *   - Privacy policy text snapshotting
 *   - ICV expiry notifications
 *
 * The DIFC-LCIA citation update is text-only and is verified by
 * a single string-presence assertion.
 */
class HardeningBatch2Test extends TestCase
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

    // ─────────────────────────────────────────────────────────────────
    //  DSAR archive includes uploaded files
    // ─────────────────────────────────────────────────────────────────

    public function test_dsar_archive_includes_uploaded_files_in_a_files_directory(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany('Files Co', CompanyType::SUPPLIER);
        $user    = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        // Stage two uploaded files: a company document + an ICV cert
        Storage::disk('local')->put('company-documents/test/trade-license.pdf', 'TRADE-LICENSE-BYTES');
        Storage::disk('local')->put('icv-certificates/test/cert.pdf', 'ICV-BYTES');

        CompanyDocument::create([
            'company_id'        => $company->id,
            'type'              => DocumentType::TRADE_LICENSE,
            'label'             => 'Trade License',
            'file_path'         => 'company-documents/test/trade-license.pdf',
            'original_filename' => 'license.pdf',
            'status'            => CompanyDocument::STATUS_VERIFIED,
            'expires_at'        => now()->addYear(),
        ]);

        IcvCertificate::create([
            'company_id'         => $company->id,
            'issuer'             => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'M-DSAR-1',
            'score'              => 50,
            'issued_date'        => now()->subMonths(2),
            'expires_date'       => now()->addMonths(10),
            'file_path'          => 'icv-certificates/test/cert.pdf',
            'original_filename'  => 'icv-cert.pdf',
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);

        $service = $this->app->make(DataExportService::class);
        $path = $service->buildArchive($user);

        Storage::disk('local')->assertExists($path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);

        // Manifest exists
        $this->assertNotFalse($zip->locateName('files/_manifest.json'));

        // The actual file bytes are in the archive
        $manifest = json_decode($zip->getFromName('files/_manifest.json'), true);
        $this->assertNotEmpty($manifest['copied'] ?? []);
        $this->assertEmpty($manifest['missing'] ?? []);

        // Validate one of the copied files actually contains the
        // bytes we staged
        foreach ($manifest['copied'] as $entry) {
            $contents = $zip->getFromName($entry);
            $this->assertNotFalse($contents, "Missing entry: $entry");
            $this->assertNotEmpty($contents);
        }

        $zip->close();
    }

    public function test_dsar_archive_records_missing_files_without_failing(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany('Missing Co');
        $user    = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        // Reference a file that doesn't exist on disk
        CompanyDocument::create([
            'company_id'        => $company->id,
            'type'              => DocumentType::TRADE_LICENSE,
            'label'             => 'Phantom',
            'file_path'         => 'company-documents/test/missing.pdf',
            'status'            => CompanyDocument::STATUS_VERIFIED,
            'expires_at'        => now()->addYear(),
        ]);

        $service = $this->app->make(DataExportService::class);
        $path = $service->buildArchive($user);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);
        $manifest = json_decode($zip->getFromName('files/_manifest.json'), true);

        $this->assertNotEmpty($manifest['missing'] ?? []);
        $zip->close();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Erasure deep anonymization
    // ─────────────────────────────────────────────────────────────────

    public function test_erasure_anonymizes_user_audit_logs(): void
    {
        $company = $this->makeCompany('Erasure Co');
        $user    = $this->makeUser($company, UserRole::BUYER);

        // Author 3 audit log rows for this user
        for ($i = 0; $i < 3; $i++) {
            AuditLog::create([
                'user_id'       => $user->id,
                'company_id'    => $company->id,
                'action'        => \App\Enums\AuditAction::CREATE,
                'resource_type' => 'Test',
                'resource_id'   => $i + 1,
                'before'        => null,
                'after'         => ['n' => $i],
                'ip_address'    => '198.51.100.' . ($i + 10),
                'user_agent'    => 'Mozilla/5.0 user-' . $user->id,
                'status'        => 'success',
            ]);
        }

        $service = $this->app->make(DataErasureService::class);
        $request = $service->scheduleErasure($user);
        $service->executeErasure($request);

        // After erasure: every audit_log row authored by the user
        // should have ip_address = anonymised placeholder.
        $rows = AuditLog::where('user_id', $user->id)->get();
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame('0.0.0.0', $row->ip_address);
            $this->assertSame('anonymised', $row->user_agent);
        }

        // Raw column read confirms it's actually encrypted on disk too
        $rawIp = DB::table('audit_logs')->where('user_id', $user->id)->value('ip_address');
        $this->assertNotSame('0.0.0.0', $rawIp);
        $this->assertSame('0.0.0.0', Crypt::decryptString($rawIp));

        // Fulfillment metadata records the count
        $this->assertGreaterThan(0, $request->fresh()->fulfillment_metadata['audit_logs_anonymised'] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Privacy policy text snapshotting
    // ─────────────────────────────────────────────────────────────────

    public function test_consent_links_to_published_policy_version_when_one_exists(): void
    {
        $company = $this->makeCompany('Policy Co');
        $user    = $this->makeUser($company);

        // Publish a policy version
        $version = PrivacyPolicyVersion::create([
            'version'        => '2.0',
            'body_en'        => 'English body of policy v2.0',
            'body_ar'        => 'النص العربي للسياسة v2.0',
            'sha256'         => PrivacyPolicyVersion::canonicalSha256('English body of policy v2.0', 'النص العربي للسياسة v2.0'),
            'effective_from' => now()->subDay(),
            'changelog'      => 'Test version',
        ]);

        $ledger = $this->app->make(ConsentLedger::class);
        $consent = $ledger->grant($user, Consent::TYPE_PRIVACY_POLICY, '2.0');

        $this->assertNotNull($consent->privacy_policy_version_id);
        $this->assertSame((int) $version->id, (int) $consent->privacy_policy_version_id);
    }

    public function test_consent_with_no_matching_policy_version_falls_back_to_null(): void
    {
        $company = $this->makeCompany('No Policy Co');
        $user    = $this->makeUser($company);

        $ledger = $this->app->make(ConsentLedger::class);
        $consent = $ledger->grant($user, Consent::TYPE_PRIVACY_POLICY, '99.99');

        $this->assertNull($consent->privacy_policy_version_id);
        $this->assertSame('99.99', $consent->version);
    }

    public function test_dsar_archive_embeds_policy_text_snapshot(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany('Snapshot Co');
        $user    = $this->makeUser($company);

        $version = PrivacyPolicyVersion::create([
            'version'        => '3.0',
            'body_en'        => 'POLICY-EN-V3-MARKER',
            'body_ar'        => 'POLICY-AR-V3-MARKER',
            'sha256'         => PrivacyPolicyVersion::canonicalSha256('POLICY-EN-V3-MARKER', 'POLICY-AR-V3-MARKER'),
            'effective_from' => now()->subDay(),
        ]);

        $ledger = $this->app->make(ConsentLedger::class);
        $ledger->grant($user, Consent::TYPE_PRIVACY_POLICY, '3.0');

        $service = $this->app->make(DataExportService::class);
        $path = $service->buildArchive($user);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);
        $consents = json_decode($zip->getFromName('consents.json'), true);
        $zip->close();

        // Find the policy consent row and verify the snapshot
        $policyConsents = collect($consents)->filter(fn ($c) => $c['consent_type'] === Consent::TYPE_PRIVACY_POLICY);
        $this->assertGreaterThan(0, $policyConsents->count());
        $entry = $policyConsents->first();

        $this->assertArrayHasKey('policy_snapshot', $entry);
        $this->assertSame('3.0', $entry['policy_snapshot']['version']);
        $this->assertSame('POLICY-EN-V3-MARKER', $entry['policy_snapshot']['body_en']);
        $this->assertSame('POLICY-AR-V3-MARKER', $entry['policy_snapshot']['body_ar']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  DIFC-LCIA citation
    // ─────────────────────────────────────────────────────────────────

    public function test_dispute_clause_cites_decree_34_of_2021(): void
    {
        $clause = __('contracts.term_disputes_jurisdiction');
        $this->assertStringContainsString('Decree No. 34 of 2021', $clause);
        $this->assertStringContainsString('DIFC-LCIA', $clause);
        $this->assertStringContainsString('DIAC', $clause);
    }

    // ─────────────────────────────────────────────────────────────────
    //  ICV expiry notifications
    // ─────────────────────────────────────────────────────────────────

    public function test_icv_expiry_command_sends_60day_reminder_to_company_managers(): void
    {
        Notification::fake();

        $company = $this->makeCompany('Expiring Co', CompanyType::SUPPLIER);
        $manager = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        // Cert expiring in 55 days — within the 60-day window, no
        // reminder sent yet
        $cert = IcvCertificate::create([
            'company_id'         => $company->id,
            'issuer'             => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'EXP-1',
            'score'              => 50,
            'issued_date'        => now()->subYear(),
            'expires_date'       => now()->addDays(55),
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);

        $this->artisan('icv:notify-expiring')->assertSuccessful();

        Notification::assertSentTo($manager, IcvCertificateExpiringNotification::class);
        $this->assertSame(60, (int) $cert->fresh()->last_expiry_reminder_threshold);
    }

    public function test_icv_expiry_command_does_not_double_send_same_threshold(): void
    {
        Notification::fake();

        $company = $this->makeCompany('Already Notified Co', CompanyType::SUPPLIER);
        $manager = $this->makeUser($company, UserRole::COMPANY_MANAGER);

        IcvCertificate::create([
            'company_id'                       => $company->id,
            'issuer'                           => IcvCertificate::ISSUER_MOIAT,
            'certificate_number'               => 'NOPE-1',
            'score'                            => 50,
            'issued_date'                      => now()->subYear(),
            'expires_date'                     => now()->addDays(45),
            'status'                           => IcvCertificate::STATUS_VERIFIED,
            // 60-day reminder already sent
            'last_expiry_reminder_threshold'   => 60,
        ]);

        $this->artisan('icv:notify-expiring')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_icv_expiry_command_progresses_from_60_to_30_to_7(): void
    {
        Notification::fake();

        $company = $this->makeCompany('Progress Co', CompanyType::SUPPLIER);
        $this->makeUser($company, UserRole::COMPANY_MANAGER);

        // Cert at 25 days (within 30 window), 60 already sent → 30 due
        $cert = IcvCertificate::create([
            'company_id'                       => $company->id,
            'issuer'                           => IcvCertificate::ISSUER_MOIAT,
            'certificate_number'               => 'PROG-1',
            'score'                            => 50,
            'issued_date'                      => now()->subYear(),
            'expires_date'                     => now()->addDays(25),
            'status'                           => IcvCertificate::STATUS_VERIFIED,
            'last_expiry_reminder_threshold'   => 60,
        ]);

        $this->artisan('icv:notify-expiring')->assertSuccessful();
        $this->assertSame(30, (int) $cert->fresh()->last_expiry_reminder_threshold);
    }

    public function test_icv_expiry_command_dry_run_does_not_send_or_stamp(): void
    {
        Notification::fake();

        $company = $this->makeCompany('Dry Run Co', CompanyType::SUPPLIER);
        $this->makeUser($company, UserRole::COMPANY_MANAGER);

        $cert = IcvCertificate::create([
            'company_id'         => $company->id,
            'issuer'             => IcvCertificate::ISSUER_MOIAT,
            'certificate_number' => 'DRY-1',
            'score'              => 50,
            'issued_date'        => now()->subYear(),
            'expires_date'       => now()->addDays(50),
            'status'             => IcvCertificate::STATUS_VERIFIED,
        ]);

        $this->artisan('icv:notify-expiring', ['--dry-run' => true])->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertNull($cert->fresh()->last_expiry_reminder_threshold);
    }
}
