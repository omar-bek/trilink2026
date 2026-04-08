<?php

namespace Database\Seeders;

use App\Enums\AmendmentStatus;
use App\Enums\AuditAction;
use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationLevel;
use App\Models\AuditLog;
use App\Models\BeneficialOwner;
use App\Models\Bid;
use App\Models\Branch;
use App\Models\CarbonFootprint;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyBankDetail;
use App\Models\CompanyDocument;
use App\Models\CompanyInfoRequest;
use App\Models\CompanyInsurance;
use App\Models\CompanySupplier;
use App\Models\ConflictMineralsDeclaration;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractVersion;
use App\Models\CreditScore;
use App\Models\Dispute;
use App\Models\ErpConnector;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Models\EsgQuestionnaire;
use App\Models\ExchangeRate;
use App\Models\Feedback;
use App\Models\ModernSlaveryStatement;
use App\Models\NegotiationMessage;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\SanctionsScreening;
use App\Models\SavedSearch;
use App\Models\ScimUser;
use App\Models\SearchHistory;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\TaxRate;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ComprehensiveSeeder — full end-to-end demo data covering every model the
 * TriLink platform exposes. Re-running this seeder is safe (every write uses
 * updateOrCreate / firstOrCreate) and converges on the same canonical state.
 *
 * Coverage map (45 models):
 *
 *   Foundation
 *     1. Categories                      11. CompanyDocument
 *     2. TaxRate                         12. CompanyInsurance
 *     3. ExchangeRate                    13. SanctionsScreening
 *     4. Company                         14. CreditScore
 *     5. Branch                          15. CompanyInfoRequest
 *     6. User                            16. CompanyBankDetail
 *     7. CompanySupplier                 17. BeneficialOwner
 *     8. SavedSearch                     18. EsgQuestionnaire
 *     9. SearchHistory                   19. ModernSlaveryStatement
 *    10. Setting                         20. ConflictMineralsDeclaration
 *                                        21. CarbonFootprint
 *
 *   Catalog & Cart
 *    22. Product                         24. Cart
 *    23. ProductVariant                  25. CartItem
 *
 *   Procurement Workflow
 *    26. PurchaseRequest                 30. NegotiationMessage
 *    27. Rfq                             31. Contract
 *    28. Bid                             32. ContractAmendment
 *    29. ContractVersion
 *
 *   Trade Finance
 *    33. EscrowAccount                   35. Payment
 *    34. EscrowRelease
 *
 *   Operations
 *    36. Shipment                        38. Dispute
 *    37. TrackingEvent                   39. Feedback
 *
 *   Integrations & Identity
 *    40. WebhookEndpoint                 42. ErpConnector
 *    41. WebhookDelivery                 43. ScimUser
 *
 *   Platform
 *    44. AuditLog                        45. DatabaseNotification
 *
 * Login: every user is created with the password "password".
 */
class ComprehensiveSeeder extends Seeder
{
    /** @var Collection<int,Company> */
    private Collection $suppliers;

    public function run(): void
    {
        $this->command->info('ComprehensiveSeeder: starting…');

        $cats = $this->seedCategories();
        $this->seedTaxRates($cats);
        $this->seedExchangeRates();

        [$buyer, $buyerPending, $logistics, $clearance, $serviceProvider, $sanctioned, $admin]
            = $this->seedCompaniesAndUsers($cats);

        $branches = $this->seedBranches($buyer, $cats);
        $this->bindBranchManager($buyer, $branches);

        $this->seedCompanyBankDetails($buyer, $this->suppliers, $logistics, $clearance, $serviceProvider);
        $this->seedCompanyInfoRequests($buyerPending, $admin);
        $this->seedBeneficialOwners($buyer, $this->suppliers);

        $allCompanies = collect([$buyer, $buyerPending, $logistics, $clearance, $serviceProvider, $sanctioned])
            ->merge($this->suppliers);
        $this->seedCompanyDocuments($allCompanies, $admin);
        $this->seedCompanyInsurances($buyer, $this->suppliers, $logistics, $admin);
        $this->seedSanctionsScreenings($allCompanies, $sanctioned, $admin);
        $this->seedCreditScores($buyer, $this->suppliers);

        $this->seedEsg($buyer, $this->suppliers, $admin);
        $this->seedCompanySuppliers($buyer, $this->suppliers);

        $products  = $this->seedProductsAndVariants($this->suppliers, $cats);
        $this->seedCartsAndCartItems($buyer, $products);

        $prs       = $this->seedPurchaseRequests($buyer, $branches, $cats);
        $rfqs      = $this->seedRfqs($buyer, $branches, $prs, $logistics, $clearance, $serviceProvider, $cats);
        $bids      = $this->seedBids($rfqs, $logistics, $clearance, $serviceProvider);
        $this->seedNegotiationMessages($bids);

        $contracts = $this->seedContracts($buyer, $logistics, $prs, $branches);
        $this->seedContractAmendmentsAndVersions($contracts);

        $escrows   = $this->seedEscrowAccountsAndReleases($contracts);
        $payments  = $this->seedPayments($contracts, $buyer, $escrows);

        $shipments = $this->seedShipments($contracts, $buyer, $logistics);
        $this->seedCarbonFootprints($shipments);

        $this->seedDisputes($contracts, $buyer);
        $this->seedFeedback($contracts, $buyer);

        $this->seedSavedSearchesAndHistory($buyer);

        $this->seedWebhookEndpointsAndDeliveries($buyer);
        $this->seedErpConnectors($buyer);
        $this->seedScimUsers();

        $this->seedSettings();
        $this->seedAuditLogs($buyer);
        $this->seedDatabaseNotifications($buyer);

        $this->command->info(sprintf(
            'ComprehensiveSeeder: done — %d Companies, %d Users, %d Products, %d PRs, %d RFQs, %d Bids, %d Contracts, %d Payments, %d Shipments, %d Disputes, %d Webhooks, %d Notifications.',
            Company::count(), User::count(), Product::count(),
            PurchaseRequest::count(), Rfq::count(), Bid::count(),
            Contract::count(), Payment::count(), Shipment::count(),
            Dispute::count(), WebhookEndpoint::count(), DatabaseNotification::count(),
        ));
    }

    // ================================================================
    // 1. Categories
    // ================================================================
    /** @return array<string,Category> */
    private function seedCategories(): array
    {
        $defs = [
            ['electronics',   'Electronics',          'إلكترونيات',        'Consumer + industrial electronics, components, and assemblies.'],
            ['it-hardware',   'IT Hardware',          'أجهزة تقنية',       'Workstations, servers, networking, and peripherals.'],
            ['construction',  'Construction',         'مواد بناء',         'Cement, steel, formwork, and structural materials.'],
            ['industrial',    'Industrial Equipment', 'معدات صناعية',      'Heavy machinery, pumps, compressors, generators.'],
            ['medical',       'Medical',              'طبي',               'Diagnostic devices, PPE, and clinical consumables.'],
            ['office',        'Office Supplies',      'مستلزمات مكتبية',   'Office furniture, stationery, and printers.'],
            ['logistics-svc', 'Logistics Services',   'خدمات شحن',         'Freight forwarding, road, sea, and air shipping.'],
            ['clearance-svc', 'Customs Services',     'خدمات تخليص',       'Customs brokerage, HS classification, duty calculation.'],
        ];

        $out = [];
        foreach ($defs as [$key, $name, $nameAr, $desc]) {
            $out[$key] = Category::updateOrCreate(
                ['name' => $name],
                [
                    'name_ar'     => $nameAr,
                    'description' => $desc,
                    'level'       => 0,
                    'path'        => $key,
                    'is_active'   => true,
                ],
            );
        }

        return $out;
    }

    // ================================================================
    // 2. Tax rates
    // ================================================================
    /** @param array<string,Category> $cats */
    private function seedTaxRates(array $cats): void
    {
        $rates = [
            ['UAE-VAT-5',       'UAE Standard VAT',   5.00, null,                'AE', true,  true,  'Standard 5% VAT applied to most goods and services in the UAE.'],
            ['UAE-MED-EXEMPT',  'Medical Exempt',     0.00, $cats['medical']->id,'AE', true,  false, 'Zero-rated VAT for qualifying medical equipment.'],
            ['EXPORT-ZERO',     'Export Zero-Rated',  0.00, null,                null, true,  false, 'Zero-rated VAT for qualifying exports outside the GCC.'],
            ['KSA-VAT-15',      'KSA Standard VAT',  15.00, null,                'SA', true,  false, 'Standard 15% VAT applied to most goods and services in Saudi Arabia.'],
        ];

        foreach ($rates as [$code, $name, $rate, $catId, $country, $active, $default, $desc]) {
            TaxRate::updateOrCreate(
                ['code' => $code],
                [
                    'name'        => $name,
                    'rate'        => $rate,
                    'category_id' => $catId,
                    'country'     => $country,
                    'is_active'   => $active,
                    'is_default'  => $default,
                    'description' => $desc,
                ],
            );
        }
    }

    // ================================================================
    // 3. Exchange rates — daily snapshots for the last 5 days
    // ================================================================
    private function seedExchangeRates(): void
    {
        $pairs = [
            ['AED', 'USD', 0.27225840],
            ['AED', 'EUR', 0.25081230],
            ['AED', 'SAR', 1.02110000],
            ['USD', 'AED', 3.67250000],
            ['EUR', 'AED', 3.98700000],
        ];

        foreach (range(0, 4) as $daysAgo) {
            $asOf = now()->subDays($daysAgo)->toDateString();
            foreach ($pairs as [$from, $to, $base]) {
                // Tiny pseudo-jitter so the historical chart isn't a flat line.
                $jitter = (1 + (($daysAgo * 13) % 7 - 3) / 1000);
                ExchangeRate::updateOrCreate(
                    ['from_currency' => $from, 'to_currency' => $to, 'as_of' => $asOf],
                    ['rate' => round($base * $jitter, 8), 'source' => 'mock'],
                );
            }
        }
    }

