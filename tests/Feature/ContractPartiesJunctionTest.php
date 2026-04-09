<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractParty;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the contract_parties denormalized junction table.
 *
 * The table is a queryable index over `contracts.parties` JSON.
 * The ContractObserver keeps the two in sync; this test asserts:
 *
 *   - Creating a contract populates the junction.
 *   - Updating the parties JSON re-syncs the junction.
 *   - The Contract::forCompany scope returns every contract that
 *     has a row for that company in the junction.
 *   - Deleting a contract cascades to the junction (FK constraint).
 */
class ContractPartiesJunctionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCompany(string $name, CompanyType $type): Company
    {
        return Company::create([
            'name'                => $name,
            'registration_number' => 'TRN-' . uniqid(),
            'type'                => $type,
            'status'              => CompanyStatus::ACTIVE,
            'email'               => uniqid() . '@j.test',
            'city'                => 'Dubai',
            'country'             => 'UAE',
        ]);
    }

    public function test_creating_a_contract_populates_the_junction_for_buyer_and_every_party(): void
    {
        $buyer    = $this->makeCompany('Buyer Co', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier Co', CompanyType::SUPPLIER);
        $logistics = $this->makeCompany('Logi Co', CompanyType::LOGISTICS);

        $contract = Contract::create([
            'title'            => 'Multi-party',
            'buyer_company_id' => $buyer->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [
                ['company_id' => $supplier->id,  'role' => 'supplier'],
                ['company_id' => $logistics->id, 'role' => 'logistics'],
            ],
            'total_amount'     => 1000,
            'currency'         => 'AED',
            'start_date'       => now(),
            'end_date'         => now()->addMonth(),
        ]);

        $rows = ContractParty::where('contract_id', $contract->id)->get();

        // Three rows: buyer + supplier + logistics. The buyer is
        // synthesised by the observer from buyer_company_id.
        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(
            ['buyer', 'supplier', 'logistics'],
            $rows->pluck('role')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$buyer->id, $supplier->id, $logistics->id],
            $rows->pluck('company_id')->map(fn ($v) => (int) $v)->all(),
        );
    }

    public function test_updating_parties_json_re_syncs_the_junction(): void
    {
        $buyer       = $this->makeCompany('Buyer', CompanyType::BUYER);
        $supplierOld = $this->makeCompany('Old Supplier', CompanyType::SUPPLIER);
        $supplierNew = $this->makeCompany('New Supplier', CompanyType::SUPPLIER);

        $contract = Contract::create([
            'title'            => 'Reshuffle',
            'buyer_company_id' => $buyer->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [['company_id' => $supplierOld->id, 'role' => 'supplier']],
            'total_amount'     => 1000,
            'currency'         => 'AED',
            'start_date'       => now(),
            'end_date'         => now()->addMonth(),
        ]);

        // Sanity: junction has buyer + old supplier.
        $this->assertSame(2, ContractParty::where('contract_id', $contract->id)->count());
        $this->assertTrue(
            ContractParty::where('contract_id', $contract->id)
                ->where('company_id', $supplierOld->id)
                ->exists()
        );

        // Swap the supplier.
        $contract->update([
            'parties' => [['company_id' => $supplierNew->id, 'role' => 'supplier']],
        ]);

        $rows = ContractParty::where('contract_id', $contract->id)->get();
        $this->assertSame(2, $rows->count());
        $this->assertFalse(
            $rows->contains(fn ($r) => (int) $r->company_id === $supplierOld->id),
            'Old supplier should have been removed from the junction.'
        );
        $this->assertTrue(
            $rows->contains(fn ($r) => (int) $r->company_id === $supplierNew->id),
            'New supplier should have been inserted into the junction.'
        );
    }

    public function test_for_company_scope_returns_every_contract_a_company_is_party_of(): void
    {
        $buyer    = $this->makeCompany('Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier', CompanyType::SUPPLIER);
        $other    = $this->makeCompany('Other', CompanyType::SUPPLIER);

        // 2 contracts where supplier is a party.
        Contract::create([
            'title'            => 'A',
            'buyer_company_id' => $buyer->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [['company_id' => $supplier->id, 'role' => 'supplier']],
            'total_amount'     => 1, 'currency' => 'AED',
            'start_date'       => now(), 'end_date' => now()->addMonth(),
        ]);
        Contract::create([
            'title'            => 'B',
            'buyer_company_id' => $buyer->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [['company_id' => $supplier->id, 'role' => 'supplier']],
            'total_amount'     => 2, 'currency' => 'AED',
            'start_date'       => now(), 'end_date' => now()->addMonth(),
        ]);
        // 1 contract where supplier is NOT a party — it's the
        // unrelated $other supplier instead.
        Contract::create([
            'title'            => 'C',
            'buyer_company_id' => $buyer->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [['company_id' => $other->id, 'role' => 'supplier']],
            'total_amount'     => 3, 'currency' => 'AED',
            'start_date'       => now(), 'end_date' => now()->addMonth(),
        ]);

        $supplierContracts = Contract::query()->forCompany($supplier->id)->get();
        $this->assertSame(2, $supplierContracts->count());
        $this->assertEqualsCanonicalizing(['A', 'B'], $supplierContracts->pluck('title')->all());

        // The other supplier sees only their own contract.
        $otherContracts = Contract::query()->forCompany($other->id)->get();
        $this->assertSame(1, $otherContracts->count());
        $this->assertSame('C', $otherContracts->first()->title);
    }

    public function test_deleting_a_contract_cascades_to_the_junction(): void
    {
        $buyer    = $this->makeCompany('Buyer', CompanyType::BUYER);
        $supplier = $this->makeCompany('Supplier', CompanyType::SUPPLIER);

        $contract = Contract::create([
            'title'            => 'Doomed',
            'buyer_company_id' => $buyer->id,
            'status'           => ContractStatus::ACTIVE,
            'parties'          => [['company_id' => $supplier->id, 'role' => 'supplier']],
            'total_amount'     => 1, 'currency' => 'AED',
            'start_date'       => now(), 'end_date' => now()->addMonth(),
        ]);

        $this->assertSame(2, ContractParty::where('contract_id', $contract->id)->count());

        $contract->delete();

        $this->assertSame(0, ContractParty::where('contract_id', $contract->id)->count());
    }
}
