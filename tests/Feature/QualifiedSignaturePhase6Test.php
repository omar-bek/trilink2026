<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DocumentType;
use App\Enums\SignatureGrade;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractService;
use App\Services\Signing\MockTspProvider;
use App\Services\Signing\SignatureGradeResolver;
use App\Services\Signing\UaePassProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 (UAE Compliance Roadmap) — Qualified e-Signature & UAE Pass
 * regression suite. Covers:
 *
 *   1. SignatureGrade enum semantics + ranking
 *   2. SignatureGradeResolver picks the right grade per contract type
 *   3. ContractService::sign refuses weak signatures + persists grade
 *   4. UAE Pass provider gating (enabled/disabled)
 *   5. MockTspProvider sign + verify roundtrip
 *   6. Public /contracts/{id}/verify endpoint detects tamper + grade
 */
class QualifiedSignaturePhase6Test extends TestCase
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

    private function attachLicense(Company $company): void
    {
        CompanyDocument::create([
            'company_id' => $company->id,
            'type'       => DocumentType::TRADE_LICENSE,
            'label'      => 'License',
            'file_path'  => 'test/license.pdf',
            'status'     => CompanyDocument::STATUS_VERIFIED,
            'expires_at' => now()->addYear(),
        ]);
    }

    private function makeContract(
        Company $buyer,
        Company $supplier,
        string $title = 'Test Contract',
        float $amount = 100000
    ): Contract {
        return Contract::create([
            'title'             => $title,
            'buyer_company_id'  => $buyer->id,
            'status'            => ContractStatus::PENDING_SIGNATURES,
            'total_amount'      => $amount,
            'currency'          => 'AED',
            'parties'           => [['company_id' => $supplier->id, 'role' => 'supplier']],
            'version'           => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. Enum semantics
    // ─────────────────────────────────────────────────────────────────

    public function test_signature_grade_satisfies_returns_true_for_higher_grades(): void
    {
        $this->assertTrue(SignatureGrade::QUALIFIED->satisfies(SignatureGrade::SIMPLE));
        $this->assertTrue(SignatureGrade::QUALIFIED->satisfies(SignatureGrade::ADVANCED));
        $this->assertTrue(SignatureGrade::QUALIFIED->satisfies(SignatureGrade::QUALIFIED));
        $this->assertTrue(SignatureGrade::ADVANCED->satisfies(SignatureGrade::SIMPLE));
        $this->assertTrue(SignatureGrade::ADVANCED->satisfies(SignatureGrade::ADVANCED));
        $this->assertTrue(SignatureGrade::SIMPLE->satisfies(SignatureGrade::SIMPLE));

        $this->assertFalse(SignatureGrade::SIMPLE->satisfies(SignatureGrade::ADVANCED));
        $this->assertFalse(SignatureGrade::SIMPLE->satisfies(SignatureGrade::QUALIFIED));
        $this->assertFalse(SignatureGrade::ADVANCED->satisfies(SignatureGrade::QUALIFIED));
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Grade resolver
    // ─────────────────────────────────────────────────────────────────

    public function test_resolver_returns_simple_for_ordinary_b2b_contract(): void
    {
        $resolver = $this->app->make(SignatureGradeResolver::class);
        $buyer = $this->makeCompany('B2B Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('B2B Supplier', CompanyType::SUPPLIER);
        $contract = $this->makeContract($buyer, $supplier, 'Office supplies', 50000);

        $this->assertSame(SignatureGrade::SIMPLE, $resolver->requiredFor($contract));
    }

    public function test_resolver_returns_advanced_for_high_value_contract(): void
    {
        $resolver = $this->app->make(SignatureGradeResolver::class);
        $buyer = $this->makeCompany('HV Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('HV Supplier', CompanyType::SUPPLIER);
        $contract = $this->makeContract($buyer, $supplier, 'Construction works', 750_000);

        $this->assertSame(SignatureGrade::ADVANCED, $resolver->requiredFor($contract));
    }

    public function test_resolver_returns_qualified_for_government_buyer(): void
    {
        $resolver = $this->app->make(SignatureGradeResolver::class);
        $govBuyer = $this->makeCompany('Gov Department', CompanyType::GOVERNMENT);
        $supplier = $this->makeCompany('Gov Supplier', CompanyType::SUPPLIER);
        $contract = $this->makeContract($govBuyer, $supplier, 'Office supplies', 25_000);

        $this->assertSame(SignatureGrade::QUALIFIED, $resolver->requiredFor($contract));
    }

    public function test_resolver_returns_qualified_for_real_estate_keyword(): void
    {
        $resolver = $this->app->make(SignatureGradeResolver::class);
        $buyer = $this->makeCompany('RE Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('RE Supplier', CompanyType::SUPPLIER);
        $contract = $this->makeContract($buyer, $supplier, 'Lease agreement for warehouse', 30_000);

        $this->assertSame(SignatureGrade::QUALIFIED, $resolver->requiredFor($contract));
    }

    public function test_resolver_reason_string_explains_the_decision(): void
    {
        $resolver = $this->app->make(SignatureGradeResolver::class);
        $govBuyer = $this->makeCompany('Gov X', CompanyType::GOVERNMENT);
        $supplier = $this->makeCompany('Sup Y', CompanyType::SUPPLIER);
        $contract = $this->makeContract($govBuyer, $supplier);

        $reason = $resolver->reasonFor($contract);
        $this->assertStringContainsString('Government', $reason);
        $this->assertStringContainsString('Article 19', $reason);
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. ContractService grade-aware sign
    // ─────────────────────────────────────────────────────────────────

    public function test_simple_signature_on_high_value_contract_is_refused(): void
    {
        $service = $this->app->make(ContractService::class);
        $buyer = $this->makeCompany('Refuse Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Refuse Supplier', CompanyType::SUPPLIER);
        $this->attachLicense($buyer);
        $this->attachLicense($supplier);
        $supplierUser = $this->makeUser($supplier, UserRole::SUPPLIER);

        $contract = $this->makeContract($buyer, $supplier, 'Big project', 1_000_000);

        // Default audit context = simple grade. Should be refused.
        $result = $service->sign($contract->id, $supplierUser->id, $supplier->id, null, [
            'ip_address' => '1.2.3.4',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('Advanced', $result);
        // Contract should still have no signatures
        $this->assertEmpty($contract->fresh()->signatures ?? []);
    }

    public function test_advanced_signature_on_high_value_contract_is_accepted(): void
    {
        $service = $this->app->make(ContractService::class);
        $buyer = $this->makeCompany('Adv Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Adv Supplier', CompanyType::SUPPLIER);
        $this->attachLicense($buyer);
        $this->attachLicense($supplier);
        $supplierUser = $this->makeUser($supplier, UserRole::SUPPLIER);

        $contract = $this->makeContract($buyer, $supplier, 'Big project', 1_000_000);

        $result = $service->sign($contract->id, $supplierUser->id, $supplier->id, null, [
            'ip_address'         => '1.2.3.4',
            'signature_grade'    => 'advanced',
            'uae_pass_user_id'   => 'UP-XYZ',
            'uae_pass_full_name' => 'Test User',
        ]);

        $this->assertNotSame('string', gettype($result));
        $this->assertInstanceOf(Contract::class, $result);
        $contract->refresh();
        $this->assertCount(1, $contract->signatures);
        $sig = $contract->signatures[0];
        $this->assertSame('advanced', $sig['signature_grade']);
        $this->assertSame('UP-XYZ', $sig['uae_pass_user_id']);
        // The cached grade-required is now stamped on the row.
        $this->assertSame('advanced', $contract->signature_grade_required);
    }

    public function test_simple_signature_on_ordinary_b2b_contract_is_accepted(): void
    {
        $service = $this->app->make(ContractService::class);
        $buyer = $this->makeCompany('Easy Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Easy Supplier', CompanyType::SUPPLIER);
        $this->attachLicense($buyer);
        $this->attachLicense($supplier);
        $supplierUser = $this->makeUser($supplier, UserRole::SUPPLIER);

        $contract = $this->makeContract($buyer, $supplier, 'Stationery', 5_000);

        $result = $service->sign($contract->id, $supplierUser->id, $supplier->id, null, [
            'ip_address' => '1.2.3.4',
        ]);

        $this->assertNotSame('string', gettype($result));
        $this->assertInstanceOf(Contract::class, $result);
        $contract->refresh();
        $this->assertCount(1, $contract->signatures);
        $this->assertSame('simple', $contract->signatures[0]['signature_grade']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. UAE Pass provider gating
    // ─────────────────────────────────────────────────────────────────

    public function test_uae_pass_provider_is_disabled_by_default(): void
    {
        config()->set('uae_pass.enabled', false);
        $provider = $this->app->make(UaePassProvider::class);
        $this->assertFalse($provider->isEnabled());
    }

    public function test_uae_pass_provider_throws_on_authorization_when_disabled(): void
    {
        config()->set('uae_pass.enabled', false);
        $provider = $this->app->make(UaePassProvider::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/disabled/');
        $provider->buildAuthorizationUrl('1', 'state-x');
    }

    public function test_uae_pass_provider_builds_authorization_url_when_enabled(): void
    {
        config()->set('uae_pass.enabled', true);
        config()->set('uae_pass.environment', 'sandbox');
        config()->set('uae_pass.client_id', 'test-client');
        config()->set('uae_pass.client_secret', 'test-secret');
        config()->set('uae_pass.redirect_uri', 'https://example.com/cb');
        $provider = $this->app->make(UaePassProvider::class);

        $url = $provider->buildAuthorizationUrl('1', 'state-abc');

        $this->assertStringContainsString('stg-id.uaepass.ae', $url);
        $this->assertStringContainsString('client_id=test-client', $url);
        $this->assertStringContainsString('state=state-abc', $url);
        $this->assertStringContainsString('response_type=code', $url);
    }

    // ─────────────────────────────────────────────────────────────────
    //  5. Mock TSP roundtrip
    // ─────────────────────────────────────────────────────────────────

    public function test_mock_tsp_signs_and_verifies_roundtrip(): void
    {
        $tsp = $this->app->make(MockTspProvider::class);
        $contractHash = hash('sha256', 'test-contract-bytes');
        $signerContext = ['user_id' => 1, 'company_id' => 2];

        $envelope = $tsp->signHash($contractHash, $signerContext);

        $this->assertSame('mock', $envelope['tsp_provider']);
        $this->assertSame('CAdES', $envelope['signature_format']);
        $this->assertNotEmpty($envelope['signature_payload']);
        $this->assertNotEmpty($envelope['timestamp_token']);

        // Verify with the same signer context
        $verifyEnvelope = array_merge($envelope, [
            'signer_user_id'    => 1,
            'signer_company_id' => 2,
        ]);
        $result = $tsp->verify($contractHash, $verifyEnvelope);
        $this->assertTrue($result['valid']);
    }

    public function test_mock_tsp_verify_fails_on_modified_hash(): void
    {
        $tsp = $this->app->make(MockTspProvider::class);
        $envelope = $tsp->signHash(hash('sha256', 'original'), ['user_id' => 1, 'company_id' => 2]);

        $verifyEnvelope = array_merge($envelope, [
            'signer_user_id'    => 1,
            'signer_company_id' => 2,
        ]);
        $result = $tsp->verify(hash('sha256', 'tampered'), $verifyEnvelope);

        $this->assertFalse($result['valid']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  6. Public verify endpoint
    // ─────────────────────────────────────────────────────────────────

    public function test_public_verify_page_renders_for_unsigned_contract(): void
    {
        $buyer = $this->makeCompany('Verify Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Verify Supplier', CompanyType::SUPPLIER);
        $contract = $this->makeContract($buyer, $supplier);

        $this->get(route('public.contracts.verify', ['id' => $contract->id]))
            ->assertOk()
            ->assertSee('Signature Verification')
            ->assertSee('No signatures');
    }

    public function test_public_verify_detects_tampered_contract(): void
    {
        $service = $this->app->make(ContractService::class);
        $buyer = $this->makeCompany('Tamper Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Tamper Supplier', CompanyType::SUPPLIER);
        $this->attachLicense($buyer);
        $this->attachLicense($supplier);
        $supplierUser = $this->makeUser($supplier, UserRole::SUPPLIER);

        $contract = $this->makeContract($buyer, $supplier, 'Original title', 5000);

        // Sign with simple grade
        $service->sign($contract->id, $supplierUser->id, $supplier->id, null, [
            'ip_address' => '10.0.0.1',
        ]);

        // Tamper: change the title AFTER signing
        $contract->refresh();
        $contract->update(['title' => 'EVIL MODIFIED title']);

        $response = $this->get(route('public.contracts.verify', ['id' => $contract->id]));
        $response->assertOk();
        $response->assertSee('BROKEN');
    }

    public function test_public_verify_endpoint_requires_no_authentication(): void
    {
        $buyer = $this->makeCompany('Open Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Open Supplier', CompanyType::SUPPLIER);
        $contract = $this->makeContract($buyer, $supplier);

        // No actingAs() — anonymous request
        $this->get(route('public.contracts.verify', ['id' => $contract->id]))
            ->assertOk();
    }
}