    // ================================================================
    // 4 + 6. Companies and users
    // ================================================================
    /**
     * @param array<string,Category> $cats
     * @return array{0:Company,1:Company,2:Company,3:Company,4:Company,5:Company,6:User}
     */
    private function seedCompaniesAndUsers(array $cats): array
    {
        // ---- Buyers ----
        $buyer = $this->company([
            'registration_number' => 'BUY-AHRAM-001',
            'name'                => 'Al-Ahram Group',
            'name_ar'             => 'مجموعة الأهرام',
            'tax_number'          => '100000000100003',
            'type'                => CompanyType::BUYER,
            'status'              => CompanyStatus::ACTIVE,
            'verification_level'  => VerificationLevel::GOLD,
            'sanctions_status'    => 'clean',
            'sanctions_screened_at' => now()->subDays(15),
            'email'               => 'info@al-ahram.test',
            'phone'               => '+971 50 000 0010',
            'website'             => 'https://al-ahram.test',
            'address'             => 'Sheikh Zayed Road, Tower 12, Floor 18',
            'city'                => 'Dubai',
            'country'             => 'UAE',
            'description'         => 'Multi-sector regional buyer headquartered in Dubai. Active across construction, IT and industrial procurement.',
        ], $cats, ['construction', 'it-hardware', 'industrial', 'office']);

        $buyerPending = $this->company([
            'registration_number' => 'BUY-FUTURE-002',
            'name'                => 'Future Investments LLC',
            'name_ar'             => 'الاستثمارات المستقبلية',
            'tax_number'          => '100000000200003',
            'type'                => CompanyType::BUYER,
            'status'              => CompanyStatus::PENDING,
            'verification_level'  => VerificationLevel::UNVERIFIED,
            'sanctions_status'    => 'not_screened',
            'email'               => 'register@future-inv.test',
            'phone'               => '+971 50 000 0099',
            'website'             => 'https://future-inv.test',
            'address'             => 'Business Bay, Tower 9',
            'city'                => 'Dubai',
            'country'             => 'UAE',
            'description'         => 'Newly registered buyer awaiting verification approval.',
        ], $cats, []);

        // ---- Suppliers ----
        $supplierSeed = [
            ['SUP-EMIND-001',  'Emirates Industrial Co.', 'شركة الإمارات الصناعية', 'info@emirates-ind.test', '+971 50 000 0021', 'Industrial Area 5',        'Sharjah',   VerificationLevel::PLATINUM, ['industrial', 'electronics']],
            ['SUP-KHOORY-001', 'Al-Khoory Trading LLC',   'الخوري للتجارة',         'sales@khoory.test',      '+971 50 000 0022', 'Deira, Al Maktoum St.',    'Dubai',     VerificationLevel::GOLD,     ['construction']],
            ['SUP-DBTECH-001', 'Dubai Tech Solutions',    'دبي تك سوليوشنز',        'sales@dbtech.test',      '+971 50 000 0023', 'Dubai Internet City',      'Dubai',     VerificationLevel::SILVER,   ['it-hardware', 'electronics']],
            ['SUP-GULFE-001',  'Gulf Office Supplies',    'الخليج للمكاتب',         'info@gulfe.test',        '+971 50 000 0024', 'Mussafah Industrial Area', 'Abu Dhabi', VerificationLevel::BRONZE,   ['office']],
            ['SUP-MEDCO-001',  'MedCo Diagnostics',       'ميدكو للأجهزة الطبية',   'info@medco.test',        '+971 50 000 0025', 'Healthcare City',          'Dubai',     VerificationLevel::GOLD,     ['medical']],
        ];

        $this->suppliers = collect($supplierSeed)->map(fn ($s) => $this->company([
            'registration_number' => $s[0],
            'name'                => $s[1],
            'name_ar'             => $s[2],
            'tax_number'          => '100000' . str_pad((string) (crc32($s[0]) % 1000000), 6, '0', STR_PAD_LEFT) . '00003',
            'type'                => CompanyType::SUPPLIER,
            'status'              => CompanyStatus::ACTIVE,
            'verification_level'  => $s[7],
            'sanctions_status'    => 'clean',
            'sanctions_screened_at' => now()->subDays(20),
            'email'               => $s[3],
            'phone'               => $s[4],
            'website'             => 'https://' . explode('@', $s[3])[1],
            'address'             => $s[5],
            'city'                => $s[6],
            'country'             => 'UAE',
            'description'         => 'Verified TriLink supplier delivering across the GCC.',
            'certifications'      => [
                ['name' => 'ISO 9001:2015', 'issuer' => 'TÜV SÜD', 'expires_at' => now()->addYears(2)->toDateString()],
            ],
        ], $cats, $s[8]));

        // ---- Service providers ----
        $logistics = $this->company([
            'registration_number' => 'LOG-FASTLINE-001',
            'name'                => 'FastLine Logistics',
            'name_ar'             => 'فاست لاين للخدمات اللوجستية',
            'tax_number'          => '100000000300003',
            'type'                => CompanyType::LOGISTICS,
            'status'              => CompanyStatus::ACTIVE,
            'verification_level'  => VerificationLevel::SILVER,
            'sanctions_status'    => 'clean',
            'email'               => 'dispatch@fastline.test',
            'phone'               => '+971 50 000 0050',
            'address'             => 'Jebel Ali Free Zone, Block C',
            'city'                => 'Dubai',
            'country'             => 'UAE',
            'description'         => 'Sea, air, and road freight forwarder. GCC-wide coverage.',
        ], $cats, ['logistics-svc']);

        $clearance = $this->company([
            'registration_number' => 'CLR-CARGOCHECK-001',
            'name'                => 'CargoCheck Customs',
            'name_ar'             => 'كارجوتشيك للتخليص الجمركي',
            'tax_number'          => '100000000400003',
            'type'                => CompanyType::CLEARANCE,
            'status'              => CompanyStatus::ACTIVE,
            'verification_level'  => VerificationLevel::GOLD,
            'sanctions_status'    => 'clean',
            'email'               => 'clearance@cargocheck.test',
            'phone'               => '+971 50 000 0060',
            'address'             => 'Port Rashid, Customs Centre',
            'city'                => 'Dubai',
            'country'             => 'UAE',
            'description'         => 'Licensed customs brokerage and clearance specialists.',
        ], $cats, ['clearance-svc']);

        $serviceProvider = $this->company([
            'registration_number' => 'SRV-BUILDTECH-001',
            'name'                => 'BuildTech Services',
            'name_ar'             => 'بيلد تك للخدمات',
            'tax_number'          => '100000000500003',
            'type'                => CompanyType::SERVICE_PROVIDER,
            'status'              => CompanyStatus::ACTIVE,
            'verification_level'  => VerificationLevel::SILVER,
            'sanctions_status'    => 'clean',
            'email'               => 'sales@buildtech.test',
            'phone'               => '+971 50 000 0070',
            'address'             => 'Dubai Investment Park',
            'city'                => 'Dubai',
            'country'             => 'UAE',
            'description'         => 'Installation, commissioning, and maintenance services.',
        ], $cats, ['construction', 'industrial']);

        $sanctioned = $this->company([
            'registration_number' => 'SUP-REDFLAG-999',
            'name'                => 'RedFlag Trading FZE',
            'name_ar'             => 'ريد فلاج للتجارة',
            'tax_number'          => '100000000999003',
            'type'                => CompanyType::SUPPLIER,
            'status'              => CompanyStatus::INACTIVE,
            'verification_level'  => VerificationLevel::UNVERIFIED,
            'sanctions_status'    => 'hit',
            'sanctions_screened_at' => now()->subDays(2),
            'email'               => 'contact@redflag.test',
            'phone'               => '+971 50 000 9999',
            'address'             => 'Free Zone Office',
            'city'                => 'Ajman',
            'country'             => 'UAE',
            'description'         => 'Suspended after sanctions hit during routine screening.',
        ], $cats, []);

        // ---- Users ----
        $admin = $this->user('admin@trilink.test', 'System', 'Admin', UserRole::ADMIN, null, 'Platform Administrator');

        $this->user('gov@trilink.test', 'Salim', 'Al-Rashid', UserRole::GOVERNMENT, null, 'Government Liaison');

        // Buyer team — covers every internal role
        $this->user('manager@al-ahram.test',     'Khalid',  'Hassan',     UserRole::COMPANY_MANAGER, $buyer->id, 'Procurement Director');
        $this->user('buyer@al-ahram.test',       'Ahmed',   'Al-Mansoori',UserRole::BUYER,           $buyer->id, 'Senior Buyer');
        $this->user('branch.dubai@al-ahram.test','Hessa',   'Al-Falasi',  UserRole::BRANCH_MANAGER,  $buyer->id, 'Dubai Branch Manager');
        $this->user('finance@al-ahram.test',     'Noura',   'Al-Khoori',  UserRole::FINANCE,         $buyer->id, 'AP Specialist');
        $this->user('finance.mgr@al-ahram.test', 'Yasser',  'Al-Marri',   UserRole::FINANCE_MANAGER, $buyer->id, 'Finance Manager');
        $this->user('sales@al-ahram.test',       'Maryam',  'Al-Suwaidi', UserRole::SALES,           $buyer->id, 'Sales Executive');
        $this->user('sales.mgr@al-ahram.test',   'Fahad',   'Al-Hosani',  UserRole::SALES_MANAGER,   $buyer->id, 'Sales Manager');

        $this->user('owner@future-inv.test', 'Tariq', 'Al-Owais', UserRole::COMPANY_MANAGER, $buyerPending->id, 'Founder');

        // One supplier-side manager + supplier-contact for each supplier
        $supplierContactSeed = [
            ['mohammed@emirates-ind.test', 'Mohammed', 'Hassan',     0],
            ['fatima@khoory.test',         'Fatima',   'Al-Zaabi',   1],
            ['rashid@dbtech.test',         'Rashid',   'Al-Maktoum', 2],
            ['layla@gulfe.test',           'Layla',    'Al-Otaibi',  3],
            ['omar@medco.test',            'Omar',     'Al-Sharif',  4],
        ];
        foreach ($supplierContactSeed as $sc) {
            $this->user('manager.' . $sc[0], $sc[1] . ' (Mgr)', $sc[2], UserRole::COMPANY_MANAGER, $this->suppliers[$sc[3]]->id, 'Company Manager');
            $this->user($sc[0],               $sc[1],            $sc[2], UserRole::SUPPLIER,        $this->suppliers[$sc[3]]->id, 'Supplier Contact');
        }

        $this->user('driver@fastline.test',    'Yousef', 'Al-Bedouin', UserRole::LOGISTICS,        $logistics->id,       'Operations');
        $this->user('agent@cargocheck.test',   'Hamad',  'Al-Nuaimi',  UserRole::CLEARANCE,        $clearance->id,       'Customs Agent');
        $this->user('engineer@buildtech.test', 'Saeed',  'Al-Dhaheri', UserRole::SERVICE_PROVIDER, $serviceProvider->id, 'Project Engineer');

        // A user attached to the sanctioned company so the suspended-account
        // path can be exercised end-to-end.
        $this->user('contact@redflag.test', 'Blocked', 'User', UserRole::SUPPLIER, $sanctioned->id, 'Suspended');

        return [$buyer, $buyerPending, $logistics, $clearance, $serviceProvider, $sanctioned, $admin];
    }

    /**
     * @param array<string,mixed>     $attrs
     * @param array<string,Category>  $cats
     * @param list<string>            $catKeys
     */
    private function company(array $attrs, array $cats, array $catKeys): Company
    {
        $regNumber = $attrs['registration_number'];

        // Cast enums to their string value before persisting.
        foreach (['type', 'status', 'verification_level'] as $enumField) {
            if (isset($attrs[$enumField]) && $attrs[$enumField] instanceof \BackedEnum) {
                $attrs[$enumField] = $attrs[$enumField]->value;
            }
        }

        $company = Company::updateOrCreate(
            ['registration_number' => $regNumber],
            $attrs,
        );

        if ($catKeys !== []) {
            $company->categories()->syncWithoutDetaching(
                collect($catKeys)->map(fn ($k) => $cats[$k]->id)->all(),
            );
        }

        return $company;
    }

