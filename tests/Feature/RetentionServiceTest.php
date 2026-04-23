<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_skim_is_zero_when_contract_has_no_retention_percentage(): void
    {
        [$contract, $payment] = $this->fixtures(retentionPct: null);
        $this->assertEquals('0.00', app(RetentionService::class)->skim($payment));
        $this->assertNull($contract->fresh()->retention_amount);
    }

    public function test_skim_computes_slice_and_accumulates_on_contract(): void
    {
        [$contract, $payment] = $this->fixtures(retentionPct: 10, amount: 10000);
        app(RetentionService::class)->skim($payment);
        $this->assertEquals('1000.00', $contract->fresh()->retention_amount);
    }

    public function test_skim_is_cumulative_across_payments(): void
    {
        [$contract, $payment] = $this->fixtures(retentionPct: 5, amount: 20000);
        $payment2 = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $contract->buyer_company_id,
            'recipient_company_id' => collect($contract->parties)->firstWhere('role', 'supplier')['company_id'],
            'buyer_id' => $payment->buyer_id,
            'status' => PaymentStatus::APPROVED->value,
            'amount' => 10000,
            'currency' => 'AED',
            'milestone' => 'production',
            'vat_rate' => 5,
        ]);

        $r = app(RetentionService::class);
        $r->skim($payment);
        $r->skim($payment2->load('contract'));

        // 5% of (20000 + 10000) = 1500
        $this->assertEquals('1500.00', $contract->fresh()->retention_amount);
    }

    public function test_skim_skips_retention_release_payments(): void
    {
        [$contract, $payment] = $this->fixtures(retentionPct: 10, amount: 10000, milestone: 'retention_release');
        app(RetentionService::class)->skim($payment);
        $this->assertNull($contract->fresh()->retention_amount);
    }

    public function test_release_all_creates_pending_payment_and_stamps_released_at(): void
    {
        [$contract, $payment] = $this->fixtures(retentionPct: 10, amount: 10000);
        app(RetentionService::class)->skim($payment);

        $release = app(RetentionService::class)->releaseAll($contract->fresh());

        $this->assertNotNull($release);
        $this->assertEquals('1000.00', $release->amount);
        $this->assertEquals(PaymentStatus::PENDING_APPROVAL->value, $release->status->value);
        $this->assertEquals('retention_release', $release->milestone);
        $this->assertNotNull($contract->fresh()->retention_released_at);
    }

    public function test_release_all_is_idempotent(): void
    {
        [$contract, $payment] = $this->fixtures(retentionPct: 10, amount: 10000);
        app(RetentionService::class)->skim($payment);

        app(RetentionService::class)->releaseAll($contract->fresh());
        $second = app(RetentionService::class)->releaseAll($contract->fresh());
        $this->assertNull($second);
    }

    public function test_pending_release_lists_contracts_past_release_date(): void
    {
        [$contract] = $this->fixtures(retentionPct: 5, amount: 10000, releaseDate: now()->subDay());
        $pending = app(RetentionService::class)->pendingRelease();
        $this->assertTrue($pending->contains('id', $contract->id));
    }

    /**
     * @return array{0:Contract,1:Payment}
     */
    private function fixtures(
        ?float $retentionPct = 10,
        float $amount = 10000,
        string $milestone = 'delivery',
        ?\DateTimeInterface $releaseDate = null,
    ): array {
        $buyer = $this->company('Buyer', CompanyType::BUYER);
        $supplier = $this->company('Supplier', CompanyType::SUPPLIER);
        $user = $this->user($buyer);

        $contract = Contract::create([
            'title' => 'C-'.uniqid(),
            'buyer_company_id' => $buyer->id,
            'parties' => [
                ['company_id' => $buyer->id, 'role' => 'buyer'],
                ['company_id' => $supplier->id, 'role' => 'supplier'],
            ],
            'status' => ContractStatus::ACTIVE,
            'total_amount' => 100000,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'retention_percentage' => $retentionPct,
            'retention_release_date' => $releaseDate,
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id' => $user->id,
            'status' => PaymentStatus::APPROVED->value,
            'amount' => $amount,
            'currency' => 'AED',
            'milestone' => $milestone,
            'vat_rate' => 5,
        ]);
        $payment->setRelation('contract', $contract);

        return [$contract, $payment];
    }

    private function company(string $name, CompanyType $type): Company
    {
        return Company::create([
            'name' => $name,
            'registration_number' => 'TRN-'.uniqid(),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'email' => strtolower($name).'-'.uniqid().'@t.test',
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
}
