<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\EscrowAccount;
use App\Models\User;
use App\Services\DisputeService;
use App\Services\Escrow\BankPartnerException;
use App\Services\EscrowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase B — opening a dispute on a contract with an active escrow
 * account should auto-freeze releases/refunds until the dispute is
 * resolved or withdrawn.
 */
class EscrowDisputeFreezeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    public function test_opening_dispute_freezes_escrow(): void
    {
        [$contract, $escrow, $claimantUser, $respondentCompany] = $this->fixtures();

        $dispute = app(DisputeService::class)->create([
            'contract_id' => $contract->id,
            'company_id' => $claimantUser->company_id,
            'raised_by' => $claimantUser->id,
            'against_company_id' => $respondentCompany->id,
            'type' => 'quality',
            'status' => DisputeStatus::OPEN->value,
            'title' => 'Bad goods',
            'description' => 'Quality below spec.',
            'severity' => DisputeSeverity::HIGH->value,
        ]);

        $escrow->refresh();
        $this->assertNotNull($escrow->frozen_at);
        $this->assertEquals($dispute->id, $escrow->frozen_by_dispute_id);
    }

    public function test_release_blocked_while_frozen(): void
    {
        [$contract, $escrow, $claimantUser, $respondentCompany] = $this->fixtures();

        app(DisputeService::class)->create([
            'contract_id' => $contract->id,
            'company_id' => $claimantUser->company_id,
            'raised_by' => $claimantUser->id,
            'against_company_id' => $respondentCompany->id,
            'type' => 'payment',
            'status' => DisputeStatus::OPEN->value,
            'title' => 'Withheld payment',
            'description' => 'Payment not released.',
            'severity' => DisputeSeverity::MEDIUM->value,
        ]);

        $this->expectException(BankPartnerException::class);
        $this->expectExceptionMessage('frozen');
        app(EscrowService::class)->release(
            $escrow->fresh(),
            1000,
            'AED',
            'delivery',
            null,
            'manual',
            $claimantUser
        );
    }

    public function test_resolving_dispute_unfreezes_escrow(): void
    {
        [$contract, $escrow, $claimantUser, $respondentCompany, $govUser] = $this->fixtures();

        $disputes = app(DisputeService::class);
        $dispute = $disputes->create([
            'contract_id' => $contract->id,
            'company_id' => $claimantUser->company_id,
            'raised_by' => $claimantUser->id,
            'against_company_id' => $respondentCompany->id,
            'type' => 'quality',
            'status' => DisputeStatus::OPEN->value,
            'title' => 'test',
            'description' => 'test',
            'severity' => DisputeSeverity::MEDIUM->value,
        ]);

        $disputes->decide($dispute->fresh(), $govUser, [
            'decision_outcome' => 'for_claimant',
            'resolution' => 'Claimant wins.',
        ]);

        $escrow->refresh();
        $this->assertNull($escrow->frozen_at);
        $this->assertNull($escrow->frozen_by_dispute_id);
    }

    /**
     * @return array{0:Contract,1:EscrowAccount,2:User,3:Company,4:User}
     */
    private function fixtures(): array
    {
        $buyer = $this->company('Buyer Co', CompanyType::BUYER);
        $supplier = $this->company('Supplier Co', CompanyType::SUPPLIER);
        $claimantUser = $this->user($buyer, UserRole::BUYER);
        $govCo = $this->company('Gov Co', CompanyType::BUYER);
        $govUser = $this->user($govCo, UserRole::GOVERNMENT);

        $contract = Contract::create([
            'title' => 'C-'.uniqid(),
            'buyer_company_id' => $buyer->id,
            'parties' => [
                ['company_id' => $buyer->id, 'role' => 'buyer'],
                ['company_id' => $supplier->id, 'role' => 'supplier'],
            ],
            'status' => ContractStatus::ACTIVE,
            'total_amount' => 50000,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        $escrow = EscrowAccount::create([
            'contract_id' => $contract->id,
            'bank_partner' => 'mock',
            'currency' => 'AED',
            'status' => EscrowAccount::STATUS_ACTIVE,
            'activated_at' => now(),
            'total_deposited' => 50000,
            'total_released' => 0,
        ]);
        $contract->update(['escrow_account_id' => $escrow->id]);

        return [$contract, $escrow, $claimantUser, $supplier, $govUser];
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

    private function user(Company $company, UserRole $role): User
    {
        return User::create([
            'first_name' => 'T', 'last_name' => 'U',
            'email' => 'u-'.uniqid().'@t.test',
            'password' => 'secret-pass',
            'role' => $role,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }
}