    private function user(string $email, string $first, string $last, UserRole $role, ?int $companyId, ?string $title = null): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'first_name'     => $first,
                'last_name'      => $last,
                'phone'          => '+971 55 ' . str_pad((string) (crc32($email) % 10000000), 7, '0', STR_PAD_LEFT),
                'password'       => Hash::make('password'),
                'role'           => $role->value,
                'position_title' => $title,
                'status'         => UserStatus::ACTIVE->value,
                'company_id'     => $companyId,
            ],
        );
    }

    // ================================================================
    // 5. Branches
    // ================================================================
    /**
     * @param array<string,Category> $cats
     * @return Collection<int,Branch>
     */
    private function seedBranches(Company $buyer, array $cats): Collection
    {
        $defs = [
            ['Dubai HQ',                'مقر دبي',              'it-hardware',  'Sheikh Zayed Road, Tower 12', 'Dubai',     'AE'],
            ['Abu Dhabi Logistics',     'فرع أبوظبي اللوجستي', 'logistics-svc','Mussafah Industrial Area',     'Abu Dhabi', 'AE'],
            ['Sharjah Industrial',      'فرع الشارقة الصناعي',  'industrial',   'Sharjah Industrial Area 13',  'Sharjah',   'AE'],
            ['Jeddah Trading Office',   'مكتب جدة التجاري',     'construction', 'King Abdullah Road, Jeddah',  'Jeddah',    'SA'],
        ];

        return collect($defs)->map(fn ($d) => Branch::updateOrCreate(
            ['company_id' => $buyer->id, 'name' => $d[0]],
            [
                'name_ar'     => $d[1],
                'category_id' => $cats[$d[2]]->id,
                'address'     => $d[3],
                'city'        => $d[4],
                'country'     => $d[5],
                'is_active'   => true,
            ],
        ));
    }

    /** @param Collection<int,Branch> $branches */
    private function bindBranchManager(Company $buyer, Collection $branches): void
    {
        $branchMgr = User::where('email', 'branch.dubai@al-ahram.test')->first();
        if ($branchMgr) {
            $branchMgr->forceFill(['branch_id' => $branches->first()->id])->save();
        }

        // Pin the Dubai branch's manager pointer for completeness.
        $branches->first()->forceFill(['branch_manager_id' => $branchMgr?->id])->save();
    }

    // ================================================================
    // 16. Company bank details
    // ================================================================
    /** @param Collection<int,Company> $suppliers */
    private function seedCompanyBankDetails(Company $buyer, Collection $suppliers, Company $logistics, Company $clearance, Company $service): void
    {
        $rows = [
            [$buyer,    'Al-Ahram Group',          'Emirates NBD',       'Sheikh Zayed Br.', 'AE070331234567890123456', 'EBILAEAD'],
            [$logistics,'FastLine Logistics',      'Mashreq Bank',       'Jebel Ali Br.',    'AE470330000000098765432', 'BOMLAEAD'],
            [$clearance,'CargoCheck Customs',      'Abu Dhabi Comm Bank','Port Rashid Br.',  'AE140332222333344445555', 'ADCBAEAA'],
            [$service,  'BuildTech Services',     'First Abu Dhabi Bank','DIP Br.',          'AE980331111222233334444', 'NBADAEAA'],
        ];
        foreach ($suppliers as $i => $sup) {
            $rows[] = [$sup, $sup->name, 'Emirates NBD', 'Branch ' . ($i + 1), sprintf('AE0703%020d', 1000000000 + $i * 11), 'EBILAEAD'];
        }

        foreach ($rows as [$company, $holder, $bank, $branch, $iban, $swift]) {
            CompanyBankDetail::updateOrCreate(
                ['company_id' => $company->id],
                [
                    'holder_name' => $holder,
                    'bank_name'   => $bank,
                    'branch'      => $branch,
                    'iban'        => $iban,
                    'swift'       => $swift,
                    'currency'    => 'AED',
                    'notes'       => 'Verified during onboarding.',
                ],
            );
        }
    }

    // ================================================================
    // 15. Company info requests (admin needs more info from a buyer)
    // ================================================================
    private function seedCompanyInfoRequests(Company $buyerPending, User $admin): void
    {
        CompanyInfoRequest::updateOrCreate(
            ['company_id' => $buyerPending->id],
            [
                'items'        => ['tax_number', 'trade_license_file', 'beneficial_owners'],
                'note'         => 'Please upload your trade license and add at least one beneficial owner before we can complete verification.',
                'requested_at' => now()->subDays(3),
                'requested_by' => $admin->id,
            ],
        );
    }

    // ================================================================
    // 17. Beneficial owners
    // ================================================================
    /** @param Collection<int,Company> $suppliers */
    private function seedBeneficialOwners(Company $buyer, Collection $suppliers): void
    {
        // Buyer — two owners totalling 100%.
        $buyerOwners = [
            ['Khalid Hassan Al-Mansoori', 'AE', '1978-04-12', 'emirates_id', '784-1978-1234567-1', 65.00, 'director', false, 'Family business inheritance and active management of the group since 2003.'],
            ['Ahmed Hassan Al-Mansoori',  'AE', '1982-09-30', 'emirates_id', '784-1982-7654321-2', 35.00, 'shareholder', false, 'Co-founder, previously CFO of a Dubai construction firm.'],
        ];
        foreach ($buyerOwners as $i => $o) {
            BeneficialOwner::updateOrCreate(
                ['company_id' => $buyer->id, 'full_name' => $o[0]],
                [
                    'nationality'          => $o[1],
                    'date_of_birth'        => $o[2],
                    'id_type'              => $o[3],
                    'id_number'            => $o[4],
                    'id_expiry'            => now()->addYears(3)->toDateString(),
                    'ownership_percentage' => $o[5],
                    'role'                 => $o[6],
                    'is_pep'               => $o[7],
                    'source_of_wealth'     => $o[8],
                    'last_screened_at'     => now()->subDays(15),
                    'screening_result'     => 'clean',
                    'verified_at'          => now()->subDays(15),
                ],
            );
        }

        // One owner per supplier (100% ownership).
        foreach ($suppliers as $i => $sup) {
            BeneficialOwner::updateOrCreate(
                ['company_id' => $sup->id, 'full_name' => "Founder of {$sup->name}"],
                [
                    'nationality'          => 'AE',
                    'date_of_birth'        => '1975-01-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'id_type'              => 'emirates_id',
                    'id_number'            => '784-1975-' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT) . '-' . ($i + 1),
                    'id_expiry'            => now()->addYears(2)->toDateString(),
                    'ownership_percentage' => 100.00,
                    'role'                 => 'ubo',
                    'is_pep'               => false,
                    'source_of_wealth'     => 'Long-term operator and founder of the company.',
                    'last_screened_at'     => now()->subDays(20),
                    'screening_result'     => 'clean',
                    'verified_at'          => now()->subDays(20),
                ],
            );
        }
    }

    // ================================================================
    // 11. Company documents (the vault)
    // ================================================================
    /** @param Collection<int,Company> $companies */
    private function seedCompanyDocuments(Collection $companies, User $admin): void
    {
        $plans = [
            // [type, status, expires_in_days, label]
            [DocumentType::TRADE_LICENSE,        CompanyDocument::STATUS_VERIFIED,   365, 'Trade License'],
            [DocumentType::TAX_CERTIFICATE,      CompanyDocument::STATUS_VERIFIED,   730, 'Tax Registration Certificate'],
            [DocumentType::INSURANCE_CERTIFICATE,CompanyDocument::STATUS_PENDING,    180, 'Insurance Certificate'],
            [DocumentType::AUDITED_FINANCIALS,   CompanyDocument::STATUS_REJECTED,   null,'2025 Audited Financials'],
            [DocumentType::ISO_9001,             CompanyDocument::STATUS_EXPIRED,    -10, 'ISO 9001:2015 Certificate'],
        ];

        foreach ($companies as $company) {
            if ($company->status !== CompanyStatus::ACTIVE) {
                continue;
            }

            $uploader = User::where('company_id', $company->id)->first() ?? $admin;

            foreach ($plans as $i => [$type, $status, $expiryOffset, $label]) {
                CompanyDocument::updateOrCreate(
                    ['company_id' => $company->id, 'type' => $type->value],
                    [
                        'label'             => $label,
                        'file_path'         => "demo/{$company->id}/{$type->value}.pdf",
                        'original_filename' => $type->value . '.pdf',
                        'file_size'         => 184_320 + $i * 1024,
                        'mime_type'         => 'application/pdf',
                        'status'            => $status,
                        'issued_at'         => now()->subDays(180)->toDateString(),
                        'expires_at'        => $expiryOffset !== null ? now()->addDays($expiryOffset)->toDateString() : null,
                        'rejection_reason'  => $status === CompanyDocument::STATUS_REJECTED
                            ? 'Document quality is too low to read company stamps. Please re-upload a clearer scan.'
                            : null,
                        'uploaded_by'       => $uploader->id,
                        'verified_by'       => in_array($status, [CompanyDocument::STATUS_VERIFIED, CompanyDocument::STATUS_REJECTED, CompanyDocument::STATUS_EXPIRED], true) ? $admin->id : null,
                        'verified_at'       => in_array($status, [CompanyDocument::STATUS_VERIFIED, CompanyDocument::STATUS_REJECTED, CompanyDocument::STATUS_EXPIRED], true) ? now()->subDays(7) : null,
                    ],
                );
            }
        }
    }

    // ================================================================
    // 12. Company insurances
    // ================================================================
    /** @param Collection<int,Company> $suppliers */
    private function seedCompanyInsurances(Company $buyer, Collection $suppliers, Company $logistics, User $admin): void
    {
        $defs = [
            // [company, type, insurer, coverage, status, expires_in]
            [$buyer,     'public_liability', 'AXA Gulf',           5_000_000, 'verified', 240],
            [$buyer,     'cargo',            'Oman Insurance',     2_000_000, 'verified', 180],
            [$logistics, 'cargo',            'RSA Insurance',      3_500_000, 'verified', 365],
            [$logistics, 'workers_comp',     'Tokio Marine',         750_000, 'verified', 200],
        ];
        foreach ($suppliers as $i => $sup) {
            $defs[] = [$sup, 'product_liability', 'AXA Gulf', 1_500_000 + $i * 250_000, $i === 4 ? 'pending' : 'verified', 200 + $i * 30];
        }

        foreach ($defs as $i => [$company, $type, $insurer, $coverage, $status, $expiresIn]) {
            $policyNumber = strtoupper(substr($company->registration_number, 0, 6)) . '-' . strtoupper($type) . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            CompanyInsurance::updateOrCreate(
                ['company_id' => $company->id, 'policy_number' => $policyNumber],
                [
                    'type'              => $type,
                    'insurer'           => $insurer,
                    'coverage_amount'   => $coverage,
                    'currency'          => 'AED',
                    'starts_at'         => now()->subDays(30)->toDateString(),
                    'expires_at'        => now()->addDays($expiresIn)->toDateString(),
                    'file_path'         => "insurances/{$company->id}/{$type}.pdf",
                    'original_filename' => "{$type}_policy.pdf",
                    'file_size'         => 145_300,
                    'mime_type'         => 'application/pdf',
                    'status'            => $status,
                    'verified_by'       => $status === 'verified' ? $admin->id : null,
                    'verified_at'       => $status === 'verified' ? now()->subDays(5) : null,
                ],
            );
        }
    }

    // ================================================================
    // 13. Sanctions screenings
    // ================================================================
    /** @param Collection<int,Company> $companies */
    private function seedSanctionsScreenings(Collection $companies, Company $sanctioned, User $admin): void
    {
        // Wipe the seeder's previous rows so re-runs converge.
        SanctionsScreening::query()->whereIn('company_id', $companies->pluck('id'))->delete();

        foreach ($companies as $company) {
            if ($company->id === $sanctioned->id) {
                continue;
            }
            // Two clean periodic checks per active company.
            for ($i = 0; $i < 2; $i++) {
                SanctionsScreening::create([
                    'company_id'       => $company->id,
                    'provider'         => 'opensanctions',
                    'query'            => $company->name,
                    'result'           => SanctionsScreening::RESULT_CLEAN,
                    'match_count'      => 0,
                    'matched_entities' => [],
                    'triggered_by'     => $admin->id,
                    'notes'            => 'Routine periodic screen — no matches.',
                    'created_at'       => now()->subDays(30 - $i * 14)->startOfDay(),
                    'updated_at'       => now()->subDays(30 - $i * 14)->startOfDay(),
                ]);
            }
        }

        // The sanctioned supplier — one direct hit row.
        SanctionsScreening::create([
            'company_id'       => $sanctioned->id,
            'provider'         => 'opensanctions',
            'query'            => $sanctioned->name,
            'result'           => SanctionsScreening::RESULT_HIT,
            'match_count'      => 1,
            'matched_entities' => [
                ['name' => 'RedFlag Trading FZE', 'list' => 'OFAC SDN', 'score' => 0.92],
            ],
            'triggered_by'     => $admin->id,
            'notes'            => 'Direct hit on OFAC SDN list. Company suspended.',
            'created_at'       => now()->subDays(2)->startOfDay(),
            'updated_at'       => now()->subDays(2)->startOfDay(),
        ]);
    }

    // ================================================================
    // 14. Credit scores (append-only audit trail)
    // ================================================================
    /** @param Collection<int,Company> $suppliers */
    private function seedCreditScores(Company $buyer, Collection $suppliers): void
    {
        $companies = collect([$buyer])->merge($suppliers);
        // Append-only model — wipe any previous demo rows so re-runs converge.
        CreditScore::query()->whereIn('company_id', $companies->pluck('id'))->delete();

        $rows = [
            [$buyer, 780, 'good',      'Strong cash flow, few late payments.'],
        ];
        foreach ($suppliers as $i => $sup) {
            $score = 720 - $i * 35;          // 720, 685, 650, 615, 580
            $band  = match (true) {
                $score >= 750 => 'excellent',
                $score >= 700 => 'good',
                $score >= 650 => 'fair',
                default       => 'poor',
            };
            $rows[] = [$sup, $score, $band, 'Trading history reviewed by AECB.'];
        }

        foreach ($rows as $i => [$company, $score, $band, $reason]) {
            CreditScore::create([
                'company_id'  => $company->id,
                'provider'    => 'aecb',
                'score'       => $score,
                'band'        => $band,
                'reasons'     => [$reason],
                'reported_at' => now()->subDays(15 - $i)->startOfDay(),
            ]);

            $company->forceFill([
                'latest_credit_score' => $score,
                'latest_credit_band'  => $band,
            ])->save();
        }
    }

    // ================================================================
    // 18, 19, 20, 21. ESG (questionnaires, statements, declarations, footprints)
    // ================================================================
    /** @param Collection<int,Company> $suppliers */
    private function seedEsg(Company $buyer, Collection $suppliers, User $admin): void
    {
        $companies = collect([$buyer])->merge($suppliers);
        $year      = (int) now()->format('Y');

        foreach ($companies as $i => $company) {
            $env = 70 + ($i * 4) % 25;
            $soc = 65 + ($i * 6) % 30;
            $gov = 75 + ($i * 5) % 20;
            $overall = (int) round(($env + $soc + $gov) / 3);
            $grade   = match (true) {
                $overall >= 85 => 'A',
                $overall >= 75 => 'B',
                $overall >= 65 => 'C',
                $overall >= 55 => 'D',
                default        => 'F',
            };

            EsgQuestionnaire::updateOrCreate(
                ['company_id' => $company->id],
                [
                    'environmental_score' => $env,
                    'social_score'        => $soc,
                    'governance_score'    => $gov,
                    'overall_score'       => $overall,
                    'grade'               => $grade,
                    'answers' => [
                        'env_energy_mix'     => 'majority_renewable',
                        'env_emissions_tracked' => 'reduction_target',
                        'env_iso14001'       => 'certified',
                        'soc_living_wage'    => 'all_employees',
                        'soc_health_safety'  => 'iso45001',
                        'soc_grievance'      => 'published_metrics',
                        'gov_anti_corruption'=> 'training_and_audit',
                        'gov_data_privacy'   => 'gdpr_compliant',
                        'gov_audited_financials' => 'big_four',
                    ],
                    'submitted_by' => $admin->id,
                    'submitted_at' => now()->subDays(20),
                ],
            );

            ModernSlaveryStatement::updateOrCreate(
                ['company_id' => $company->id, 'reporting_year' => $year],
                [
                    'statement'       => "{$company->name} prohibits any form of forced labour, child labour, and human trafficking across its operations and supply chain. We audit our tier-1 suppliers annually and require attestations from new partners.",
                    'controls'        => ['supplier_audits', 'whistleblower_channel', 'training_program'],
                    'board_approved'  => true,
                    'approved_at'     => now()->subDays(60)->toDateString(),
                    'signed_by_name'  => 'Khalid Hassan',
                    'signed_by_title' => 'Group CEO',
                ],
            );

            ConflictMineralsDeclaration::updateOrCreate(
                ['company_id' => $company->id, 'reporting_year' => $year],
                [
                    'tin_status'      => 'conflict_free',
                    'tungsten_status' => 'conflict_free',
                    'tantalum_status' => 'in_progress',
                    'gold_status'     => 'conflict_free',
                    'smelters'        => [
                        ['name' => 'Malaysia Smelting Corp', 'mineral' => 'tin',  'cid' => 'CID000292', 'country' => 'MY'],
                        ['name' => 'Wolfram Bergbau',         'mineral' => 'tungsten','cid' => 'CID002624','country' => 'AT'],
                    ],
                    'policy_url' => 'https://example.com/conflict-minerals-policy',
                ],
            );

            // One Scope-3 carbon entry per company.
            CarbonFootprint::firstOrCreate(
                [
                    'entity_type' => 'company',
                    'entity_id'   => $company->id,
                    'period_start'=> now()->startOfYear()->toDateString(),
                    'scope'       => 3,
                ],
                [
                    'co2e_kg'     => 18_500 + $i * 4_200,
                    'period_end'  => now()->toDateString(),
                    'source'      => 'manual_entry',
                    'metadata'    => ['notes' => 'Annual roll-up imported from spreadsheet.'],
                ],
            );
        }
    }

    // ================================================================
    // 7. Company suppliers — locked supplier
    // ================================================================
    /** @param Collection<int,Company> $suppliers */
    private function seedCompanySuppliers(Company $buyer, Collection $suppliers): void
    {
        $manager = User::where('email', 'manager@al-ahram.test')->first();

        // Lock the first supplier into the buyer's exclusive list.
        CompanySupplier::updateOrCreate(
            ['company_id' => $buyer->id, 'supplier_company_id' => $suppliers[0]->id],
            [
                'status'   => 'active',
                'notes'    => 'Captive supplier — internal use only.',
                'added_by' => $manager?->id,
            ],
        );
    }

    // ================================================================
    // 22 + 23. Products and variants
    // ================================================================
    /**
     * @param Collection<int,Company> $suppliers
     * @param array<string,Category>  $cats
     * @return Collection<int,Product>
     */
    private function seedProductsAndVariants(Collection $suppliers, array $cats): Collection
    {
        $defs = [
            // [supIdx, sku, name, name_ar, catKey, price, currency, unit, moq, stock, lead, hs]
            [0, 'EMI-PUMP-45L',   'Hydraulic Pump 45L/min',     'مضخة هيدروليك 45 لتر',   'industrial', 11650.00, 'AED', 'pcs',    1,   25, 14, '8413.50'],
            [0, 'EMI-MOT-22KW',   'Industrial Motor 22kW',      'محرك صناعي 22 كيلو وات', 'industrial', 14250.00, 'AED', 'pcs',    1,    8, 21, '8501.53'],
            [0, 'EMI-GEN-100KVA', 'Diesel Generator 100kVA',    'مولد ديزل 100 كيلوفولت', 'industrial', 42000.00, 'AED', 'units',  1,    5, 25, '8502.13'],

            [1, 'KHO-CEM-425',    'Portland Cement 42.5N',      'إسمنت بورتلاندي 42.5',  'construction',  850.00, 'AED', 'tons',   5,  500,  7, '2523.29'],
            [1, 'KHO-REBAR-16',   'Steel Rebar 16mm',           'حديد تسليح 16 مم',       'construction', 3200.00, 'AED', 'tons',   2,  250, 10, '7214.20'],

            [2, 'DBT-LAP-XPS',    'Dell XPS 15 Laptop',         'لاب توب ديل XPS 15',     'it-hardware', 6500.00, 'AED', 'pcs',    1,   30, 10, '8471.30'],
            [2, 'DBT-MON-27',     'Dell UltraSharp 27" 4K',     'شاشة ديل 27 بوصة 4K',   'it-hardware', 3100.00, 'AED', 'pcs',    1,   50,  8, '8528.52'],
            [2, 'DBT-SRV-R750',   'Dell PowerEdge R750 Server', 'سيرفر ديل R750',         'it-hardware',28500.00, 'AED', 'units',  1,    6, 20, '8471.50'],

            [3, 'GLF-DESK-EU',    'Ergonomic Sit-Stand Desk',   'مكتب إيرغونومي',         'office',     1750.00, 'AED', 'sets',   1,   60,  5, '9403.30'],
            [3, 'GLF-CHAIR-MESH', 'Mesh Office Chair',          'كرسي مكتب شبكي',         'office',      680.00, 'AED', 'pcs',    1,  200,  4, '9401.30'],

            [4, 'MED-MON-VITAL',  'Patient Vital Monitor',      'جهاز مراقبة حيوية',      'medical',   12500.00, 'AED', 'units',  1,   20, 14, '9018.19'],
            [4, 'MED-PPE-N95',    'N95 Respirator Mask',        'كمامة N95',              'medical',       6.50, 'AED', 'pcs',  100,12000,  5, '6307.90'],
        ];

        $out = collect();
        foreach ($defs as $d) {
            [$supIdx, $sku, $name, $nameAr, $catKey, $price, $cur, $unit, $moq, $stock, $lead, $hs] = $d;

            $product = Product::updateOrCreate(
                ['company_id' => $suppliers[$supIdx]->id, 'sku' => $sku],
                [
                    'category_id'    => $cats[$catKey]->id,
                    'hs_code'        => $hs,
                    'name'           => $name,
                    'name_ar'        => $nameAr,
                    'description'    => 'Catalog product available for direct purchase via Buy-Now or via RFQ.',
                    'base_price'     => $price,
                    'currency'       => $cur,
                    'unit'           => $unit,
                    'min_order_qty'  => $moq,
                    'stock_qty'      => $stock,
                    'lead_time_days' => $lead,
                    'images'         => [],
                    'specs'          => [
                        ['key' => 'origin',   'value' => 'UAE'],
                        ['key' => 'warranty', 'value' => '1 year'],
                    ],
                    'is_active'      => true,
                ],
            );
            $out->push($product);
        }

        // Variants on the laptop product (3 tiers).
        $laptop = $out->first(fn (Product $p) => $p->sku === 'DBT-LAP-XPS');
        if ($laptop) {
            $variants = [
                ['DBT-LAP-XPS-BASE', 'i5 / 16GB / 512GB',   ['cpu' => 'i5', 'ram' => '16GB', 'ssd' => '512GB'],   0.00,    20],
                ['DBT-LAP-XPS-PLUS', 'i7 / 32GB / 1TB',     ['cpu' => 'i7', 'ram' => '32GB', 'ssd' => '1TB'],     1500.00, 10],
                ['DBT-LAP-XPS-PRO',  'i9 / 64GB / 2TB',     ['cpu' => 'i9', 'ram' => '64GB', 'ssd' => '2TB'],     3500.00,  5],
            ];
            foreach ($variants as [$sku, $name, $attrs, $modifier, $stock]) {
                ProductVariant::updateOrCreate(
                    ['product_id' => $laptop->id, 'sku' => $sku],
                    [
                        'name'           => $name,
                        'attributes'     => $attrs,
                        'price_modifier' => $modifier,
                        'stock_qty'      => $stock,
                        'is_active'      => true,
                    ],
                );
            }
        }

        // Variants on the mesh chair (color).
        $chair = $out->first(fn (Product $p) => $p->sku === 'GLF-CHAIR-MESH');
        if ($chair) {
            foreach ([['BLK', 'Black', 0.00, 120], ['GRY', 'Grey', 0.00, 60], ['BLU', 'Blue', 30.00, 20]] as [$code, $color, $mod, $stock]) {
                ProductVariant::updateOrCreate(
                    ['product_id' => $chair->id, 'sku' => "GLF-CHAIR-MESH-{$code}"],
                    [
                        'name'           => "{$color} Mesh",
                        'attributes'     => ['color' => strtolower($color)],
                        'price_modifier' => $mod,
                        'stock_qty'      => $stock,
                        'is_active'      => true,
                    ],
                );
            }
        }

        return $out;
    }

    // ================================================================
    // 24 + 25. Carts and cart items
    // ================================================================
    /** @param Collection<int,Product> $products */
    private function seedCartsAndCartItems(Company $buyer, Collection $products): void
    {
        $ahmed = User::where('email', 'buyer@al-ahram.test')->first();
        if (!$ahmed) {
            return;
        }

        $cart = Cart::updateOrCreate(
            ['user_id' => $ahmed->id, 'status' => Cart::STATUS_OPEN],
            [
                'company_id' => $buyer->id,
            ],
        );

        // Wipe + recreate items so re-running the seeder converges.
        $cart->items()->delete();

        $picks = [
            ['DBT-LAP-XPS',    2],
            ['DBT-MON-27',     2],
            ['GLF-CHAIR-MESH', 4],
        ];
        foreach ($picks as [$sku, $qty]) {
            $product = $products->firstWhere('sku', $sku);
            if (!$product) {
                continue;
            }
            CartItem::create([
                'cart_id'             => $cart->id,
                'product_id'          => $product->id,
                'product_variant_id'  => null,
                'supplier_company_id' => $product->company_id,
                'quantity'            => $qty,
                'unit_price'          => $product->base_price,
                'currency'            => $product->currency,
                'name_snapshot'       => $product->name,
                'attributes_snapshot' => null,
            ]);
        }
    }

    // ================================================================
    // 26. Purchase requests — every status
    // ================================================================
    /**
     * @param Collection<int,Branch> $branches
     * @param array<string,Category> $cats
     * @return Collection<int,PurchaseRequest>
     */
    private function seedPurchaseRequests(Company $buyer, Collection $branches, array $cats): Collection
    {
        $ahmed   = User::where('email', 'buyer@al-ahram.test')->first();
        $manager = User::where('email', 'manager@al-ahram.test')->first();

        $defs = [
            ['Hydraulic Pumps for Plant A', PurchaseRequestStatus::DRAFT,            $cats['industrial'],   125000, '10 hydraulic pumps for the Plant A maintenance cycle.',                           [['name'=>'Hydraulic Pump','qty'=>10,'unit'=>'pcs','price'=>11650,'spec'=>'45 L/min']],            14],
            ['Office Workstations Q2',      PurchaseRequestStatus::SUBMITTED,        $cats['office'],       340000, 'Modular workstations + ergonomic chairs for Dubai HQ floor 18.',                  [['name'=>'Workstation','qty'=>200,'unit'=>'sets','price'=>1700,'spec'=>'Sit/stand']],              21],
            ['HVAC Retrofit',               PurchaseRequestStatus::PENDING_APPROVAL, $cats['construction'], 320000, 'Industrial HVAC retrofit — 5 rooftop units, ducting and installation.',          [['name'=>'Rooftop HVAC','qty'=>5,'unit'=>'units','price'=>55000,'spec'=>'20-ton, R-32']],          45],
            ['Copper Wire 16mm',            PurchaseRequestStatus::APPROVED,         $cats['electronics'],   95000, 'Copper wire 16mm for the electrical fit-out.',                                    [['name'=>'Copper Wire 16mm','qty'=>10,'unit'=>'tons','price'=>8500,'spec'=>'IEC 60228']],          28],
            ['Vehicle Fleet Spares',        PurchaseRequestStatus::REJECTED,         $cats['industrial'],    72000, 'Spare parts and lubricants for the company fleet.',                              [['name'=>'Brake Pad Set','qty'=>60,'unit'=>'sets','price'=>280,'spec'=>'OEM equiv.']],            10],
        ];

        $prs = collect();
        foreach ($defs as $i => $d) {
            [$title, $status, $cat, $budget, $desc, $items, $daysAhead] = $d;

            $createdAt = now()->subDays(20 - $i * 2);
            $approvalHistory = [];
            if (in_array($status->value, ['submitted', 'pending_approval', 'approved', 'rejected'], true) && $ahmed) {
                $approvalHistory[] = ['action' => 'Submitted for approval', 'by' => $ahmed->full_name, 'at' => $createdAt->copy()->addHours(4)->toDateTimeString()];
            }
            if ($status === PurchaseRequestStatus::APPROVED && $manager) {
                $approvalHistory[] = ['action' => 'Approved by company manager', 'by' => $manager->full_name, 'at' => $createdAt->copy()->addHours(8)->toDateTimeString()];
            }
            if ($status === PurchaseRequestStatus::REJECTED && $manager) {
                $approvalHistory[] = ['action' => 'Rejected by company manager', 'by' => $manager->full_name, 'at' => $createdAt->copy()->addHours(8)->toDateTimeString(), 'note' => 'Out of approved Q2 budget — defer to Q3.'];
            }

            $pr = PurchaseRequest::updateOrCreate(
                ['title' => $title],
                [
                    'description'      => $desc,
                    'company_id'       => $buyer->id,
                    'branch_id'        => $branches[$i % 2]->id,
                    'buyer_id'         => $ahmed?->id,
                    'category_id'      => $cat->id,
                    'status'           => $status->value,
                    'items'            => $items,
                    'budget'           => $budget,
                    'currency'         => 'AED',
                    'delivery_location'=> [
                        'address' => 'Dubai Silicon Oasis, Building 12',
                        'city'    => 'Dubai',
                        'country' => 'UAE',
                    ],
                    'required_date'    => now()->addDays($daysAhead)->toDateString(),
                    'approval_history' => $approvalHistory,
                    'rfq_generated'    => $status === PurchaseRequestStatus::APPROVED,
                ],
            );
            $pr->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt->copy()->addHours(8)])->saveQuietly();
            $prs->push($pr);
        }

        return $prs;
    }

    // ================================================================
    // 27. RFQs (with auction)
    // ================================================================
    /**
     * @param Collection<int,Branch>          $branches
     * @param Collection<int,PurchaseRequest> $prs
     * @param array<string,Category>          $cats
     * @return Collection<int,Rfq>
     */
    private function seedRfqs(Company $buyer, Collection $branches, Collection $prs, Company $logistics, Company $clearance, Company $service, array $cats): Collection
    {
        $supplierIds = $this->suppliers->pluck('id')->all();

        $defs = [
            // [title, type, status, target_role, target_ids, cat, budget, deadline_days, prIdx, items]
            ['Copper Wire 16mm — Electrical', RfqType::SUPPLIER, RfqStatus::OPEN,      UserRole::SUPPLIER, $supplierIds, $cats['electronics'],  95000, 15, 3,
                [['name'=>'Copper Wire 16mm','qty'=>10,'unit'=>'tons','specs'=>['IEC 60228 compliant','99.9% purity','500m spools']]]],

            ['Office Workstations Bulk Order', RfqType::SUPPLIER, RfqStatus::OPEN,     UserRole::SUPPLIER, $supplierIds, $cats['office'],       340000, 18, 1,
                [['name'=>'Modular Workstation','qty'=>200,'unit'=>'sets','specs'=>['Sit/stand desk','Mesh chair','5-yr warranty']]]],

            ['HVAC Retrofit — Closed', RfqType::SUPPLIER, RfqStatus::CLOSED,           UserRole::SUPPLIER, $supplierIds, $cats['construction'], 320000, -3, 2,
                [['name'=>'Rooftop HVAC','qty'=>5,'unit'=>'units','specs'=>['20-ton','R-32 refrigerant','BACnet']]]],

            ['Industrial Pumps — Draft RFQ', RfqType::SUPPLIER, RfqStatus::DRAFT,      UserRole::SUPPLIER, $supplierIds, $cats['industrial'],   125000, 30, 0,
                [['name'=>'Hydraulic Pump','qty'=>10,'unit'=>'pcs','specs'=>['45 L/min','Continuous duty']]]],

            ['Cancelled Steel RFQ', RfqType::SUPPLIER, RfqStatus::CANCELLED,           UserRole::SUPPLIER, $supplierIds, $cats['construction'], 220000, 5, 4,
                [['name'=>'Steel Rebar 16mm','qty'=>200,'unit'=>'tons','specs'=>['B500B','Mill cert']]]],

            ['Logistics — Sea Freight Dubai→Jeddah', RfqType::LOGISTICS, RfqStatus::OPEN, UserRole::LOGISTICS, [$logistics->id], $cats['logistics-svc'], 18000, 10, 1,
                [['name'=>'40ft container','qty'=>3,'unit'=>'containers','specs'=>['Door-to-door','Marine insurance']]]],

            ['Customs — JAFZA Import Clearance', RfqType::CLEARANCE, RfqStatus::OPEN, UserRole::CLEARANCE, [$clearance->id], $cats['clearance-svc'], 4500, 7, 1,
                [['name'=>'Clearance batch','qty'=>1,'unit'=>'job','specs'=>['HS classification','Duty calculation','Inspection support']]]],

            ['Service — HVAC Installation', RfqType::SERVICE_PROVIDER, RfqStatus::OPEN, UserRole::SERVICE_PROVIDER, [$service->id], $cats['construction'], 65000, 14, 2,
                [['name'=>'Installation service','qty'=>1,'unit'=>'job','specs'=>['Licensed crew','Commissioning included']]]],

            ['Sales Offer — Excess Office Stock', RfqType::SALES_OFFER, RfqStatus::OPEN, UserRole::BUYER, [], $cats['office'], 50000, 21, 1,
                [['name'=>'Office chairs','qty'=>120,'unit'=>'pcs','specs'=>['New, in box','Black mesh']]]],
        ];

        $rfqs = collect();
        foreach ($defs as $i => $d) {
            [$title, $type, $status, $targetRole, $targetIds, $cat, $budget, $deadlineDays, $prIdx, $items] = $d;

            $rfq = Rfq::updateOrCreate(
                ['title' => $title],
                [
                    'rfq_number'         => sprintf('RFQ-%s-%04d', date('Y'), 1001 + $i),
                    'description'        => 'Demo RFQ — ' . $title,
                    'company_id'         => $buyer->id,
                    'branch_id'          => $branches[$i % 2]->id,
                    'purchase_request_id'=> $prs[$prIdx]->id,
                    'type'               => $type->value,
                    'target_role'        => $targetRole->value,
                    'target_company_ids' => $targetIds,
                    'status'             => $status->value,
                    'items'              => $items,
                    'budget'             => $budget,
                    'currency'           => 'AED',
                    'deadline'           => now()->addDays($deadlineDays),
                    'delivery_location'  => 'Dubai Silicon Oasis, Building 12, UAE',
                    'is_anonymous'       => false,
                    'category_id'        => $cat->id,
                    'is_auction'         => false,
                ],
            );
            $rfqs->push($rfq);
        }

        // Live reverse-auction RFQ.
        $auction = Rfq::updateOrCreate(
            ['title' => 'Live Auction — IT Hardware Refresh'],
            [
                'rfq_number'         => sprintf('RFQ-%s-%04d', date('Y'), 9001),
                'description'        => '50 laptops + 50 monitors. Live reverse auction — lowest bid wins.',
                'company_id'         => $buyer->id,
                'branch_id'          => $branches[0]->id,
                'purchase_request_id'=> $prs[1]->id,
                'type'               => RfqType::SUPPLIER->value,
                'target_role'        => UserRole::SUPPLIER->value,
                'target_company_ids' => $supplierIds,
                'status'             => RfqStatus::OPEN->value,
                'items'              => [
                    ['name' => 'Dell XPS 15 Laptop',          'qty' => 50, 'unit' => 'pcs', 'specs' => ['i7','32GB RAM','1TB SSD','3-yr warranty']],
                    ['name' => 'Dell UltraSharp 27" Monitor', 'qty' => 50, 'unit' => 'pcs', 'specs' => ['4K IPS','USB-C','Height-adjustable']],
                ],
                'budget'             => 450000,
                'currency'           => 'AED',
                'deadline'           => now()->addDays(3),
                'delivery_location'  => 'Dubai Silicon Oasis, Building 12, UAE',
                'is_anonymous'       => false,
                'category_id'        => $cats['it-hardware']->id,
                'is_auction'         => true,
                'auction_starts_at'  => now()->subHours(2),
                'auction_ends_at'    => now()->addHours(48),
                'reserve_price'      => 380000,
                'bid_decrement'      => 1000,
                'anti_snipe_seconds' => 120,
            ],
        );
        $rfqs->push($auction);

        return $rfqs;
    }

    // ================================================================
    // 28. Bids — every status across the open RFQs
    // ================================================================
    /**
     * @param Collection<int,Rfq> $rfqs
     * @return Collection<int,Bid>
     */
    private function seedBids(Collection $rfqs, Company $logistics, Company $clearance, Company $service): Collection
    {
        $out = collect();

        $supplierContacts = $this->suppliers->map(
            fn (Company $c) => User::where('company_id', $c->id)->where('role', UserRole::SUPPLIER->value)->first(),
        );

        // RFQ index → list of [supplierIdx, status, priceFactor]
        $bidPlans = [
            0 => [ // Copper Wire — open
                [0, BidStatus::SUBMITTED,    0.96],
                [1, BidStatus::UNDER_REVIEW, 0.99],
                [2, BidStatus::REJECTED,     1.05],
            ],
            1 => [ // Office Workstations — open
                [3, BidStatus::ACCEPTED,     0.92],
                [2, BidStatus::SUBMITTED,    0.95],
                [4, BidStatus::WITHDRAWN,    1.02],
            ],
            2 => [ // HVAC — closed (historical)
                [1, BidStatus::ACCEPTED,     0.97],
                [3, BidStatus::REJECTED,     1.04],
            ],
            3 => [ // Industrial Pumps — DRAFT RFQ → draft bid
                [0, BidStatus::DRAFT,        0.98],
            ],
        ];

        foreach ($bidPlans as $rfqIdx => $plans) {
            $rfq = $rfqs[$rfqIdx];
            foreach ($plans as [$supIdx, $status, $factor]) {
                $sup     = $this->suppliers[$supIdx];
                $contact = $supplierContacts[$supIdx];
                $price   = round((float) $rfq->budget * $factor, 2);

                $bid = Bid::updateOrCreate(
                    ['rfq_id' => $rfq->id, 'company_id' => $sup->id],
                    [
                        'provider_id'        => $contact?->id,
                        'status'             => $status->value,
                        'price'              => $price,
                        'currency'           => 'AED',
                        'delivery_time_days' => 12 + $supIdx * 3,
                        'payment_terms'      => '30% advance, 50% on production, 20% on delivery',
                        'payment_schedule'   => [
                            ['milestone' => 'advance',    'percentage' => 30],
                            ['milestone' => 'production', 'percentage' => 50],
                            ['milestone' => 'delivery',   'percentage' => 20],
                        ],
                        'items' => collect($rfq->items)->map(fn ($it) => [
                            'name'       => $it['name'] ?? 'Item',
                            'qty'        => $it['qty'] ?? 1,
                            'unit_price' => round($price / max(count($rfq->items), 1) / max((int) ($it['qty'] ?? 1), 1), 2),
                        ])->toArray(),
                        'validity_date' => now()->addDays(30),
                        'is_anonymous'  => false,
                        'attachments'   => [],
                        'ai_score'      => [
                            'overall'    => 80 + $supIdx * 3,
                            'compliance' => 88 + $supIdx,
                            'rating'     => round(4.4 + (mt_rand(0, 5) / 10), 1),
                            'notes'      => 'Strong delivery record and competitive payment terms.',
                        ],
                        'notes' => 'Includes installation support and 1-year warranty.',
                    ],
                );
                $out->push($bid);
            }
        }

        // Logistics RFQ
        $logisticsRfq = $rfqs->firstWhere('type', RfqType::LOGISTICS);
        if ($logisticsRfq) {
            $contact = User::where('company_id', $logistics->id)->first();
            $out->push(Bid::updateOrCreate(
                ['rfq_id' => $logisticsRfq->id, 'company_id' => $logistics->id],
                [
                    'provider_id'        => $contact?->id,
                    'status'             => BidStatus::SUBMITTED->value,
                    'price'              => 16500,
                    'currency'           => 'AED',
                    'delivery_time_days' => 10,
                    'payment_terms'      => '50% on booking, 50% on delivery',
                    'payment_schedule'   => [
                        ['milestone' => 'booking',  'percentage' => 50],
                        ['milestone' => 'delivery', 'percentage' => 50],
                    ],
                    'items' => [['name' => '40ft container', 'qty' => 3, 'unit_price' => 5500]],
                    'validity_date' => now()->addDays(14),
                    'is_anonymous'  => false,
                    'ai_score'      => ['overall' => 86, 'compliance' => 90, 'rating' => 4.6],
                    'notes'         => 'Door-to-door including marine insurance.',
                ],
            ));
        }

        // Clearance RFQ
        $clearanceRfq = $rfqs->firstWhere('type', RfqType::CLEARANCE);
        if ($clearanceRfq) {
            $contact = User::where('company_id', $clearance->id)->first();
            $out->push(Bid::updateOrCreate(
                ['rfq_id' => $clearanceRfq->id, 'company_id' => $clearance->id],
                [
                    'provider_id'        => $contact?->id,
                    'status'             => BidStatus::ACCEPTED->value,
                    'price'              => 4200,
                    'currency'           => 'AED',
                    'delivery_time_days' => 5,
                    'payment_terms'      => '100% on completion',
                    'payment_schedule'   => [['milestone' => 'completion', 'percentage' => 100]],
                    'items'              => [['name' => 'Clearance batch', 'qty' => 1, 'unit_price' => 4200]],
                    'validity_date'      => now()->addDays(14),
                    'ai_score'           => ['overall' => 92, 'compliance' => 95, 'rating' => 4.8],
                    'notes'              => 'Includes HS classification and duty calculation.',
                ],
            ));
        }

        // Service-provider RFQ
        $serviceRfq = $rfqs->firstWhere('type', RfqType::SERVICE_PROVIDER);
        if ($serviceRfq) {
            $contact = User::where('company_id', $service->id)->first();
            $out->push(Bid::updateOrCreate(
                ['rfq_id' => $serviceRfq->id, 'company_id' => $service->id],
                [
                    'provider_id'        => $contact?->id,
                    'status'             => BidStatus::UNDER_REVIEW->value,
                    'price'              => 62000,
                    'currency'           => 'AED',
                    'delivery_time_days' => 14,
                    'payment_terms'      => '40% advance, 60% on commissioning',
                    'payment_schedule'   => [
                        ['milestone' => 'advance',       'percentage' => 40],
                        ['milestone' => 'commissioning', 'percentage' => 60],
                    ],
                    'items'         => [['name' => 'Installation service', 'qty' => 1, 'unit_price' => 62000]],
                    'validity_date' => now()->addDays(21),
                    'ai_score'      => ['overall' => 84, 'compliance' => 88, 'rating' => 4.5],
                    'notes'         => 'Licensed crew, full commissioning report.',
                ],
            ));
        }

        // Auction bids — two suppliers undercutting each other
        $auctionRfq = $rfqs->last();
        if ($auctionRfq && $auctionRfq->is_auction) {
            $out->push(Bid::updateOrCreate(
                ['rfq_id' => $auctionRfq->id, 'company_id' => $this->suppliers[2]->id],
                [
                    'provider_id'        => $supplierContacts[2]?->id,
                    'status'             => BidStatus::SUBMITTED->value,
                    'price'              => 432000,
                    'currency'           => 'AED',
                    'delivery_time_days' => 10,
                    'payment_terms'      => '100% on delivery',
                    'payment_schedule'   => [['milestone' => 'delivery', 'percentage' => 100]],
                    'items'              => [
                        ['name' => 'Dell XPS 15 Laptop',          'qty' => 50, 'unit_price' => 5500],
                        ['name' => 'Dell UltraSharp 27" Monitor', 'qty' => 50, 'unit_price' => 3140],
                    ],
                    'validity_date' => now()->addDays(7),
                    'ai_score'      => ['overall' => 88, 'compliance' => 92, 'rating' => 4.7],
                    'notes'         => 'Auction bid — round 1.',
                ],
            ));
            $out->push(Bid::updateOrCreate(
                ['rfq_id' => $auctionRfq->id, 'company_id' => $this->suppliers[3]->id],
                [
                    'provider_id'        => $supplierContacts[3]?->id,
                    'status'             => BidStatus::SUBMITTED->value,
                    'price'              => 425000,
                    'currency'           => 'AED',
                    'delivery_time_days' => 12,
                    'payment_terms'      => '50% advance, 50% on delivery',
                    'payment_schedule'   => [
                        ['milestone' => 'advance',  'percentage' => 50],
                        ['milestone' => 'delivery', 'percentage' => 50],
                    ],
                    'items' => [
                        ['name' => 'Dell XPS 15 Laptop',          'qty' => 50, 'unit_price' => 5400],
                        ['name' => 'Dell UltraSharp 27" Monitor', 'qty' => 50, 'unit_price' => 3100],
                    ],
                    'validity_date' => now()->addDays(7),
                    'ai_score'      => ['overall' => 90, 'compliance' => 93, 'rating' => 4.8],
                    'notes'         => 'Auction bid — undercut round 1.',
                ],
            ));
        }

        return $out;
    }

    // ================================================================
    // 30. Negotiation messages — multi-round counter-offers
    // ================================================================
    /** @param Collection<int,Bid> $bids */
    private function seedNegotiationMessages(Collection $bids): void
    {
        $target = $bids->first(fn (Bid $b) => $b->status === BidStatus::UNDER_REVIEW);
        if (!$target) {
            return;
        }
        $ahmed = User::where('email', 'buyer@al-ahram.test')->first();
        $supplierContact = User::where('company_id', $target->company_id)->where('role', UserRole::SUPPLIER->value)->first();
        if (!$ahmed || !$supplierContact) {
            return;
        }

        NegotiationMessage::firstOrCreate(
            ['bid_id' => $target->id, 'sender_id' => $ahmed->id, 'kind' => NegotiationMessage::KIND_TEXT, 'body' => 'Hi — can you do better on the unit price? Our budget for this round is tight.'],
            ['sender_side' => 'buyer', 'round_status' => NegotiationMessage::ROUND_OPEN],
        );
        NegotiationMessage::firstOrCreate(
            ['bid_id' => $target->id, 'sender_id' => $supplierContact->id, 'kind' => NegotiationMessage::KIND_COUNTER_OFFER, 'round_number' => 1],
            [
                'sender_side'  => 'supplier',
                'body'         => 'Counter offer round 1',
                'offer'        => [
                    'amount'        => (float) $target->price * 0.96,
                    'currency'      => 'AED',
                    'delivery_days' => 11,
                    'payment_terms' => '30% advance, 50% production, 20% delivery',
                    'reason'        => 'Reduced margin to stay within budget',
                ],
                'round_status' => NegotiationMessage::ROUND_COUNTERED,
            ],
        );
        NegotiationMessage::firstOrCreate(
            ['bid_id' => $target->id, 'sender_id' => $ahmed->id, 'kind' => NegotiationMessage::KIND_COUNTER_OFFER, 'round_number' => 2],
            [
                'sender_side'  => 'buyer',
                'body'         => 'Counter offer round 2',
                'offer'        => [
                    'amount'        => (float) $target->price * 0.93,
                    'currency'      => 'AED',
                    'delivery_days' => 10,
                    'payment_terms' => '20% advance, 60% production, 20% delivery',
                    'reason'        => 'Need a slightly better price to approve internally',
                ],
                'round_status' => NegotiationMessage::ROUND_OPEN,
            ],
        );
        NegotiationMessage::firstOrCreate(
            ['bid_id' => $target->id, 'sender_id' => $supplierContact->id, 'kind' => NegotiationMessage::KIND_TEXT, 'body' => 'Reviewing internally — will respond within 24 hours.'],
            ['sender_side' => 'supplier', 'round_status' => NegotiationMessage::ROUND_OPEN],
        );
    }

    // ================================================================
    // 31. Contracts — every status, with structured terms + signatures
    // ================================================================
    /**
     * @param Collection<int,PurchaseRequest> $prs
     * @param Collection<int,Branch>          $branches
     * @return Collection<int,Contract>
     */
    private function seedContracts(Company $buyer, Company $logistics, Collection $prs, Collection $branches): Collection
    {
        $defs = [
            ['CNT-2026-0001', 'Copper Wire 16mm — Electrical',  ContractStatus::ACTIVE,             0,  95000, -30, 15,  true],
            ['CNT-2026-0002', 'Office Workstations Bulk Order', ContractStatus::ACTIVE,             3, 340000, -10, 35,  true],
            ['CNT-2026-0003', 'IT Hardware Refresh',            ContractStatus::SIGNED,             2, 125000, -2,  60,  true],
            ['CNT-2026-0004', 'HVAC Retrofit',                  ContractStatus::PENDING_SIGNATURES, 1, 320000,  0,  90,  false],
            ['CNT-2026-0005', 'Office Equipment Q1',            ContractStatus::COMPLETED,          3,  68000, -90, -10, true],
            ['CNT-2026-0006', 'Steel Procurement',              ContractStatus::TERMINATED,         1, 220000, -60, -20, true],
            ['CNT-2026-0007', 'Cancelled Marketing Print',      ContractStatus::CANCELLED,          3,  45000, -25,  5,  false],
            ['CNT-2026-0008', 'Hydraulic Pumps Plant A',        ContractStatus::DRAFT,              0, 125000,  0,  45,  false],
        ];

        $contracts = collect();
        foreach ($defs as $i => $d) {
            [$number, $title, $status, $supIdx, $total, $startOffset, $endOffset, $signedBoth] = $d;
            $supplier = $this->suppliers[$supIdx];

            $signatures = [];
            if ($signedBoth) {
                $signedAt = now()->addDays($startOffset)->toDateTimeString();
                $signatures = [
                    ['company_id' => $buyer->id,    'signed_at' => $signedAt],
                    ['company_id' => $supplier->id, 'signed_at' => $signedAt],
                ];
            } elseif ($status === ContractStatus::PENDING_SIGNATURES) {
                $signatures = [
                    ['company_id' => $buyer->id, 'signed_at' => now()->subDay()->toDateTimeString()],
                ];
            }

            $progress = match ($status) {
                ContractStatus::DRAFT              => null,
                ContractStatus::PENDING_SIGNATURES => 0,
                ContractStatus::SIGNED             => 5,
                ContractStatus::ACTIVE             => 55,
                ContractStatus::COMPLETED          => 100,
                ContractStatus::TERMINATED         => 40,
                ContractStatus::CANCELLED          => 0,
            };

            $progressUpdates = $status === ContractStatus::ACTIVE ? [
                ['at' => now()->subDays(10)->toDateTimeString(), 'by' => 'supplier', 'percent' => 25, 'note' => 'Production started — raw materials sourced.'],
                ['at' => now()->subDays(4)->toDateTimeString(),  'by' => 'supplier', 'percent' => 55, 'note' => 'Halfway through production. Photos uploaded.'],
            ] : null;

            $supplierDocs = $status === ContractStatus::ACTIVE ? [
                ['name' => 'Production photo 1.jpg',      'path' => "contracts/demo/{$number}/prod1.jpg",  'size' => 184320, 'uploaded_at' => now()->subDays(4)->toDateTimeString()],
                ['name' => 'Quality Inspection Cert.pdf', 'path' => "contracts/demo/{$number}/qc.pdf",     'size' => 92160,  'uploaded_at' => now()->subDays(2)->toDateTimeString()],
            ] : null;

            $contract = Contract::updateOrCreate(
                ['contract_number' => $number],
                [
                    'title'               => $title,
                    'description'         => 'Procurement contract for ' . $title,
                    'purchase_request_id' => $prs[$i % $prs->count()]->id,
                    'buyer_company_id'    => $buyer->id,
                    'branch_id'           => $branches[$i % 2]->id,
                    'status'              => $status->value,
                    'parties'             => [
                        ['company_id' => $buyer->id,    'name' => $buyer->name,    'role' => 'buyer'],
                        ['company_id' => $supplier->id, 'name' => $supplier->name, 'role' => 'supplier'],
                    ],
                    'amounts' => [
                        'subtotal' => $total,
                        'vat'      => $total * 0.05,
                        'total'    => $total * 1.05,
                    ],
                    'total_amount'        => $total,
                    'currency'            => 'AED',
                    'payment_schedule'    => [
                        ['milestone' => 'advance',    'percentage' => 30, 'amount' => $total * 0.30],
                        ['milestone' => 'production', 'percentage' => 50, 'amount' => $total * 0.50],
                        ['milestone' => 'delivery',   'percentage' => 20, 'amount' => $total * 0.20],
                    ],
                    'signatures'          => $signatures,
                    'terms'               => json_encode($this->buildContractTerms($total), JSON_UNESCAPED_UNICODE),
                    'start_date'          => now()->addDays($startOffset)->toDateString(),
                    'end_date'            => now()->addDays($endOffset)->toDateString(),
                    'version'             => 1,
                    'progress_percentage' => $progress,
                    'progress_updates'    => $progressUpdates,
                    'supplier_documents'  => $supplierDocs,
                ],
            );
            $contracts->push($contract);
        }

        return $contracts;
    }

    /** @return array<int,array{title:string,items:array<int,string>}> */
    private function buildContractTerms(float $total): array
    {
        return [
            ['title' => 'Product Specifications', 'items' => [
                'Goods as per RFQ specification',
                'Quantity and unit price as per accepted bid',
                'Original manufacturer packaging',
            ]],
            ['title' => 'Delivery Terms', 'items' => [
                'Delivery Location: Dubai Silicon Oasis, UAE',
                'Delivery within agreed timeline (per shipment record)',
                'Incoterms: DAP (Delivered at Place)',
            ]],
            ['title' => 'Quality Assurance', 'items' => [
                'Quality certificates required before shipment',
                'Inspection rights reserved for buyer',
                'Warranty period: 1–3 years from delivery date',
            ]],
            ['title' => 'Payment Terms', 'items' => [
                '30% advance payment upon contract signing',
                '50% upon production completion verification',
                '20% upon successful delivery and inspection',
                'Total contract value: AED ' . number_format($total),
            ]],
            ['title' => 'Dispute Resolution', 'items' => [
                'Governed by UAE Commercial Law',
                'Disputes handled through TriLink platform mediation',
                'Arbitration in Dubai, UAE if mediation fails',
            ]],
        ];
    }

    // ================================================================
    // 29 + 32. Contract amendments + version snapshots
    // ================================================================
    /** @param Collection<int,Contract> $contracts */
    private function seedContractAmendmentsAndVersions(Collection $contracts): void
    {
        $active = $contracts->first(fn (Contract $c) => $c->status === ContractStatus::ACTIVE);
        if (!$active) {
            return;
        }
        $ahmed   = User::where('email', 'buyer@al-ahram.test')->first();
        $manager = User::where('email', 'manager@al-ahram.test')->first();

        ContractAmendment::firstOrCreate(
            ['contract_id' => $active->id, 'reason' => 'Delivery delay due to upstream material availability.'],
            [
                'from_version' => 1,
                'changes'      => [
                    ['field' => 'end_date', 'from' => $active->end_date->toDateString(), 'to' => $active->end_date->copy()->addDays(14)->toDateString()],
                ],
                'status'           => AmendmentStatus::PENDING_APPROVAL->value,
                'approval_history' => [
                    ['action' => 'submitted', 'by' => $ahmed?->full_name, 'at' => now()->subDay()->toDateTimeString()],
                ],
                'requested_by'     => $ahmed?->id,
            ],
        );

        ContractAmendment::firstOrCreate(
            ['contract_id' => $active->id, 'reason' => 'Price uplift agreed for additional inspection scope.'],
            [
                'from_version' => 1,
                'changes'      => [
                    ['field' => 'total_amount', 'from' => (float) $active->total_amount, 'to' => (float) $active->total_amount + 5000],
                ],
                'status'           => AmendmentStatus::APPROVED->value,
                'approval_history' => [
                    ['action' => 'submitted', 'by' => $ahmed?->full_name,   'at' => now()->subDays(3)->toDateTimeString()],
                    ['action' => 'approved',  'by' => $manager?->full_name, 'at' => now()->subDays(2)->toDateTimeString()],
                ],
                'requested_by'     => $ahmed?->id,
            ],
        );

        ContractVersion::firstOrCreate(
            ['contract_id' => $active->id, 'version' => 1],
            [
                'snapshot' => [
                    'title'        => $active->title,
                    'total_amount' => (float) $active->total_amount,
                    'end_date'     => $active->end_date->toDateString(),
                    'terms'        => $active->terms,
                ],
                'created_by' => $ahmed?->id,
            ],
        );
    }

    // ================================================================
    // 33 + 34. Escrow accounts and releases
    // ================================================================
    /**
     * @param Collection<int,Contract> $contracts
     * @return Collection<int,EscrowAccount>
     */
    private function seedEscrowAccountsAndReleases(Collection $contracts): Collection
    {
        $eligible = $contracts->filter(fn (Contract $c) => in_array(
            $c->status,
            [ContractStatus::ACTIVE, ContractStatus::SIGNED, ContractStatus::COMPLETED],
            true,
        ))->values();

        $accounts = collect();
        foreach ($eligible as $contract) {
            $total      = (float) $contract->total_amount;
            $isComplete = $contract->status === ContractStatus::COMPLETED;
            $isActive   = $contract->status === ContractStatus::ACTIVE;

            $deposited = $isComplete ? $total : ($isActive ? $total * 0.80 : $total * 0.30);
            $released  = $isComplete ? $total : ($isActive ? $total * 0.30 : 0);

            $account = EscrowAccount::updateOrCreate(
                ['contract_id' => $contract->id],
                [
                    'bank_partner'        => 'mashreq_neobiz',
                    'external_account_id' => 'ESC-' . $contract->contract_number,
                    'currency'            => 'AED',
                    'total_deposited'     => $deposited,
                    'total_released'      => $released,
                    'status'              => $isComplete ? 'closed' : 'active',
                    'activated_at'        => now()->subDays(20),
                    'closed_at'           => $isComplete ? now()->subDays(5) : null,
                    'metadata'            => ['retention_days' => 30],
                ],
            );
            $contract->forceFill(['escrow_account_id' => $account->id])->save();

            // One deposit row.
            EscrowRelease::firstOrCreate(
                ['escrow_account_id' => $account->id, 'type' => 'deposit', 'milestone' => 'initial'],
                [
                    'amount'         => $deposited,
                    'currency'       => 'AED',
                    'triggered_by'   => 'manual',
                    'bank_reference' => 'DEP-' . $contract->contract_number,
                    'notes'          => 'Initial deposit by buyer.',
                    'recorded_at'    => now()->subDays(20),
                ],
            );

            if ($released > 0) {
                EscrowRelease::firstOrCreate(
                    ['escrow_account_id' => $account->id, 'type' => 'release', 'milestone' => 'advance'],
                    [
                        'amount'         => $released,
                        'currency'       => 'AED',
                        'triggered_by'   => 'auto_signature',
                        'bank_reference' => 'REL-' . $contract->contract_number . '-1',
                        'notes'          => 'Auto-released on contract signature.',
                        'recorded_at'    => now()->subDays(15),
                    ],
                );
            }

            $accounts->push($account);
        }

        return $accounts;
    }

    // ================================================================
    // 35. Payments — every PaymentStatus value
    // ================================================================
    /**
     * @param Collection<int,Contract>      $contracts
     * @param Collection<int,EscrowAccount> $escrows
     * @return Collection<int,Payment>
     */
    private function seedPayments(Collection $contracts, Company $buyer, Collection $escrows): Collection
    {
        $ahmed   = User::where('email', 'buyer@al-ahram.test')->first();
        $manager = User::where('email', 'manager@al-ahram.test')->first();

        $supplierFor = function (Contract $c): ?Company {
            $partyIds   = collect($c->parties)->pluck('company_id')->filter()->all();
            $supplierId = collect($partyIds)->first(fn ($id) => $this->suppliers->pluck('id')->contains($id));
            return $this->suppliers->firstWhere('id', $supplierId) ?? $this->suppliers->first();
        };

        $active     = $contracts->first(fn (Contract $c) => $c->status === ContractStatus::ACTIVE);
        $signed     = $contracts->first(fn (Contract $c) => $c->status === ContractStatus::SIGNED);
        $completed  = $contracts->first(fn (Contract $c) => $c->status === ContractStatus::COMPLETED);
        $terminated = $contracts->first(fn (Contract $c) => $c->status === ContractStatus::TERMINATED);

        $rows = [];
        if ($active) {
            $sup = $supplierFor($active);
            $rows[] = [$active, $sup, PaymentStatus::COMPLETED,        28500, 'Advance Payment (30%)',           -25];
            $rows[] = [$active, $sup, PaymentStatus::PROCESSING,       47500, 'Production Milestone (50%)',       -2];
            $rows[] = [$active, $sup, PaymentStatus::APPROVED,         19000, 'Delivery Payment (20%) — pending',  5];
        }
        if ($signed) {
            $sup = $supplierFor($signed);
            $rows[] = [$signed, $sup, PaymentStatus::PENDING_APPROVAL, 37500, 'Advance Payment (30%)',             1];
            $rows[] = [$signed, $sup, PaymentStatus::REJECTED,         62500, 'Production Stage — rejected',      -1];
        }
        if ($completed) {
            $sup = $supplierFor($completed);
            $rows[] = [$completed, $sup, PaymentStatus::COMPLETED,     20400, 'Advance Payment',                 -75];
            $rows[] = [$completed, $sup, PaymentStatus::COMPLETED,     47600, 'Delivery Payment',                -12];
            $rows[] = [$completed, $sup, PaymentStatus::REFUNDED,       2000, 'Goodwill Refund',                  -8];
        }
        if ($terminated) {
            $sup = $supplierFor($terminated);
            $rows[] = [$terminated, $sup, PaymentStatus::FAILED,       66000, 'Advance Payment — gateway failure',-45];
            $rows[] = [$terminated, $sup, PaymentStatus::CANCELLED,    44000, 'Production Payment — cancelled',  -20];
        }

        $payments = collect();
        foreach ($rows as $r) {
            [$contract, $sup, $status, $amount, $milestone, $offset] = $r;

            $payment = Payment::updateOrCreate(
                ['contract_id' => $contract->id, 'milestone' => $milestone],
                [
                    'company_id'           => $buyer->id,
                    'recipient_company_id' => $sup->id,
                    'buyer_id'             => $ahmed?->id,
                    'status'               => $status->value,
                    'amount'               => $amount,
                    'vat_rate'             => 5.00,
                    'vat_amount'           => $amount * 0.05,
                    'total_amount'         => $amount * 1.05,
                    'currency'             => 'AED',
                    'milestone'            => $milestone,
                    'approved_at'          => in_array($status, [PaymentStatus::COMPLETED, PaymentStatus::APPROVED, PaymentStatus::PROCESSING], true)
                        ? now()->addDays($offset) : null,
                    'approved_by'          => in_array($status, [PaymentStatus::COMPLETED, PaymentStatus::APPROVED, PaymentStatus::PROCESSING], true)
                        ? $manager?->id : null,
                    'rejection_reason'     => $status === PaymentStatus::REJECTED ? 'Supplier did not meet milestone evidence requirement.' : null,
                ],
            );
            $payment->forceFill(['created_at' => now()->addDays($offset)])->saveQuietly();
            $payments->push($payment);
        }

        return $payments;
    }

    // ================================================================
    // 36 + 37. Shipments + tracking events
    // ================================================================
    /**
     * @param Collection<int,Contract> $contracts
     * @return Collection<int,Shipment>
     */
    private function seedShipments(Collection $contracts, Company $buyer, Company $logistics): Collection
    {
        $shippable = $contracts->filter(fn (Contract $c) => in_array(
            $c->status,
            [ContractStatus::ACTIVE, ContractStatus::SIGNED, ContractStatus::COMPLETED, ContractStatus::TERMINATED],
            true,
        ))->values();

        if ($shippable->isEmpty()) {
            return collect();
        }

        $defs = [
            [ShipmentStatus::IN_PRODUCTION,    'Sharjah Industrial Area', 'Dubai Silicon Oasis', 14],
            [ShipmentStatus::READY_FOR_PICKUP, 'Deira, Dubai',            'Abu Dhabi',            7],
            [ShipmentStatus::IN_TRANSIT,       'Dubai Internet City',     'Al Ain',               3],
            [ShipmentStatus::IN_CLEARANCE,     'Mussafah, Abu Dhabi',     'Ras Al Khaimah',       1],
            [ShipmentStatus::DELIVERED,        'Healthcare City, Dubai',  'Fujairah',            -3],
            [ShipmentStatus::CANCELLED,        'Sharjah Free Zone',       'Sharjah',             -1],
        ];

        $shipments = collect();
        foreach ($defs as $i => $d) {
            [$status, $originCity, $destCity, $etaOffset] = $d;
            $contract = $shippable[$i % $shippable->count()];

            $shipment = Shipment::updateOrCreate(
                ['tracking_number' => sprintf('SHP-%s-%04d', date('Y'), 1001 + $i)],
                [
                    'contract_id'            => $contract->id,
                    'company_id'             => $buyer->id,
                    'logistics_company_id'   => $logistics->id,
                    'status'                 => $status->value,
                    'origin'                 => ['city' => $originCity, 'country' => 'UAE', 'address' => $originCity],
                    'destination'            => ['city' => $destCity,   'country' => 'UAE', 'address' => $destCity],
                    'current_location'       => ['city' => $originCity, 'country' => 'UAE'],
                    'inspection_status'      => $status === ShipmentStatus::DELIVERED ? 'passed' : null,
                    'customs_clearance_status'=> in_array($status, [ShipmentStatus::IN_CLEARANCE, ShipmentStatus::DELIVERED], true) ? 'cleared' : null,
                    'customs_documents'      => [],
                    'estimated_delivery'     => now()->addDays($etaOffset),
                    'actual_delivery'        => $status === ShipmentStatus::DELIVERED ? now()->addDays($etaOffset) : null,
                    'notes'                  => 'Carrier: FastLine Logistics. Real-time GPS enabled.',
                ],
            );

            $shipment->trackingEvents()->delete();
            $this->seedTrackingEvents($shipment, $status, $originCity, $destCity);

            $shipments->push($shipment);
        }

        return $shipments;
    }

    private function seedTrackingEvents(Shipment $shipment, ShipmentStatus $finalStatus, string $originCity, string $destCity): void
    {
        $progression = [
            ShipmentStatus::IN_PRODUCTION->value    => ['Goods being prepared at supplier facility',  $originCity,                  -10],
            ShipmentStatus::READY_FOR_PICKUP->value => ['Goods ready, logistics provider notified',   $originCity,                   -7],
            ShipmentStatus::IN_TRANSIT->value       => ['Shipment picked up and en route',            'Highway en route',            -3],
            ShipmentStatus::IN_CLEARANCE->value     => ['Customs clearance in progress',              $destCity . ' Customs Port',   -1],
            ShipmentStatus::DELIVERED->value        => ['Shipment delivered successfully',            $destCity,                      0],
        ];

        $stop = $finalStatus->value;
        $emit = true;
        foreach ($progression as $status => [$desc, $loc, $offset]) {
            if (!$emit) {
                break;
            }
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'status'      => $status,
                'description' => $desc,
                'location'    => ['city' => $loc, 'country' => 'UAE', 'address' => $loc],
                'event_at'    => now()->addDays($offset),
            ]);
            if ($status === $stop) {
                $emit = false;
            }
        }

        if ($finalStatus === ShipmentStatus::CANCELLED) {
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'status'      => ShipmentStatus::CANCELLED->value,
                'description' => 'Shipment cancelled by buyer.',
                'location'    => ['city' => $originCity, 'country' => 'UAE'],
                'event_at'    => now()->subDay(),
            ]);
        }
    }

    // ================================================================
    // 21 (cont). Carbon footprints per shipment
    // ================================================================
    /** @param Collection<int,Shipment> $shipments */
    private function seedCarbonFootprints(Collection $shipments): void
    {
        foreach ($shipments as $shipment) {
            CarbonFootprint::firstOrCreate(
                [
                    'entity_type' => 'shipment',
                    'entity_id'   => $shipment->id,
                ],
                [
                    'scope'        => 3,
                    'co2e_kg'      => 1200 + ($shipment->id * 37) % 1500,
                    'period_start' => now()->subDays(15)->toDateString(),
                    'period_end'   => now()->toDateString(),
                    'source'       => 'shipment_calculation',
                    'metadata'     => ['mode' => 'road', 'distance_km' => 380 + ($shipment->id * 11) % 200],
                ],
            );
        }
    }

    // ================================================================
    // 38. Disputes — every type and status
    // ================================================================
    /** @param Collection<int,Contract> $contracts */
    private function seedDisputes(Collection $contracts, Company $buyer): void
    {
        $eligible = $contracts->filter(fn (Contract $c) => in_array(
            $c->status,
            [ContractStatus::ACTIVE, ContractStatus::SIGNED, ContractStatus::COMPLETED, ContractStatus::TERMINATED],
            true,
        ))->values();

        if ($eligible->isEmpty()) {
            return;
        }

        $ahmed = User::where('email', 'buyer@al-ahram.test')->first();
        $gov   = User::where('email', 'gov@trilink.test')->first();

        $defs = [
            [DisputeType::QUALITY,         DisputeStatus::OPEN,         'Non-compliant product specifications',     'Received copper wire does not meet IEC 60228 standards as specified.'],
            [DisputeType::DELIVERY,        DisputeStatus::UNDER_REVIEW, 'Shipment delayed beyond agreed timeline',  'Goods arrived 8 days past contractual window.'],
            [DisputeType::PAYMENT,         DisputeStatus::ESCALATED,    'Milestone payment dispute',                'Supplier claims completion; buyer disputes scope. Escalating to government.'],
            [DisputeType::CONTRACT_BREACH, DisputeStatus::RESOLVED,     'Scope creep beyond contract',              'Supplier delivered unrequested extras and invoiced for them. Resolved by mutual write-off.'],
            [DisputeType::OTHER,           DisputeStatus::OPEN,         'Communication breakdown',                  'Supplier unresponsive for 5 business days.'],
        ];

        foreach ($defs as $i => $d) {
            [$type, $status, $title, $desc] = $d;
            $contract = $eligible[$i % $eligible->count()];

            $partyIds   = collect($contract->parties)->pluck('company_id')->filter()->all();
            $supplierId = collect($partyIds)->first(fn ($id) => $this->suppliers->pluck('id')->contains($id));
            $against    = $this->suppliers->firstWhere('id', $supplierId) ?? $this->suppliers->first();

            Dispute::updateOrCreate(
                ['title' => $title, 'contract_id' => $contract->id],
                [
                    'company_id'              => $buyer->id,
                    'raised_by'               => $ahmed?->id,
                    'against_company_id'      => $against->id,
                    'assigned_to'             => in_array($status, [DisputeStatus::UNDER_REVIEW, DisputeStatus::ESCALATED], true) ? $gov?->id : null,
                    'type'                    => $type->value,
                    'status'                  => $status->value,
                    'description'             => $desc,
                    'sla_due_date'            => now()->addDays(7 - $i),
                    'escalated_to_government' => $status === DisputeStatus::ESCALATED,
                    'resolution'              => $status === DisputeStatus::RESOLVED
                        ? 'Mutual write-off agreed. Supplier credited buyer for the disputed extras and the matter is closed.'
                        : null,
                    'resolved_at'             => $status === DisputeStatus::RESOLVED ? now()->subDays(5) : null,
                ],
            );
        }
    }

    // ================================================================
    // 39. Feedback / reviews
    // ================================================================
    /** @param Collection<int,Contract> $contracts */
    private function seedFeedback(Collection $contracts, Company $buyer): void
    {
        $completed = $contracts->filter(fn (Contract $c) => $c->status === ContractStatus::COMPLETED);
        $ahmed     = User::where('email', 'buyer@al-ahram.test')->first();

        foreach ($completed as $contract) {
            $partyIds   = collect($contract->parties)->pluck('company_id')->filter()->all();
            $supplierId = collect($partyIds)->first(fn ($id) => $this->suppliers->pluck('id')->contains($id));
            if (!$supplierId) {
                continue;
            }

            Feedback::updateOrCreate(
                ['contract_id' => $contract->id, 'rater_company_id' => $buyer->id],
                [
                    'target_company_id'   => $supplierId,
                    'rater_user_id'       => $ahmed?->id,
                    'rating'              => 5,
                    'comment'             => 'Excellent execution — on time, on spec, and easy to communicate with.',
                    'quality_score'       => 5,
                    'on_time_score'       => 5,
                    'communication_score' => 4,
                ],
            );
        }
    }

    // ================================================================
    // 8 + 9. Saved searches and search history
    // ================================================================
    private function seedSavedSearchesAndHistory(Company $buyer): void
    {
        $ahmed = User::where('email', 'buyer@al-ahram.test')->first();
        if (!$ahmed) {
            return;
        }

        $defs = [
            ['Open RFQs in Construction', 'rfqs',      ['status' => 'open',  'category' => 'construction']],
            ['Verified suppliers in UAE', 'suppliers', ['country' => 'UAE',  'verification' => 'gold']],
            ['Office stock products',     'products',  ['category' => 'office', 'in_stock' => true]],
        ];
        foreach ($defs as [$label, $resource, $filters]) {
            SavedSearch::updateOrCreate(
                ['user_id' => $ahmed->id, 'label' => $label],
                [
                    'resource_type'    => $resource,
                    'filters'          => $filters,
                    'is_active'        => true,
                    'last_notified_at' => now()->subDays(2),
                ],
            );
        }

        // Recent search history.
        $terms = ['hydraulic pump', 'copper wire', 'rfq construction', 'medco', 'gold supplier'];
        foreach ($terms as $i => $term) {
            SearchHistory::firstOrCreate(
                ['user_id' => $ahmed->id, 'term' => $term],
                ['result_count' => 3 + $i, 'created_at' => now()->subHours($i)],
            );
        }
    }

    // ================================================================
    // 40 + 41. Webhook endpoints + deliveries
    // ================================================================
    private function seedWebhookEndpointsAndDeliveries(Company $buyer): void
    {
        $endpoints = [
            ['Zapier — Production',  'https://hooks.zapier.com/hooks/catch/12345/abcde',   'contract.signed,payment.completed'],
            ['Internal ETL Sink',    'https://etl.al-ahram.test/webhooks/trilink',         ''],
        ];
        foreach ($endpoints as [$label, $url, $events]) {
            $endpoint = WebhookEndpoint::updateOrCreate(
                ['company_id' => $buyer->id, 'label' => $label],
                [
                    'url'               => $url,
                    'events'            => $events,
                    'secret'            => Str::random(48),
                    'is_active'         => true,
                    'last_delivered_at' => now()->subHours(3),
                    'failure_count'     => 0,
                ],
            );

            // Wipe previous demo deliveries for this endpoint so re-runs converge.
            WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->id)->delete();

            $deliveryDefs = [
                ['contract.signed',    200, 'success', '{"ok":true}'],
                ['payment.completed',  200, 'success', '{"ok":true}'],
                ['shipment.delivered', 502, 'failed',  '{"error":"Bad Gateway"}'],
            ];
            foreach ($deliveryDefs as $i => [$event, $status, $deliveryStatus, $body]) {
                WebhookDelivery::create([
                    'webhook_endpoint_id' => $endpoint->id,
                    'event'               => $event,
                    'payload'             => ['event' => $event, 'demo' => true],
                    'response_status'     => $status,
                    'response_body'       => $body,
                    'attempt'             => 1,
                    'status'              => $deliveryStatus,
                    'created_at'          => now()->subHours($i + 1),
                    'updated_at'          => now()->subHours($i + 1),
                ]);
            }
        }
    }

    // ================================================================
    // 42. ERP connectors
    // ================================================================
    private function seedErpConnectors(Company $buyer): void
    {
        $defs = [
            ['odoo',     'Production Odoo',     'https://odoo.al-ahram.test'],
            ['netsuite', 'NetSuite Sandbox',    'https://sandbox.netsuite.com/al-ahram'],
        ];
        foreach ($defs as [$type, $label, $baseUrl]) {
            ErpConnector::updateOrCreate(
                ['company_id' => $buyer->id, 'label' => $label],
                [
                    'type'                  => $type,
                    'base_url'              => $baseUrl,
                    'credentials_encrypted' => encrypt(['api_key' => 'demo-key', 'secret' => 'demo-secret']),
                    'is_active'             => true,
                    'last_sync_at'          => now()->subHours(6),
                    'metadata'              => ['version' => '17.0', 'environment' => 'production'],
                ],
            );
        }
    }

    // ================================================================
    // 43. SCIM users — IdP-provisioned shadows
    // ================================================================
    private function seedScimUsers(): void
    {
        $emails = ['buyer@al-ahram.test', 'manager@al-ahram.test', 'finance@al-ahram.test'];
        foreach ($emails as $i => $email) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }
            ScimUser::updateOrCreate(
                ['external_id' => 'okta-' . $user->id],
                [
                    'user_id'      => $user->id,
                    'is_active'    => true,
                    'scim_payload' => [
                        'schemas'     => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                        'externalId'  => 'okta-' . $user->id,
                        'userName'    => $email,
                        'name'        => ['givenName' => $user->first_name, 'familyName' => $user->last_name],
                        'emails'      => [['value' => $email, 'primary' => true]],
                    ],
                ],
            );
        }
    }

    // ================================================================
    // 10. Settings
    // ================================================================
    private function seedSettings(): void
    {
        Setting::setValue('platform.name',      ['en' => 'TriLink', 'ar' => 'تري لينك'], 'general');
        Setting::setValue('platform.currency',  'AED',                                    'general');
        Setting::setValue('platform.timezone',  'Asia/Dubai',                             'general');
        Setting::setValue('payments.gateway',   'stripe',                                 'payments');
        Setting::setValue('shipping.providers', ['fastline', 'aramex', 'dhl'],            'shipping');
        Setting::setValue('escrow.bank_partner','mashreq_neobiz',                         'finance');
        Setting::setValue('integrations.signing_secret_length', 48,                       'integrations');
    }

    // ================================================================
    // 44. Audit logs
    // ================================================================
    private function seedAuditLogs(Company $buyer): void
    {
        $admin   = User::where('email', 'admin@trilink.test')->first();
        $ahmed   = User::where('email', 'buyer@al-ahram.test')->first();
        $manager = User::where('email', 'manager@al-ahram.test')->first();
        if (!$admin || !$ahmed || !$manager) {
            return;
        }

        // Wipe previous demo audit logs (identified by user_agent) so re-runs converge.
        AuditLog::query()->where('user_agent', 'ComprehensiveSeeder/2.0')->delete();

        $rows = [
            [$admin,   AuditAction::LOGIN,   'User',            $admin->id, null],
            [$ahmed,   AuditAction::CREATE,  'PurchaseRequest', null,        $buyer->id],
            [$ahmed,   AuditAction::SUBMIT,  'PurchaseRequest', null,        $buyer->id],
            [$manager, AuditAction::APPROVE, 'PurchaseRequest', null,        $buyer->id],
            [$ahmed,   AuditAction::SIGN,    'Contract',        null,        $buyer->id],
            [$manager, AuditAction::APPROVE, 'Payment',         null,        $buyer->id],
            [$admin,   AuditAction::EXPORT,  'AuditLog',        null,        null],
        ];
        foreach ($rows as [$user, $action, $type, $rid, $cid]) {
            AuditLog::create([
                'user_id'       => $user->id,
                'company_id'    => $cid,
                'action'        => $action->value,
                'resource_type' => $type,
                'resource_id'   => $rid,
                'before'        => null,
                'after'         => ['demo' => true],
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'ComprehensiveSeeder/2.0',
                'status'        => 'success',
            ]);
        }
    }

    // ================================================================
    // 45. Database notifications — bell-icon dropdown
    // ================================================================
    private function seedDatabaseNotifications(Company $buyer): void
    {
        $ahmed   = User::where('email', 'buyer@al-ahram.test')->first();
        $manager = User::where('email', 'manager@al-ahram.test')->first();
        if (!$ahmed || !$manager) {
            return;
        }

        // Wipe existing demo notifications so re-seeding doesn't pile up.
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', [$ahmed->id, $manager->id])->delete();

        $contracts = Contract::where('buyer_company_id', $buyer->id)->limit(3)->get();
        $payments  = Payment::where('company_id', $buyer->id)->limit(2)->get();

        $defs = [
            [$ahmed,   'App\\Notifications\\NewBidNotification',          'bid',      'New bid received',          'Emirates Industrial Co. submitted a bid for "Copper Wire 16mm".'],
            [$ahmed,   'App\\Notifications\\ContractSignedNotification',  'contract', 'Contract signed',           'Contract CNT-2026-0003 has been signed by both parties.'],
            [$manager, 'App\\Notifications\\PaymentStatusNotification',   'payment',  'Payment awaiting approval', 'A new payment milestone is awaiting your approval.'],
            [$manager, 'App\\Notifications\\DisputeNotification',         'dispute',  'Dispute opened',            'A dispute has been opened on contract CNT-2026-0001.'],
        ];

        foreach ($defs as $i => [$user, $type, $entityType, $title, $message]) {
            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => $type,
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data'            => json_encode([
                    'type'        => 'info',
                    'title'       => $title,
                    'message'     => $message,
                    'entity_type' => $entityType,
                    'entity_id'   => $contracts->first()?->id ?? 1,
                ]),
                'read_at'         => $i === 0 ? null : now()->subMinutes($i * 30),
                'created_at'      => now()->subHours($i + 1),
                'updated_at'      => now()->subHours($i + 1),
            ]);
        }
    }
}
