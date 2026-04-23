<?php

namespace Tests\Feature;

use App\Enums\BankGuaranteeStatus;
use App\Enums\BankGuaranteeType;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BankGuarantee;
use App\Models\Company;
use App\Models\User;
use App\Services\BankGuaranteeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankGuaranteeServiceTest extends TestCase
{
    use RefreshDatabase;

    private BankGuaranteeService $service;
    private Company $applicant;
    private Company $beneficiary;
    private User $applicantUser;
    private User $beneficiaryUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BankGuaranteeService::class);
        $this->applicant = $this->company('Supplier Co', CompanyType::SUPPLIER);
        $this->beneficiary = $this->company('Buyer Co', CompanyType::BUYER);
        $this->applicantUser = $this->user($this->applicant);
        $this->beneficiaryUser = $this->user($this->beneficiary);
    }

    public function test_register_creates_pending_guarantee_with_generated_number(): void
    {
        $bg = $this->freshBg();

        $this->assertEquals(BankGuaranteeStatus::PENDING_ISSUANCE, $bg->status);
        $this->assertStringStartsWith('PB-', $bg->bg_number);
        $this->assertEquals('100000.00', $bg->amount_remaining);
    }

    public function test_activate_transitions_to_live(): void
    {
        $bg = $this->freshBg();
        $activated = $this->service->activate($bg, $this->beneficiaryUser);

        $this->assertEquals(BankGuaranteeStatus::LIVE, $activated->status);
        $this->assertNotNull($activated->activated_at);
    }

    public function test_call_rejected_by_non_beneficiary(): void
    {
        $bg = $this->freshBg();
        $this->service->activate($bg, $this->beneficiaryUser);

        $this->expectException(\RuntimeException::class);
        $this->service->call($bg->fresh(), $this->applicantUser, 10000, 'test');
    }

    public function test_call_records_claim_and_updates_status(): void
    {
        $bg = $this->freshBg();
        $this->service->activate($bg, $this->beneficiaryUser);

        $call = $this->service->call($bg->fresh(), $this->beneficiaryUser, 25000, 'Non-performance');
        $this->assertEquals(25000, $call->amount);
        $this->assertEquals(BankGuaranteeStatus::CALLED, $bg->fresh()->status);
    }

    public function test_call_exceeding_remaining_liability_throws(): void
    {
        $bg = $this->freshBg();
        $this->service->activate($bg, $this->beneficiaryUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->call($bg->fresh(), $this->beneficiaryUser, 999999, 'too much');
    }

    public function test_reduce_rejects_non_reducible_types(): void
    {
        $bg = $this->freshBg();
        $this->service->activate($bg, $this->beneficiaryUser);

        $this->expectException(\RuntimeException::class);
        $this->service->reduce($bg->fresh(), $this->beneficiaryUser, 10000, 'partial delivery');
    }

    public function test_reduce_advance_payment_bg_decreases_remaining(): void
    {
        $bg = $this->freshBg(['type' => BankGuaranteeType::ADVANCE_PAYMENT->value, 'amount' => 100000]);
        $this->service->activate($bg, $this->beneficiaryUser);

        $reduced = $this->service->reduce($bg->fresh(), $this->beneficiaryUser, 30000, '30% delivered');
        $this->assertEquals('70000.00', $reduced->amount_remaining);
        $this->assertEquals(BankGuaranteeStatus::REDUCED, $reduced->status);
    }

    public function test_extend_rejects_earlier_date(): void
    {
        $bg = $this->freshBg();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->extend($bg, $this->beneficiaryUser, now()->subDays(10)->toDateString());
    }

    public function test_extend_updates_expiry_and_logs_event(): void
    {
        $bg = $this->freshBg();
        $newExpiry = now()->addYears(2)->toDateString();
        $this->service->extend($bg, $this->applicantUser, $newExpiry, 'Buyer asked');

        $this->assertEquals($newExpiry, $bg->fresh()->expiry_date->toDateString());
        $this->assertTrue($bg->events()->where('event', 'extended')->exists());
    }

    public function test_release_marks_returned(): void
    {
        $bg = $this->freshBg();
        $this->service->activate($bg, $this->beneficiaryUser);

        $released = $this->service->release($bg->fresh(), $this->beneficiaryUser, 'Contract complete');
        $this->assertEquals(BankGuaranteeStatus::RETURNED, $released->status);
        $this->assertNotNull($released->returned_at);
    }

    private function company(string $name, CompanyType $type): Company
    {
        return Company::create([
            'name' => $name,
            'registration_number' => 'TRN-'.uniqid(),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'email' => strtolower(preg_replace('/\s+/', '', $name)).'@t.test',
            'city' => 'Dubai',
            'country' => 'UAE',
        ]);
    }

    private function user(Company $company): User
    {
        return User::create([
            'first_name' => 'T', 'last_name' => 'U',
            'email' => 'u-'.uniqid().'@t.test',
            'password' => 'secret-pass',
            'role' => UserRole::BUYER,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    private function freshBg(array $overrides = []): BankGuarantee
    {
        return $this->service->register(array_merge([
            'type' => BankGuaranteeType::PERFORMANCE_BOND->value,
            'applicant_company_id' => $this->applicant->id,
            'beneficiary_company_id' => $this->beneficiary->id,
            'amount' => 100000,
            'currency' => 'AED',
            'validity_start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'issuing_bank_name' => 'Mashreq Bank',
        ], $overrides), $this->applicantUser);
    }
}
