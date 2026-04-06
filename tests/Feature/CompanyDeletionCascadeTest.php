<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cascade policy when an admin deletes a company:
 *
 *   - DELETED:   users (soft), company⇄category pivot
 *   - PRESERVED: contracts, bids, payments, RFQs, purchase_requests,
 *                shipments, disputes, audit logs
 *
 * Companies are the platform's foundation, but their transactional history
 * must remain intact for audit/compliance and counter-party fairness.
 */
class CompanyDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Build a fully-populated tenant: company + 3 users + 1 of each
     * transactional record so we can verify both sides of the cascade.
     *
     * @return array{company: Company, users: array<int, User>, pr: PurchaseRequest, rfq: Rfq, bid: Bid, contract: Contract, payment: Payment, shipment: Shipment, dispute: Dispute, category: Category}
     */
    private function seedFullTenant(): array
    {
        $company = Company::create([
            'name'                => 'Acme Industries',
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => CompanyType::BUYER,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => 'acme@test.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);

        $users = [];
        foreach (['Alice', 'Bob', 'Carol'] as $name) {
            $users[] = User::create([
                'first_name' => $name,
                'last_name'  => 'Acme',
                'email'      => strtolower($name) . '-' . uniqid() . '@acme.test',
                'password'   => 'secret-pass',
                'role'       => UserRole::BUYER,
                'status'     => UserStatus::ACTIVE,
                'company_id' => $company->id,
            ]);
        }

        $category = Category::create(['name' => 'Electronics ' . uniqid(), 'is_active' => true]);
        $company->categories()->attach($category->id);

        // Counter-party — needed so contracts/bids/payments have a "the other
        // side" record to verify ownership-by-history is preserved.
        $supplierCompany = Company::create([
            'name'                => 'Widget Supply',
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => CompanyType::SUPPLIER,
            'status'              => CompanyStatus::ACTIVE,
        ]);

        $pr = PurchaseRequest::create([
            'title'         => 'Office chairs',
            'company_id'    => $company->id,
            'buyer_id'      => $users[0]->id,
            'status'        => PurchaseRequestStatus::APPROVED,
            'budget'        => 50000,
            'currency'      => 'AED',
            'items'         => [],
            'required_date' => now()->addDays(30),
        ]);

        $rfq = Rfq::create([
            'title'      => 'Chair RFQ',
            'company_id' => $company->id,
            'purchase_request_id' => $pr->id,
            'type'       => RfqType::SUPPLIER,
            'status'     => RfqStatus::OPEN,
            'budget'     => 50000,
            'currency'   => 'AED',
            'items'      => [],
            'deadline'   => now()->addDays(15),
        ]);

        $bid = Bid::create([
            'rfq_id'             => $rfq->id,
            'company_id'         => $supplierCompany->id,
            'provider_id'        => $users[0]->id,
            'status'             => BidStatus::SUBMITTED,
            'price'              => 48000,
            'currency'           => 'AED',
            'delivery_time_days' => 20,
            'validity_date'      => now()->addDays(20),
        ]);

        $contract = Contract::create([
            'title'            => 'Acme ⇄ Widget Supply',
            'buyer_company_id' => $company->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [
                ['company_id' => $company->id,         'role' => 'buyer'],
                ['company_id' => $supplierCompany->id, 'role' => 'supplier'],
            ],
            'total_amount' => 48000,
            'currency'     => 'AED',
            'start_date'   => now(),
            'end_date'     => now()->addMonths(3),
        ]);

        $payment = Payment::create([
            'contract_id'          => $contract->id,
            'company_id'           => $company->id,
            'recipient_company_id' => $supplierCompany->id,
            'buyer_id'             => $users[0]->id,
            'status'               => PaymentStatus::PENDING_APPROVAL,
            'amount'               => 14400,
            'vat_rate'             => 5,
            'currency'             => 'AED',
            'milestone'            => 'Advance',
        ]);

        $shipment = Shipment::create([
            'contract_id' => $contract->id,
            'company_id'  => $company->id,
            'status'      => ShipmentStatus::IN_TRANSIT,
            'origin'      => ['city' => 'Dubai'],
            'destination' => ['city' => 'Abu Dhabi'],
        ]);

        $dispute = Dispute::create([
            'contract_id'        => $contract->id,
            'company_id'         => $company->id,
            'raised_by'          => $users[0]->id,
            'against_company_id' => $supplierCompany->id,
            'type'               => DisputeType::QUALITY,
            'status'             => DisputeStatus::OPEN,
            'title'              => 'Late delivery',
            'description'        => 'Goods came late',
        ]);

        return compact('company', 'users', 'pr', 'rfq', 'bid', 'contract', 'payment', 'shipment', 'dispute', 'category');
    }

    public function test_soft_deleting_company_soft_deletes_all_its_users(): void
    {
        $t = $this->seedFullTenant();

        $userIds = array_map(fn (User $u) => $u->id, $t['users']);
        $this->assertCount(3, User::whereIn('id', $userIds)->get());

        $t['company']->delete();

        // The company itself is soft-deleted.
        $this->assertSoftDeleted('companies', ['id' => $t['company']->id]);

        // Every user under the tenant is gone from the default scope.
        $this->assertEquals(0, User::whereIn('id', $userIds)->count());
        // …but still recoverable via withTrashed (audit-safe).
        $this->assertEquals(3, User::withTrashed()->whereIn('id', $userIds)->count());
    }

    public function test_soft_deleting_company_preserves_all_transactional_data(): void
    {
        $t = $this->seedFullTenant();

        $t['company']->delete();

        // Every transactional row STILL exists in its table.
        $this->assertDatabaseHas('purchase_requests', ['id' => $t['pr']->id]);
        $this->assertDatabaseHas('rfqs',              ['id' => $t['rfq']->id]);
        $this->assertDatabaseHas('bids',              ['id' => $t['bid']->id]);
        $this->assertDatabaseHas('contracts',         ['id' => $t['contract']->id]);
        $this->assertDatabaseHas('payments',          ['id' => $t['payment']->id]);
        $this->assertDatabaseHas('shipments',         ['id' => $t['shipment']->id]);
        $this->assertDatabaseHas('disputes',          ['id' => $t['dispute']->id]);

        // None are soft-deleted either.
        $this->assertNull($t['pr']->fresh()->deleted_at);
        $this->assertNull($t['contract']->fresh()->deleted_at);
        $this->assertNull($t['payment']->fresh()->deleted_at);
    }

    public function test_soft_deleting_company_detaches_company_category_pivot(): void
    {
        $t = $this->seedFullTenant();

        $this->assertEquals(1, DB::table('company_category')->where('company_id', $t['company']->id)->count());

        $t['company']->delete();

        // Pivot is gone — it's a relationship row, not history.
        $this->assertEquals(0, DB::table('company_category')->where('company_id', $t['company']->id)->count());
        // But the category itself remains.
        $this->assertDatabaseHas('categories', ['id' => $t['category']->id]);
    }

    public function test_admin_destroy_uses_soft_delete_not_force_delete(): void
    {
        // The admin UI route always uses soft-delete (`$company->delete()`),
        // which preserves the company row in the DB so the FK cascades on
        // company_id never trigger. This is the production path and the
        // policy enforced by Company::booted().
        //
        // Force-delete is a *destructive override* that bypasses this policy
        // at the DB layer (cascadeOnDelete on company_id FKs). It is not
        // exposed in any UI route — admins must use tinker/manual code.
        // Tests below verify the production (soft-delete) path only.
        $t = $this->seedFullTenant();

        $t['company']->delete(); // soft

        // Company is recoverable.
        $this->assertSoftDeleted('companies', ['id' => $t['company']->id]);
        $this->assertNotNull(Company::withTrashed()->find($t['company']->id));

        // ALL transactional history survives — exactly the user's requirement.
        $this->assertDatabaseHas('contracts', ['id' => $t['contract']->id]);
        $this->assertDatabaseHas('bids',      ['id' => $t['bid']->id]);
        $this->assertDatabaseHas('payments',  ['id' => $t['payment']->id]);
        $this->assertDatabaseHas('rfqs',      ['id' => $t['rfq']->id]);
        $this->assertDatabaseHas('purchase_requests', ['id' => $t['pr']->id]);
        $this->assertDatabaseHas('shipments', ['id' => $t['shipment']->id]);
        $this->assertDatabaseHas('disputes',  ['id' => $t['dispute']->id]);
    }

    public function test_admin_destroy_route_triggers_full_cascade(): void
    {
        $t = $this->seedFullTenant();

        // Create an admin to call the destroy route.
        $adminCompany = Company::create([
            'name' => 'Platform',
            'registration_number' => 'TRN-ADMIN-' . uniqid(),
            'type' => CompanyType::BUYER,
            'status' => CompanyStatus::ACTIVE,
        ]);
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name'  => 'Op',
            'email'      => 'admin-' . uniqid() . '@p.test',
            'password'   => 'secret-pass',
            'role'       => UserRole::ADMIN,
            'status'     => UserStatus::ACTIVE,
            'company_id' => $adminCompany->id,
        ]);

        $userIds = array_map(fn (User $u) => $u->id, $t['users']);

        $this->actingAs($admin)
            ->delete(route('admin.companies.destroy', ['id' => $t['company']->id]))
            ->assertRedirect(route('admin.companies.index'));

        // Cascade: company + users gone, transactional data preserved.
        $this->assertSoftDeleted('companies', ['id' => $t['company']->id]);
        $this->assertEquals(0, User::whereIn('id', $userIds)->count());
        $this->assertDatabaseHas('contracts', ['id' => $t['contract']->id]);
        $this->assertDatabaseHas('payments',  ['id' => $t['payment']->id]);
        $this->assertDatabaseHas('bids',      ['id' => $t['bid']->id]);
    }

    public function test_counter_party_records_remain_for_the_other_side(): void
    {
        $t = $this->seedFullTenant();

        // The supplier (counter-party) should still be able to see the bid + contract
        // they participated in, even after the buyer's company is deleted.
        $supplierCompanyId = $t['bid']->company_id;

        $t['company']->delete();

        // The bid still belongs to the supplier — they can still query it.
        $this->assertEquals(1, Bid::where('company_id', $supplierCompanyId)->count());

        // The contract still lists the supplier in its parties JSON.
        $contract = Contract::find($t['contract']->id);
        $partyCompanyIds = collect($contract->parties)->pluck('company_id')->all();
        $this->assertContains($supplierCompanyId, $partyCompanyIds);

        // The payment to the supplier still exists.
        $this->assertEquals(1, Payment::where('recipient_company_id', $supplierCompanyId)->count());
    }
}
