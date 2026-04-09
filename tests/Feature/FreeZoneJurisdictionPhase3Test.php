<?php

namespace Tests\Feature;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\FreeZoneAuthority;
use App\Enums\LegalJurisdiction;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use App\Services\ContractService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (UAE Compliance Roadmap) — Free Zone & Jurisdiction Awareness
 * regression suite. Verifies the four behaviours that justify the
 * change:
 *
 *   1. Enums correctly classify zones (designated vs not, jurisdiction)
 *   2. Company casts persist + round-trip the new columns
 *   3. ContractService picks the right legal clauses for the resolved
 *      jurisdiction (federal / DIFC / ADGM)
 *   4. ContractService picks the right VAT clauses for the resolved
 *      VAT case (standard / designated-zone internal / reverse charge)
 */
class FreeZoneJurisdictionPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCompany(
        string $name,
        ?FreeZoneAuthority $fz = null,
        CompanyType $type = CompanyType::BUYER,
    ): Company {
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
            'is_free_zone'        => $fz !== null,
            'free_zone_authority' => $fz?->value,
            'is_designated_zone'  => $fz?->isDesignated() ?? false,
            'legal_jurisdiction'  => ($fz?->jurisdiction() ?? LegalJurisdiction::FEDERAL)->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. Enums
    // ─────────────────────────────────────────────────────────────────

    public function test_designated_zones_are_correctly_flagged(): void
    {
        $this->assertTrue(FreeZoneAuthority::DAFZA->isDesignated());
        $this->assertTrue(FreeZoneAuthority::JAFZA->isDesignated());
        $this->assertTrue(FreeZoneAuthority::DMCC->isDesignated());
        $this->assertTrue(FreeZoneAuthority::KIZAD->isDesignated());

        // DIFC and ADGM are common-law zones, NOT designated-zone for VAT
        $this->assertFalse(FreeZoneAuthority::DIFC->isDesignated());
        $this->assertFalse(FreeZoneAuthority::ADGM->isDesignated());
    }

    public function test_jurisdiction_resolution_picks_common_law_only_when_both_parties_match(): void
    {
        $this->assertSame(
            LegalJurisdiction::DIFC,
            LegalJurisdiction::resolveForPair(LegalJurisdiction::DIFC, LegalJurisdiction::DIFC)
        );
        $this->assertSame(
            LegalJurisdiction::ADGM,
            LegalJurisdiction::resolveForPair(LegalJurisdiction::ADGM, LegalJurisdiction::ADGM)
        );

        // Mixed → federal default
        $this->assertSame(
            LegalJurisdiction::FEDERAL,
            LegalJurisdiction::resolveForPair(LegalJurisdiction::DIFC, LegalJurisdiction::FEDERAL)
        );
        $this->assertSame(
            LegalJurisdiction::FEDERAL,
            LegalJurisdiction::resolveForPair(LegalJurisdiction::DIFC, LegalJurisdiction::ADGM)
        );

        // Both null → federal
        $this->assertSame(
            LegalJurisdiction::FEDERAL,
            LegalJurisdiction::resolveForPair(null, null)
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Company casts
    // ─────────────────────────────────────────────────────────────────

    public function test_company_persists_and_casts_free_zone_columns(): void
    {
        $company = $this->makeCompany('JAFZA Co', FreeZoneAuthority::JAFZA);

        $reloaded = $company->fresh();
        $this->assertTrue($reloaded->is_free_zone);
        $this->assertSame(FreeZoneAuthority::JAFZA, $reloaded->free_zone_authority);
        $this->assertTrue($reloaded->is_designated_zone);
        $this->assertSame(LegalJurisdiction::FEDERAL, $reloaded->legal_jurisdiction);
        $this->assertTrue($reloaded->isInDesignatedZone());
    }

    public function test_difc_company_is_classified_as_difc_jurisdiction(): void
    {
        $company = $this->makeCompany('DIFC Co', FreeZoneAuthority::DIFC);
        $reloaded = $company->fresh();

        $this->assertSame(FreeZoneAuthority::DIFC, $reloaded->free_zone_authority);
        $this->assertSame(LegalJurisdiction::DIFC, $reloaded->jurisdiction());
        $this->assertFalse($reloaded->isInDesignatedZone());
    }

    public function test_legacy_company_without_columns_falls_back_to_federal(): void
    {
        $legacy = $this->makeCompany('Legacy Co'); // no FZ
        $this->assertFalse($legacy->is_free_zone);
        $this->assertSame(LegalJurisdiction::FEDERAL, $legacy->jurisdiction());
        $this->assertFalse($legacy->isInDesignatedZone());
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. ContractService — jurisdiction dispatch
    // ─────────────────────────────────────────────────────────────────

    public function test_contract_terms_use_difc_clauses_when_both_parties_in_difc(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyer    = $this->makeCompany('DIFC Buyer', FreeZoneAuthority::DIFC, CompanyType::BUYER);
        $supplier = $this->makeCompany('DIFC Supplier', FreeZoneAuthority::DIFC, CompanyType::SUPPLIER);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'DIFC Test',
            totalValueLabel: 'AED 100,000',
            paymentBreakdown: '100% on delivery',
            deliveryDays: 30,
            buyerCompany: $buyer,
            supplierCompany: $supplier,
        );

        $this->assertSame('difc', $terms['jurisdiction']);
        $en = collect($terms['en'])->keyBy('title');
        $governing = $en->get(__('contracts.governing_law'));
        $this->assertNotNull($governing);
        $this->assertStringContainsString('DIFC', implode(' ', $governing['items']));
    }

    public function test_contract_terms_use_adgm_clauses_when_both_parties_in_adgm(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyer    = $this->makeCompany('ADGM Buyer', FreeZoneAuthority::ADGM, CompanyType::BUYER);
        $supplier = $this->makeCompany('ADGM Supplier', FreeZoneAuthority::ADGM, CompanyType::SUPPLIER);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'ADGM Test',
            totalValueLabel: 'AED 50,000',
            paymentBreakdown: '50/50 split',
            deliveryDays: 60,
            buyerCompany: $buyer,
            supplierCompany: $supplier,
        );

        $this->assertSame('adgm', $terms['jurisdiction']);
        $en = collect($terms['en'])->keyBy('title');
        $governing = $en->get(__('contracts.governing_law'));
        $this->assertStringContainsString('ADGM', implode(' ', $governing['items']));
    }

    public function test_mixed_jurisdiction_falls_back_to_federal(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyer    = $this->makeCompany('DIFC Buyer', FreeZoneAuthority::DIFC, CompanyType::BUYER);
        $supplier = $this->makeCompany('Mainland Supplier', null, CompanyType::SUPPLIER);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'Mixed Test',
            totalValueLabel: 'AED 1,000',
            paymentBreakdown: 'on delivery',
            deliveryDays: 15,
            buyerCompany: $buyer,
            supplierCompany: $supplier,
        );

        $this->assertSame('federal', $terms['jurisdiction']);
    }

    public function test_legacy_call_without_companies_uses_federal_default(): void
    {
        $service = $this->app->make(ContractService::class);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'Legacy',
            totalValueLabel: 'AED 1,000',
            paymentBreakdown: '—',
            deliveryDays: 30,
        );

        $this->assertSame('federal', $terms['jurisdiction']);
        $this->assertSame('standard', $terms['vat_case']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  4. ContractService — VAT case dispatch
    // ─────────────────────────────────────────────────────────────────

    public function test_designated_zone_to_designated_zone_uses_zero_vat_clauses(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyer    = $this->makeCompany('DAFZA Buyer', FreeZoneAuthority::DAFZA, CompanyType::BUYER);
        $supplier = $this->makeCompany('JAFZA Supplier', FreeZoneAuthority::JAFZA, CompanyType::SUPPLIER);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'DZ Test',
            totalValueLabel: 'AED 25,000',
            paymentBreakdown: '—',
            deliveryDays: 14,
            buyerCompany: $buyer,
            supplierCompany: $supplier,
        );

        $this->assertSame('designated_zone_internal', $terms['vat_case']);

        $en = collect($terms['en'])->keyBy('title');
        $vatSection = $en->get(__('contracts.tax_vat'));
        $this->assertNotNull($vatSection);
        $vatText = implode(' ', $vatSection['items']);
        $this->assertStringContainsString('Designated Zones', $vatText);
        $this->assertStringContainsString('outside the scope', $vatText);
    }

    public function test_designated_zone_to_mainland_uses_reverse_charge_clauses(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyer    = $this->makeCompany('Mainland Buyer', null, CompanyType::BUYER);
        $supplier = $this->makeCompany('DAFZA Supplier', FreeZoneAuthority::DAFZA, CompanyType::SUPPLIER);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'RC Test',
            totalValueLabel: 'AED 12,000',
            paymentBreakdown: '—',
            deliveryDays: 7,
            buyerCompany: $buyer,
            supplierCompany: $supplier,
        );

        $this->assertSame('reverse_charge', $terms['vat_case']);

        $en = collect($terms['en'])->keyBy('title');
        $vatText = implode(' ', $en->get(__('contracts.tax_vat'))['items']);
        $this->assertStringContainsString('reverse-charge', $vatText);
    }

    public function test_two_mainland_companies_get_standard_vat_clauses(): void
    {
        $service = $this->app->make(ContractService::class);

        $buyer    = $this->makeCompany('Mainland Buyer 1', null, CompanyType::BUYER);
        $supplier = $this->makeCompany('Mainland Supplier 1', null, CompanyType::SUPPLIER);

        $terms = $service->buildBilingualUaeContractTerms(
            scopeTitle: 'Standard Test',
            totalValueLabel: 'AED 5,000',
            paymentBreakdown: '—',
            deliveryDays: 10,
            buyerCompany: $buyer,
            supplierCompany: $supplier,
        );

        $this->assertSame('standard', $terms['vat_case']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  5. Registration flow accepts FZ choice
    // ─────────────────────────────────────────────────────────────────

    public function test_registration_form_renders_with_free_zone_dropdown(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('establishment_type', false)
            ->assertSee('free_zone_authority', false);
    }
}
