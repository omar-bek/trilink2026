<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Coverage for PaymentService — focused on the two methods that touch
 * money state: generateFromSchedule() (idempotent milestone fan-out) and
 * approve() (the trade-license guard introduced in Sprint A.5).
 *
 * Each test creates a minimal fixture, exercises one branch, and asserts
 * exactly one observable outcome. No mocks for Payment::create — the
 * Eloquent observer is part of the contract under test.
 */
class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Notifications fire on approve(); silence them so the assertions
        // stay focused on payment state instead of mailbox state.
        Notification::fake();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Fixtures
    // ─────────────────────────────────────────────────────────────────

    private function makeCompany(string $name, CompanyType $type, bool $withLicense = true): Company
    {
        $company = Company::create([
            'name' => $name,
            'registration_number' => 'TRN-'.uniqid(),
            'type' => $type,
            'status' => CompanyStatus::ACTIVE,
            'email' => strtolower(str_replace(' ', '', $name)).'@p.test',
            'city' => 'Dubai',
            'country' => 'UAE',
        ]);

        if ($withLicense) {
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

        return $company;
    }

    private function makeUser(Company $company, UserRole $role = UserRole::BUYER): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'u-'.uniqid().'@p.test',
            'password' => 'secret-pass',
            'role' => $role,
            'status' => UserStatus::ACTIVE,
            'company_id' => $company->id,
        ]);
    }

    /**
     * Build an ACTIVE contract between buyer + supplier with a 30/40/30
     * payment schedule. Used as the happy-path baseline for every
     * generateFromSchedule test.
     */
    private function makeContract(Company $buyer, Company $supplier, array $schedule): Contract
    {
        return Contract::create([
            'title' => 'C-'.uniqid(),
            'buyer_company_id' => $buyer->id,
            'status' => ContractStatus::ACTIVE,
            'parties' => [
                ['company_id' => $buyer->id,    'role' => 'buyer'],
                ['company_id' => $supplier->id, 'role' => 'supplier'],
            ],
            'total_amount' => 100000,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'payment_schedule' => $schedule,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  generateFromSchedule
    // ─────────────────────────────────────────────────────────────────

    public function test_generates_one_payment_per_schedule_milestone(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $this->makeUser($buyer); // satisfies buyer_id NOT NULL FK fallback

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Advance',    'percentage' => 30, 'amount' => 30000, 'tax_rate' => 5, 'currency' => 'AED'],
            ['milestone' => 'Production', 'percentage' => 40, 'amount' => 40000, 'tax_rate' => 5, 'currency' => 'AED'],
            ['milestone' => 'Delivery',   'percentage' => 30, 'amount' => 30000, 'tax_rate' => 5, 'currency' => 'AED'],
        ]);

        $created = app(PaymentService::class)->generateFromSchedule($contract);

        $this->assertSame(3, $created);
        $this->assertSame(3, $contract->payments()->count());

        $payments = $contract->payments()->orderBy('id')->get();
        $this->assertEquals([30000, 40000, 30000], $payments->pluck('amount')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(['Advance', 'Production', 'Delivery'], $payments->pluck('milestone')->all());

        // Every payment lands in PENDING_APPROVAL — finance approves later.
        foreach ($payments as $p) {
            $this->assertSame(PaymentStatus::PENDING_APPROVAL->value, $p->status->value);
            $this->assertEquals($buyer->id, $p->company_id);
            $this->assertEquals($supplier->id, $p->recipient_company_id);
            $this->assertEquals(5, (float) $p->vat_rate);
        }
    }

    public function test_is_idempotent_when_payments_already_exist(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Advance', 'percentage' => 100, 'amount' => 100000, 'tax_rate' => 5],
        ]);

        // First call: 1 row created.
        $first = app(PaymentService::class)->generateFromSchedule($contract);
        $this->assertSame(1, $first);

        // Second call: short-circuit, no duplicates.
        $second = app(PaymentService::class)->generateFromSchedule($contract);
        $this->assertSame(0, $second);
        $this->assertSame(1, $contract->payments()->count());
    }

    public function test_skips_milestones_with_zero_or_missing_amount(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Real', 'percentage' => 100, 'amount' => 50000, 'tax_rate' => 5],
            ['milestone' => 'Zero', 'percentage' => 0,   'amount' => 0,     'tax_rate' => 5],
            ['milestone' => 'Missing'], // no amount key at all — also skipped
        ]);

        $created = app(PaymentService::class)->generateFromSchedule($contract);

        $this->assertSame(1, $created);
        $this->assertSame(1, $contract->payments()->count());
        $this->assertSame('Real', $contract->payments()->value('milestone'));
    }

    public function test_returns_zero_when_no_supplier_party_present(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $this->makeUser($buyer);

        // Contract has only the buyer party — no supplier role anywhere.
        $contract = Contract::create([
            'title' => 'Lonely',
            'buyer_company_id' => $buyer->id,
            'status' => ContractStatus::ACTIVE,
            'parties' => [
                ['company_id' => $buyer->id, 'role' => 'buyer'],
            ],
            'total_amount' => 1000,
            'currency' => 'AED',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'payment_schedule' => [
                ['milestone' => 'Advance', 'amount' => 1000, 'tax_rate' => 5],
            ],
        ]);

        $created = app(PaymentService::class)->generateFromSchedule($contract);

        $this->assertSame(0, $created);
        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_returns_zero_when_buyer_company_has_no_users(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        // INTENTIONALLY: no makeUser($buyer) call. The fallback can't
        // satisfy buyer_id and the service must bail out cleanly.

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Advance', 'amount' => 5000, 'tax_rate' => 5],
        ]);

        $created = app(PaymentService::class)->generateFromSchedule($contract);

        $this->assertSame(0, $created);
        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_returns_zero_when_schedule_is_empty(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, []);

        $created = app(PaymentService::class)->generateFromSchedule($contract);

        $this->assertSame(0, $created);
    }

    public function test_inherits_currency_from_milestone_then_contract(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Override', 'amount' => 1000, 'currency' => 'USD', 'tax_rate' => 5],
            ['milestone' => 'Inherit',  'amount' => 2000, 'tax_rate' => 5], // no currency key
        ]);
        // Contract is in AED — second row should pick that up.

        app(PaymentService::class)->generateFromSchedule($contract);

        $payments = $contract->payments()->orderBy('id')->get();
        $this->assertSame('USD', $payments[0]->currency);
        $this->assertSame('AED', $payments[1]->currency);
    }

    // ─────────────────────────────────────────────────────────────────
    //  approve — trade-license guard (Sprint A.5)
    // ─────────────────────────────────────────────────────────────────

    public function test_approve_blocks_when_payer_trade_license_expired(): void
    {
        // Buyer has NO valid trade license (withLicense=false).
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER, withLicense: false);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $approver = $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Advance', 'amount' => 5000],
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id' => $approver->id,
            'status' => PaymentStatus::PENDING_APPROVAL,
            'amount' => 5000,
            'vat_rate' => 5,
            'currency' => 'AED',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/trade license/i');

        app(PaymentService::class)->approve($payment->id, $approver->id);
    }

    public function test_approve_succeeds_when_both_licenses_valid(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $approver = $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Advance', 'amount' => 5000],
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id' => $approver->id,
            'status' => PaymentStatus::PENDING_APPROVAL,
            'amount' => 5000,
            'vat_rate' => 5,
            'currency' => 'AED',
        ]);

        $result = app(PaymentService::class)->approve($payment->id, $approver->id);

        $this->assertNotNull($result);
        $this->assertSame(PaymentStatus::APPROVED->value, $result->status->value);
        $this->assertNotNull($result->approved_at);
        $this->assertEquals($approver->id, $result->approved_by);
    }

    public function test_approve_returns_null_when_payment_not_pending(): void
    {
        $buyer = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $approver = $this->makeUser($buyer);

        $contract = $this->makeContract($buyer, $supplier, [
            ['milestone' => 'Advance', 'amount' => 5000],
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'company_id' => $buyer->id,
            'recipient_company_id' => $supplier->id,
            'buyer_id' => $approver->id,
            'status' => PaymentStatus::APPROVED, // already approved
            'amount' => 5000,
            'vat_rate' => 5,
            'currency' => 'AED',
        ]);

        $result = app(PaymentService::class)->approve($payment->id, $approver->id);

        $this->assertNull($result);
    }
}
