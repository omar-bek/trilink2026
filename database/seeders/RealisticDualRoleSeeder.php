<?php

namespace Database\Seeders;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Enums\DocumentType;
use App\Enums\FreeZoneAuthority;
use App\Enums\LegalJurisdiction;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerificationLevel;
use App\Models\BeneficialOwner;
use App\Models\Bid;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyInsurance;
use App\Models\Consent;
use App\Models\Contract;
use App\Models\CreditScore;
use App\Models\Dispute;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Models\ExchangeRate;
use App\Models\Feedback;
use App\Models\IcvCertificate;
use App\Models\NegotiationMessage;
use App\Models\Payment;
use App\Models\PrivacyPolicyVersion;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\SanctionsScreening;
use App\Models\Shipment;
use App\Models\TaxInvoice;
use App\Models\TaxRate;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic dual-role seeder — every company acts as BUYER and SUPPLIER.
 *
 * Creates 8 UAE companies spanning construction, steel, IT, medical,
 * logistics, industrial, food, and building materials. Each company:
 *   - publishes 2 RFQs (buyer side)
 *   - submits bids on 3–4 RFQs from other companies (supplier side)
 *   - wins some, loses some → is buyer on some contracts, supplier on others
 *
 * Lifecycle covered per awarded RFQ: bid → negotiation rounds → contract →
 * escrow → milestone payments → tax invoices → shipment → feedback.
 */
class RealisticDualRoleSeeder extends Seeder
{
    /** @var array<string, Company> */
    private array $companies = [];

    /** @var array<string, Branch> */
    private array $branches = [];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<int, Rfq> */
    private array $rfqs = [];

    private CarbonImmutable $now;

    private PrivacyPolicyVersion $privacyPolicy;

    public function run(): void
    {
        $this->now = CarbonImmutable::now();

        $this->command->info('Seeding realistic dual-role data...');

        $this->seedFoundation();
        $this->seedPrivacyPolicy();
        $this->seedAdminAndGovernment();
        $this->seedCompanies();
        $this->seedUsers();
        $this->seedCompliance();
        $this->seedProducts();
        $this->seedRfqs();
        $this->seedBidsAndContracts();
        $this->seedConsents();

        $this->command->info('Done. '.count($this->companies).' companies; each acts as buyer and supplier.');
        $this->command->line('  Login: admin@trilink.test / password');
        $this->command->line('  Every company user: <role>@<slug>.test / password (e.g. manager@mansoori.test)');
    }

    // ──────────────────────────────────────────────────────────────────
    //  FOUNDATION (categories, tax rates, exchange rates)
    // ──────────────────────────────────────────────────────────────────

    private function seedFoundation(): void
    {
        $catDefs = [
            ['construction',  'Construction Materials', 'مواد البناء'],
            ['steel',         'Steel & Metals',         'الصلب والمعادن'],
            ['it',            'IT Hardware & Services', 'تكنولوجيا المعلومات'],
            ['medical',       'Medical Equipment',      'المعدات الطبية'],
            ['office',        'Office Supplies',        'اللوازم المكتبية'],
            ['industrial',    'Industrial Machinery',   'الآلات الصناعية'],
            ['food',          'Food & Beverage',        'الأغذية والمشروبات'],
            ['logistics-svc', 'Logistics Services',     'خدمات الشحن والنقل'],
            ['electrical',    'Electrical & Power',     'الكهرباء والطاقة'],
            ['hvac',          'HVAC & Climate',         'التكييف والتبريد'],
        ];
        foreach ($catDefs as [$slug, $en, $ar]) {
            $this->categories[$slug] = Category::updateOrCreate(
                ['name' => $en],
                ['name_ar' => $ar, 'is_active' => true, 'level' => 1, 'path' => '/'.$slug]
            );
        }

        TaxRate::updateOrCreate(
            ['code' => 'VAT_STD'],
            [
                'name' => 'UAE VAT Standard',
                'rate' => 5.00,
                'country' => 'AE',
                'is_active' => true,
                'is_default' => true,
                'effective_from' => '2018-01-01',
                'description' => 'UAE standard rate VAT 5%',
            ]
        );
        TaxRate::updateOrCreate(
            ['code' => 'CORP_TAX'],
            [
                'name' => 'UAE Corporate Tax',
                'rate' => 9.00,
                'country' => 'AE',
                'is_active' => true,
                'effective_from' => '2023-06-01',
                'description' => 'UAE corporate tax 9% on profits above 375K AED',
            ]
        );

        foreach ([
            ['USD', 3.6725], ['EUR', 4.0120], ['GBP', 4.6180],
            ['SAR', 0.9793], ['INR', 0.0440], ['CNY', 0.5061],
        ] as [$ccy, $rate]) {
            ExchangeRate::updateOrCreate(
                ['from_currency' => $ccy, 'to_currency' => 'AED', 'as_of' => $this->now->toDateString()],
                ['rate' => $rate, 'source' => 'UAE Central Bank']
            );
        }
    }

    private function seedPrivacyPolicy(): void
    {
        $this->privacyPolicy = PrivacyPolicyVersion::firstOrCreate(
            ['version' => '2026.1'],
            [
                'effective_from' => $this->now->subMonths(3),
                'sha256' => hash('sha256', 'trilink-privacy-2026.1'),
                'body_en' => 'Initial PDPL-aligned privacy policy for Trilink demo data.',
                'body_ar' => 'سياسة الخصوصية الأولى المتوافقة مع قانون حماية البيانات.',
                'changelog' => 'Initial version.',
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────
    //  PLATFORM USERS (admin + government)
    // ──────────────────────────────────────────────────────────────────

    private function seedAdminAndGovernment(): void
    {
        $this->users['admin'] = User::updateOrCreate(
            ['email' => 'admin@trilink.test'],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'password' => Hash::make('password'),
                'phone' => '+971500000001',
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
                'locale' => 'en',
                'email_verified_at' => $this->now,
            ]
        );

        $this->users['government'] = User::updateOrCreate(
            ['email' => 'liaison@gov.ae.test'],
            [
                'first_name' => 'Ahmed',
                'last_name' => 'Al Maktoum',
                'password' => Hash::make('password'),
                'phone' => '+971500000002',
                'role' => UserRole::GOVERNMENT,
                'status' => UserStatus::ACTIVE,
                'locale' => 'ar',
                'position_title' => 'Compliance Liaison',
                'email_verified_at' => $this->now,
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────
    //  COMPANIES — 8 real UAE-style dual-role companies
    // ──────────────────────────────────────────────────────────────────

    private function seedCompanies(): void
    {
        $companyDefs = [
            [
                'slug' => 'mansoori', 'key' => 'mansoori',
                'name' => 'Al Mansoori Trading LLC',  'name_ar' => 'المنصوري للتجارة ذ.م.م',
                'reg' => 'CN-1001234', 'trn' => '100123456700003',
                'email' => 'info@mansoori.test', 'phone' => '+97143001000',
                'website' => 'https://mansoori.test',
                'city' => 'Dubai', 'address' => 'Al Quoz Industrial Area 3, Dubai, UAE',
                'free_zone' => false, 'fz_auth' => null,
                'desc' => 'General trading house dealing in electronics, textiles, and industrial supplies.',
                'primary_cat' => 'office', 'also_trades' => ['electrical', 'it'],
            ],
            [
                'slug' => 'esi', 'key' => 'esi',
                'name' => 'Emirates Steel Industries', 'name_ar' => 'الإمارات للحديد والصلب',
                'reg' => 'CN-1002345', 'trn' => '100234567800003',
                'email' => 'sales@esi.test', 'phone' => '+97124220000',
                'website' => 'https://esi.test',
                'city' => 'Abu Dhabi', 'address' => 'ICAD-1, Mussafah, Abu Dhabi, UAE',
                'free_zone' => false, 'fz_auth' => null,
                'desc' => 'Integrated steel producer — rebar, wire rod, and heavy structural sections.',
                'primary_cat' => 'steel', 'also_trades' => ['construction', 'industrial'],
            ],
            [
                'slug' => 'dubaitech', 'key' => 'dubaitech',
                'name' => 'Dubai Tech Solutions DMCC', 'name_ar' => 'دبي للحلول التقنية',
                'reg' => 'DMCC-7788', 'trn' => '100345678900003',
                'email' => 'hello@dubaitech.test', 'phone' => '+97143905500',
                'website' => 'https://dubaitech.test',
                'city' => 'Dubai', 'address' => 'Unit 2201, Reef Tower, JLT, Dubai, UAE',
                'free_zone' => true, 'fz_auth' => FreeZoneAuthority::DMCC,
                'desc' => 'Enterprise IT — procurement, system integration, managed services.',
                'primary_cat' => 'it', 'also_trades' => ['office', 'electrical'],
            ],
            [
                'slug' => 'gulfmed', 'key' => 'gulfmed',
                'name' => 'Gulf Medical Supplies Co.', 'name_ar' => 'الخليج للإمدادات الطبية',
                'reg' => 'CN-1003456', 'trn' => '100456789000003',
                'email' => 'orders@gulfmed.test', 'phone' => '+97165555600',
                'website' => 'https://gulfmed.test',
                'city' => 'Sharjah', 'address' => 'SAIF Zone, Sharjah, UAE',
                'free_zone' => true, 'fz_auth' => FreeZoneAuthority::SAIF_ZONE,
                'desc' => 'Medical devices, consumables, and lab equipment distributor.',
                'primary_cat' => 'medical', 'also_trades' => ['office', 'electrical'],
            ],
            [
                'slug' => 'futlog', 'key' => 'futlog',
                'name' => 'Future Lines Logistics', 'name_ar' => 'خطوط المستقبل للشحن',
                'reg' => 'CN-1004567', 'trn' => '100567890100003',
                'email' => 'dispatch@futlog.test', 'phone' => '+97143998800',
                'website' => 'https://futlog.test',
                'city' => 'Dubai', 'address' => 'Jebel Ali Free Zone South, Dubai, UAE',
                'free_zone' => true, 'fz_auth' => FreeZoneAuthority::JAFZA,
                'desc' => 'Regional freight forwarder — road, air, sea, and customs clearance.',
                'primary_cat' => 'logistics-svc', 'also_trades' => ['industrial'],
            ],
            [
                'slug' => 'khalifa', 'key' => 'khalifa',
                'name' => 'Khalifa Industrial Group',  'name_ar' => 'مجموعة خليفة الصناعية',
                'reg' => 'CN-1005678', 'trn' => '100678901200003',
                'email' => 'contact@khalifa.test', 'phone' => '+97172221100',
                'website' => 'https://khalifa.test',
                'city' => 'Ras Al Khaimah', 'address' => 'Al Hamra Industrial Zone, RAK, UAE',
                'free_zone' => true, 'fz_auth' => FreeZoneAuthority::RAKEZ,
                'desc' => 'Industrial machinery manufacturing, HVAC systems, and power equipment.',
                'primary_cat' => 'industrial', 'also_trades' => ['hvac', 'electrical'],
            ],
            [
                'slug' => 'nfp', 'key' => 'nfp',
                'name' => 'National Food Products Co.', 'name_ar' => 'الوطنية للمنتجات الغذائية',
                'reg' => 'CN-1006789', 'trn' => '100789012300003',
                'email' => 'sales@nfp.test', 'phone' => '+97143334400',
                'website' => 'https://nfp.test',
                'city' => 'Dubai', 'address' => 'Dubai Industrial City, Dubai, UAE',
                'free_zone' => false, 'fz_auth' => null,
                'desc' => 'Food processing — dairy, juices, snacks, and institutional catering supply.',
                'primary_cat' => 'food', 'also_trades' => ['office', 'logistics-svc'],
            ],
            [
                'slug' => 'binhamoodah', 'key' => 'binhamoodah',
                'name' => 'Bin Hamoodah Construction', 'name_ar' => 'بن حمودة للمقاولات',
                'reg' => 'CN-1007890', 'trn' => '100890123400003',
                'email' => 'projects@binhamoodah.test', 'phone' => '+97126667700',
                'website' => 'https://binhamoodah.test',
                'city' => 'Abu Dhabi', 'address' => 'Al Salam Street, Abu Dhabi, UAE',
                'free_zone' => false, 'fz_auth' => null,
                'desc' => 'Civil works and building contractor — high-rise, infra, interior fit-out.',
                'primary_cat' => 'construction', 'also_trades' => ['steel', 'hvac'],
            ],
        ];

        foreach ($companyDefs as $i => $def) {
            $verification = [
                VerificationLevel::PLATINUM, VerificationLevel::GOLD,
                VerificationLevel::GOLD, VerificationLevel::SILVER,
                VerificationLevel::GOLD, VerificationLevel::SILVER,
                VerificationLevel::PLATINUM, VerificationLevel::GOLD,
            ][$i];

            $company = Company::updateOrCreate(
                ['registration_number' => $def['reg']],
                [
                    'name' => $def['name'],
                    'name_ar' => $def['name_ar'],
                    'tax_number' => $def['trn'],
                    // Every company is dual-role in behaviour. CompanyType is
                    // kept as BUYER here (the platform enforces dual-role via
                    // ContractParty / CompanyPolicy, not via this enum).
                    'type' => CompanyType::BUYER,
                    'status' => CompanyStatus::ACTIVE,
                    'verification_level' => $verification,
                    'verified_at' => $this->now->subMonths(2),
                    'verified_by' => $this->users['admin']->id,
                    'sanctions_status' => 'clear',
                    'sanctions_screened_at' => $this->now->subDays(7),
                    'email' => $def['email'],
                    'phone' => $def['phone'],
                    'website' => $def['website'],
                    'address' => $def['address'],
                    'city' => $def['city'],
                    'country' => 'AE',
                    'description' => $def['desc'],
                    'is_free_zone' => $def['free_zone'],
                    'free_zone_authority' => $def['fz_auth'],
                    'is_designated_zone' => in_array($def['slug'], ['futlog', 'dubaitech'], true),
                    'legal_jurisdiction' => $def['slug'] === 'dubaitech'
                        ? LegalJurisdiction::DIFC
                        : LegalJurisdiction::FEDERAL,
                    'corporate_tax_number' => $def['trn'],
                    'corporate_tax_status' => 'registered',
                    'corporate_tax_registered_at' => '2024-01-01',
                    'approval_threshold_aed' => 250000,
                    'notification_recipient_roles' => ['company_manager', 'finance_manager'],
                    'certifications' => ['ISO 9001:2015', 'ISO 14001:2015'],
                ]
            );

            $this->companies[$def['key']] = $company;

            $this->branches[$def['key']] = Branch::updateOrCreate(
                ['company_id' => $company->id, 'name' => $def['city'].' HQ'],
                [
                    'name_ar' => $def['city'].' - المقر الرئيسي',
                    'category_id' => $this->categories[$def['primary_cat']]->id,
                    'address' => $def['address'],
                    'city' => $def['city'],
                    'country' => 'AE',
                    'is_active' => true,
                ]
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  USERS — 5 users per company (manager, buyer, sales, finance, logistics)
    // ──────────────────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        $people = [
            'mansoori'    => [['Khalid', 'Al Mansoori'], ['Fatima', 'Al Zaabi'], ['Saeed', 'Al Hosani'], ['Noura', 'Al Nuaimi'], ['Hamad', 'Al Ketbi']],
            'esi'         => [['Abdullah', 'Al Shamsi'], ['Hind', 'Al Naqbi'], ['Mohammed', 'Al Ali'], ['Maryam', 'Al Suwaidi'], ['Rashid', 'Al Qubaisi']],
            'dubaitech'   => [['Omar', 'Al Mulla'], ['Layla', 'Al Falasi'], ['Yousef', 'Al Bastaki'], ['Sara', 'Al Marri'], ['Ahmed', 'Al Balushi']],
            'gulfmed'     => [['Tariq', 'Al Noaimi'], ['Aisha', 'Al Obaidli'], ['Salem', 'Al Shehhi'], ['Reem', 'Al Kaabi'], ['Majid', 'Al Tunaiji']],
            'futlog'      => [['Ibrahim', 'Al Rashid'], ['Huda', 'Al Hammadi'], ['Faisal', 'Al Kindi'], ['Mariam', 'Al Ameri'], ['Zayed', 'Al Dhaheri']],
            'khalifa'     => [['Sultan', 'Al Jaber'], ['Amna', 'Al Mansoori'], ['Nasser', 'Al Blooshi'], ['Shamma', 'Al Shamsi'], ['Obaid', 'Al Nuaimi']],
            'nfp'         => [['Hassan', 'Al Qassimi'], ['Dana', 'Al Marzouqi'], ['Rashed', 'Al Muhairi'], ['Shaikha', 'Al Mazrouei'], ['Khalifa', 'Al Romaithi']],
            'binhamoodah' => [['Mansour', 'Bin Hamoodah'], ['Meera', 'Al Hashimi'], ['Tariq', 'Al Fahim'], ['Asma', 'Al Ghaferi'], ['Saif', 'Al Neyadi']],
        ];

        $rolePlan = [
            ['manager',  UserRole::COMPANY_MANAGER, 'Managing Director'],
            ['buyer',    UserRole::BUYER,           'Senior Procurement Officer'],
            ['sales',    UserRole::SALES_MANAGER,   'Sales Manager'],
            ['finance',  UserRole::FINANCE_MANAGER, 'Finance Manager'],
            ['ops',      UserRole::LOGISTICS,      'Operations Lead'],
        ];

        foreach ($this->companies as $key => $company) {
            $branch = $this->branches[$key] ?? null;
            foreach ($rolePlan as $idx => [$emailPrefix, $role, $title]) {
                [$first, $last] = $people[$key][$idx];
                $email = "{$emailPrefix}@{$key}.test";
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'first_name' => $first,
                        'last_name' => $last,
                        'password' => Hash::make('password'),
                        'phone' => '+9715'.str_pad((string) (10000000 + crc32($email) % 89999999), 8, '0', STR_PAD_LEFT),
                        'role' => $role,
                        'status' => UserStatus::ACTIVE,
                        'company_id' => $company->id,
                        'branch_id' => $branch?->id,
                        'position_title' => $title,
                        'locale' => $idx % 2 === 0 ? 'en' : 'ar',
                        'email_verified_at' => $this->now,
                        'password_changed_at' => $this->now->subMonths(2),
                    ]
                );
                $this->users["{$key}.{$emailPrefix}"] = $user;
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  COMPLIANCE — BO, docs, insurance, sanctions, credit, ICV
    // ──────────────────────────────────────────────────────────────────

    private function seedCompliance(): void
    {
        foreach ($this->companies as $key => $company) {
            // Beneficial owners: typical UAE LLC has 2–3 named owners.
            $owners = [
                ['Sheikh '.ucfirst($key).' Al Senior', 'AE', 55, 'passport', 60, 'Chairman'],
                ['Mrs. '.ucfirst($key).' Al Junior',   'AE', 42, 'emirates_id', 40, 'Board Member'],
            ];
            foreach ($owners as [$name, $nat, $age, $idType, $pct, $role]) {
                BeneficialOwner::updateOrCreate(
                    ['company_id' => $company->id, 'full_name' => $name],
                    [
                        'nationality' => $nat,
                        'date_of_birth' => $this->now->subYears($age)->toDateString(),
                        'id_type' => $idType,
                        'id_number' => strtoupper(substr(md5($company->id.$name), 0, 12)),
                        'id_expiry' => $this->now->addYears(5)->toDateString(),
                        'ownership_percentage' => $pct,
                        'role' => $role,
                        'is_pep' => false,
                        'source_of_wealth' => 'Commercial investments and family business.',
                        'last_screened_at' => $this->now->subDays(14),
                        'screening_result' => 'clear',
                        'verified_by' => $this->users['admin']->id,
                        'verified_at' => $this->now->subMonths(2),
                    ]
                );
            }

            // Documents — cover the verification tiers we assign above.
            $docs = [
                [DocumentType::TRADE_LICENSE,        'UAE Trade License'],
                [DocumentType::TAX_CERTIFICATE,      'VAT Registration Certificate'],
                [DocumentType::COMPANY_PROFILE,      'Company Profile'],
                [DocumentType::AUDITED_FINANCIALS,   'Audited Financials 2025'],
                [DocumentType::INSURANCE_CERTIFICATE,'General Liability Insurance'],
                [DocumentType::BANK_LETTER,          'Bank Reference Letter'],
            ];
            foreach ($docs as [$type, $label]) {
                CompanyDocument::updateOrCreate(
                    ['company_id' => $company->id, 'type' => $type],
                    [
                        'label' => $label,
                        'file_path' => "demo/companies/{$key}/".strtolower((string) $type->value).".pdf",
                        'original_filename' => $label.'.pdf',
                        'file_size' => 524288,
                        'mime_type' => 'application/pdf',
                        'status' => 'verified',
                        'issued_at' => $this->now->subYear()->toDateString(),
                        'expires_at' => $this->now->addYear()->toDateString(),
                        'uploaded_by' => $this->users["{$key}.manager"]->id,
                        'verified_by' => $this->users['admin']->id,
                        'verified_at' => $this->now->subMonths(2),
                    ]
                );
            }

            // Insurance
            CompanyInsurance::updateOrCreate(
                ['company_id' => $company->id, 'type' => 'general_liability'],
                [
                    'insurer' => 'Oman Insurance Company',
                    'policy_number' => 'OIC-'.$company->id.'-'.$this->now->year,
                    'coverage_amount' => 5000000,
                    'currency' => 'AED',
                    'starts_at' => $this->now->startOfYear()->toDateString(),
                    'expires_at' => $this->now->endOfYear()->toDateString(),
                    'file_path' => "demo/companies/{$key}/insurance-general.pdf",
                    'original_filename' => 'general-liability.pdf',
                    'file_size' => 380000,
                    'mime_type' => 'application/pdf',
                    'status' => 'verified',
                    'uploaded_by' => $this->users["{$key}.manager"]->id,
                    'verified_by' => $this->users['admin']->id,
                    'verified_at' => $this->now->subMonths(1),
                ]
            );

            // Sanctions screening — clear for every company
            SanctionsScreening::updateOrCreate(
                ['company_id' => $company->id, 'provider' => 'OFAC_SDN'],
                [
                    'query' => $company->name,
                    'result' => 'clear',
                    'match_count' => 0,
                    'matched_entities' => [],
                    'triggered_by' => $this->users['admin']->id,
                    'notes' => 'Automated screening against OFAC SDN list.',
                ]
            );

            // Credit score — varied bands
            $score = 620 + ($company->id * 31) % 200;
            $band = match (true) {
                $score >= 780 => 'A+', $score >= 720 => 'A',
                $score >= 680 => 'B+', $score >= 640 => 'B',
                default => 'C',
            };
            CreditScore::updateOrCreate(
                ['company_id' => $company->id, 'provider' => 'AECB'],
                [
                    'score' => $score,
                    'band' => $band,
                    'reasons' => ['On-time payment history', 'Low credit utilisation'],
                    'reported_at' => $this->now->subDays(30),
                ]
            );
            $company->update(['latest_credit_score' => $score, 'latest_credit_band' => $band]);

            // ICV certificate
            IcvCertificate::updateOrCreate(
                ['company_id' => $company->id, 'certificate_number' => 'ICV-'.$company->id.'-2026'],
                [
                    'issuer' => 'ADNOC ICV Program',
                    'score' => 45.0 + (($company->id * 7) % 40),
                    'issued_date' => $this->now->subMonths(3)->toDateString(),
                    'expires_date' => $this->now->addMonths(9)->toDateString(),
                    'file_path' => "demo/companies/{$key}/icv.pdf",
                    'file_sha256' => hash('sha256', $company->id.'icv'),
                    'file_size' => 180000,
                    'original_filename' => 'icv-certificate.pdf',
                    'status' => 'verified',
                    'uploaded_by' => $this->users["{$key}.manager"]->id,
                    'verified_by' => $this->users['admin']->id,
                    'verified_at' => $this->now->subMonths(3),
                ]
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  PRODUCTS — each company lists 2 products in its trade categories
    // ──────────────────────────────────────────────────────────────────

    private function seedProducts(): void
    {
        $catalog = [
            'mansoori' => [
                ['MNS-OFC-001', 'office',    'Ergonomic Office Chair',   250.00, 'unit', 50,  12],
                ['MNS-ELC-002', 'electrical','LED Panel Light 60x60cm',   85.00, 'pc',   500, 7],
            ],
            'esi' => [
                ['ESI-STL-001', 'steel',      'Rebar Grade 60 — 12mm',   2450.00, 'ton', 200, 10],
                ['ESI-STL-002', 'steel',      'Structural I-Beam 200mm', 3150.00, 'ton', 80,  14],
            ],
            'dubaitech' => [
                ['DTS-IT-001', 'it',          'Dell PowerEdge R660xs',   22500.00, 'unit', 20, 21],
                ['DTS-IT-002', 'it',          'Cisco Catalyst 9300 48P', 18400.00, 'unit', 15, 18],
            ],
            'gulfmed' => [
                ['GMS-MED-001', 'medical',    'Patient Monitor 15-inch',  7800.00, 'unit', 40, 10],
                ['GMS-MED-002', 'medical',    'Nitrile Exam Gloves (box)',  22.50, 'box',  5000, 3],
            ],
            'futlog' => [
                ['FL-LOG-001',  'logistics-svc','Full Truck Load UAE→KSA', 3200.00, 'trip', 30, 1],
                ['FL-LOG-002',  'logistics-svc','Customs Clearance Service', 450.00,'decl', 200,1],
            ],
            'khalifa' => [
                ['KIG-IND-001', 'industrial',  'Industrial Compressor 75kW', 42000.00, 'unit', 8, 30],
                ['KIG-HVAC-002','hvac',        'Rooftop Chiller Unit 20TR',  58000.00, 'unit', 6, 35],
            ],
            'nfp' => [
                ['NFP-FOOD-001','food',        'UHT Milk 1L (case of 12)',    52.00, 'case', 2000, 2],
                ['NFP-FOOD-002','food',        'Bottled Juice 1L (case of 6)',36.00, 'case', 1500, 2],
            ],
            'binhamoodah' => [
                ['BH-CON-001',  'construction','Ready Mix Concrete C40',    340.00, 'm3',  1000, 1],
                ['BH-CON-002',  'construction','Cement OPC 42.5N (bag)',      18.00, 'bag', 50000,2],
            ],
        ];

        foreach ($catalog as $key => $items) {
            $company = $this->companies[$key];
            $branch = $this->branches[$key] ?? null;
            foreach ($items as [$sku, $catSlug, $name, $price, $unit, $stock, $lead]) {
                Product::updateOrCreate(
                    ['company_id' => $company->id, 'sku' => $sku],
                    [
                        'branch_id' => $branch?->id,
                        'category_id' => $this->categories[$catSlug]->id,
                        'name' => $name,
                        'name_ar' => $name,
                        'description' => "Supplied by {$company->name}. UAE-compliant specifications.",
                        'base_price' => $price,
                        'currency' => 'AED',
                        'unit' => $unit,
                        'min_order_qty' => 1,
                        'stock_qty' => $stock,
                        'lead_time_days' => $lead,
                        'specs' => ['origin' => 'UAE', 'warranty' => '12 months'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  RFQs — every company publishes 2 RFQs in its primary category(ies)
    // ──────────────────────────────────────────────────────────────────

    private function seedRfqs(): void
    {
        $rfqDefs = [
            // buyer_key, category, title, budget, item_name, qty, unit, unit_price
            ['mansoori',    'it',           'Office IT Hardware Refresh',           450000, 'Laptops i7/16GB',           25,  'unit', 8500],
            ['mansoori',    'hvac',         'Headquarters HVAC Maintenance',        180000, 'HVAC annual service',        1,   'contract', 180000],
            ['esi',         'industrial',   'Heavy-Duty Forklift Procurement',      620000, 'Diesel forklift 5-ton',      4,   'unit', 155000],
            ['esi',         'logistics-svc','Bulk Steel Transport Contract',        320000, 'Flatbed trailer trips',      120, 'trip', 2650],
            ['dubaitech',   'office',       'Office Furniture Outfit — JLT',        215000, 'Workstations + chairs',      45,  'set', 4600],
            ['dubaitech',   'electrical',   'Data Centre UPS Upgrade',              380000, 'Modular UPS 80kVA',          2,   'unit', 185000],
            ['gulfmed',     'logistics-svc','Cold-Chain Distribution Contract',     275000, 'Refrigerated deliveries',    100, 'trip', 2750],
            ['gulfmed',     'construction', 'Warehouse Expansion Build-out',        890000, 'Build-out works',            1,   'project', 890000],
            ['futlog',      'industrial',   'Fleet Hydraulic Lift Systems',         340000, 'Hydraulic tail-lift',        12,  'unit', 28000],
            ['futlog',      'it',           'Warehouse Management System',          265000, 'WMS license + deploy',       1,   'package', 265000],
            ['khalifa',     'steel',        'Structural Steel for Plant Expansion', 780000, 'I-beams + plates',           240, 'ton', 3250],
            ['khalifa',     'electrical',   'Switchgear Procurement',               520000, 'MV switchgear cabinet',      8,   'unit', 64500],
            ['nfp',         'medical',      'Food-Grade Stainless Steel Pipes',     195000, 'SS-316L pipe 4-inch',        500, 'meter', 390],
            ['nfp',         'office',       'Annual Stationery Supply',              85000, 'Stationery bundles',         150, 'bundle', 565],
            ['binhamoodah', 'steel',        'Rebar Supply — Project X',            1450000, 'Grade 60 rebar 12mm',         580, 'ton', 2500],
            ['binhamoodah', 'hvac',         'HVAC Package — Downtown Tower',        960000, 'Rooftop chiller 20TR',        15,  'unit', 64000],
        ];

        $idx = 0;
        foreach ($rfqDefs as [$buyerKey, $catSlug, $title, $budget, $itemName, $qty, $unit, $unitPrice]) {
            $idx++;
            $buyer = $this->companies[$buyerKey];
            $branch = $this->branches[$buyerKey] ?? null;
            $buyerUser = $this->users["{$buyerKey}.buyer"];

            // Purchase request (precursor to RFQ)
            $pr = PurchaseRequest::create([
                'title' => $title,
                'description' => "Procurement request for {$itemName}. Prepared by {$buyer->name}.",
                'company_id' => $buyer->id,
                'branch_id' => $branch?->id,
                'buyer_id' => $buyerUser->id,
                'category_id' => $this->categories[$catSlug]->id,
                'status' => PurchaseRequestStatus::APPROVED,
                'items' => [[
                    'name' => $itemName, 'qty' => $qty, 'unit' => $unit,
                    'spec' => "Compliant with UAE standards. Delivery to {$buyer->city}.",
                    'price' => $unitPrice,
                ]],
                'budget' => $budget,
                'currency' => 'AED',
                'delivery_location' => [
                    'city' => $buyer->city, 'country' => 'AE',
                    'address' => $buyer->address, 'contact' => $buyerUser->email,
                ],
                'required_date' => $this->now->addMonths(2)->toDateString(),
                'approval_history' => [[
                    'by' => $this->users["{$buyerKey}.manager"]->id,
                    'at' => $this->now->subWeeks(2)->toIso8601String(),
                    'decision' => 'approved',
                ]],
                'rfq_generated' => true,
            ]);

            // Every RFQ is CLOSED and awarded so each company ends up on
            // BOTH sides of the marketplace: buyer on the 2 RFQs it
            // published, supplier on ~2 RFQs published by others.
            // Two RFQs are kept OPEN for UI variety (idx 6 and 13).
            $status = in_array($idx, [6, 13], true) ? RfqStatus::OPEN : RfqStatus::CLOSED;

            $rfq = Rfq::create([
                'rfq_number' => sprintf('RFQ-%s-%04d', $this->now->format('Y'), 1000 + $idx),
                'title' => $title,
                'description' => "Public RFQ for {$itemName}. Submit competitive pricing, delivery, and warranty terms.",
                'company_id' => $buyer->id,
                'branch_id' => $branch?->id,
                'purchase_request_id' => $pr->id,
                'type' => match ($catSlug) {
                    'logistics-svc' => RfqType::LOGISTICS,
                    default => RfqType::SUPPLIER,
                },
                'target_role' => 'supplier',
                'target_company_ids' => [],
                'status' => $status,
                'items' => $pr->items,
                'budget' => $budget,
                'currency' => 'AED',
                'deadline' => $status === RfqStatus::CLOSED
                    ? $this->now->subWeeks(1)
                    : $this->now->addWeeks(3),
                'delivery_location' => $pr->delivery_location,
                'is_anonymous' => $idx % 5 === 0,
                'category_id' => $this->categories[$catSlug]->id,
                'is_auction' => false,
                'icv_weight_percentage' => $idx % 4 === 0 ? 30 : 0,
                'icv_minimum_score' => $idx % 4 === 0 ? 40.0 : null,
            ]);

            $this->rfqs[] = $rfq;
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  BIDS & CONTRACTS — each company bids on 3–4 others' RFQs;
    //  winners get full contract + escrow + payments + shipment + invoice.
    // ──────────────────────────────────────────────────────────────────

    private function seedBidsAndContracts(): void
    {
        $companyKeys = array_keys($this->companies);
        $contractIdx = 0;

        foreach ($this->rfqs as $rfqIdx => $rfq) {
            // Pick 4 bidders. The winner is chosen by deterministic
            // rotation across the 8 companies so every company wins
            // at least ~2 contracts as SUPPLIER — paired with the
            // 2 contracts it owns as BUYER, this guarantees the full
            // dual-role marketplace lights up.
            $buyerKey = array_search($rfq->company_id, array_map(fn ($c) => $c->id, $this->companies), true);
            $buyerIdx = array_search($buyerKey, $companyKeys, true);
            // Pure rotation by rfqIdx walks every company through the
            // winner slot as RFQs are awarded, so every company earns
            // at least one supplier-side contract regardless of which
            // company owns the RFQ. If the rotation lands on the
            // buyer's own slot we just step forward one.
            $winnerIdx = $rfqIdx % count($companyKeys);
            if ($winnerIdx === $buyerIdx) {
                $winnerIdx = ($winnerIdx + 1) % count($companyKeys);
            }
            $winnerKey = $companyKeys[$winnerIdx];

            $otherBidders = array_values(array_filter(
                $companyKeys,
                fn ($k) => $k !== $buyerKey && $k !== $winnerKey
            ));
            // Deterministic 3 extra bidders, rotating per RFQ.
            $extra = [];
            for ($j = 0; $j < 3; $j++) {
                $extra[] = $otherBidders[($rfqIdx + $j) % count($otherBidders)];
            }
            $bidders = array_merge([$winnerKey], array_values(array_unique($extra)));
            $bidders = array_slice($bidders, 0, 4);

            $bids = [];
            $baseBudget = (float) $rfq->budget;
            foreach ($bidders as $i => $bidderKey) {
                $supplier = $this->companies[$bidderKey];
                $supplierUser = $this->users["{$bidderKey}.sales"];

                // Bid price: some above, some below, one competitive
                $multipliers = [0.92, 0.96, 1.02, 1.08];
                $price = round($baseBudget * $multipliers[$i], 2);
                $delivery = [14, 21, 30, 45][$i];

                // Use items from RFQ with filled unit_price
                $rfqItems = $rfq->items ?? [];
                $items = array_map(function ($it) use ($price, $rfqItems) {
                    $qty = (float) ($it['qty'] ?? 1);
                    $countItems = max(count($rfqItems), 1);
                    return [
                        'name' => $it['name'] ?? 'Item',
                        'qty' => $qty,
                        'unit' => $it['unit'] ?? 'unit',
                        'unit_price' => round($price / max($qty * $countItems, 1), 2),
                    ];
                }, $rfqItems);

                $subtotal = round($price / 1.05, 2);
                $vat = round($price - $subtotal, 2);

                $status = match ($rfq->status) {
                    RfqStatus::CLOSED => $i === 0 ? BidStatus::ACCEPTED : BidStatus::REJECTED,
                    default => $i === 0 ? BidStatus::UNDER_REVIEW : BidStatus::SUBMITTED,
                };

                $bid = Bid::create([
                    'rfq_id' => $rfq->id,
                    'company_id' => $supplier->id,
                    'provider_id' => $supplierUser->id,
                    'status' => $status,
                    'price' => $price,
                    'currency' => 'AED',
                    'delivery_time_days' => $delivery,
                    'payment_terms' => '30% advance, 50% on delivery, 20% net 30',
                    'payment_schedule' => [
                        ['milestone' => 'advance',  'percent' => 30],
                        ['milestone' => 'delivery', 'percent' => 50],
                        ['milestone' => 'final',    'percent' => 20],
                    ],
                    'items' => $items,
                    'validity_date' => $this->now->addDays(30),
                    'is_anonymous' => (bool) $rfq->is_anonymous,
                    'attachments' => [],
                    'ai_score' => [
                        'overall' => 60 + (($supplier->id + $rfq->id + $i) % 40),
                        'compliance' => 70 + (($supplier->id * 3) % 30),
                        'rating' => round(3.5 + (($supplier->id % 15) / 10), 1),
                    ],
                    'notes' => "Bid submitted by {$supplier->name}. Includes 12-month warranty.",
                    'incoterm' => 'DAP',
                    'country_of_origin' => 'AE',
                    'tax_rate_snapshot' => 5.00,
                    'subtotal_excl_tax' => $subtotal,
                    'tax_amount' => $vat,
                    'total_incl_tax' => $price,
                    'negotiation_round_cap' => $price >= 250000 ? 3 : 5,
                ]);
                $bids[] = $bid;
            }

            // Negotiation rounds on the front-runner (every RFQ)
            $front = $bids[0];
            $this->seedNegotiation($front, $rfq);

            // Award RFQs that are marked CLOSED → full contract lifecycle
            if ($rfq->status === RfqStatus::CLOSED) {
                $contractIdx++;
                $this->awardContract($rfq, $front, $contractIdx);
            }
        }
    }

    private function seedNegotiation(Bid $bid, Rfq $rfq): void
    {
        $supplier = $bid->company;
        $buyer = $rfq->company;
        $supplierUser = $this->users[$this->companyKey($supplier->id).'.sales'];
        $buyerUser = $this->users[$this->companyKey($buyer->id).'.buyer'];

        $origPrice = (float) $bid->price;
        $counter = round($origPrice * 0.93, 2);
        $final = round($origPrice * 0.95, 2);

        // Round 1 — buyer counter-offer
        NegotiationMessage::create([
            'bid_id' => $bid->id,
            'sender_id' => $buyerUser->id,
            'sender_side' => 'buyer',
            'kind' => 'counter_offer',
            'body' => 'We can proceed if price is adjusted by 7% and delivery shortened to 21 days.',
            'offer' => ['price' => $counter, 'currency' => 'AED', 'delivery_days' => 21],
            'round_number' => 1,
            'round_status' => 'responded',
            'expires_at' => $this->now->subDays(2),
            'responded_at' => $this->now->subDays(1),
            'responded_by' => $supplierUser->id,
            'subtotal_excl_tax' => round($counter / 1.05, 2),
            'tax_amount' => round($counter - ($counter / 1.05), 2),
            'total_incl_tax' => $counter,
        ]);

        // Round 2 — supplier final offer + signed acceptance
        NegotiationMessage::create([
            'bid_id' => $bid->id,
            'sender_id' => $supplierUser->id,
            'sender_side' => 'supplier',
            'kind' => 'final_offer',
            'body' => 'Best and final offer. 5% reduction, delivery in 28 days, includes warranty extension.',
            'offer' => ['price' => $final, 'currency' => 'AED', 'delivery_days' => 28],
            'round_number' => 2,
            'round_status' => 'accepted',
            'expires_at' => $this->now->addDays(3),
            'responded_at' => $this->now,
            'responded_by' => $buyerUser->id,
            'subtotal_excl_tax' => round($final / 1.05, 2),
            'tax_amount' => round($final - ($final / 1.05), 2),
            'total_incl_tax' => $final,
            'signed_by_name' => $buyerUser->first_name.' '.$buyerUser->last_name,
            'signed_at' => $this->now,
            'signature_ip' => '192.168.10.'.($bid->id % 250),
            'signature_hash' => hash('sha256', $bid->id.'|'.$final.'|'.$buyerUser->id),
        ]);
    }

    private function awardContract(Rfq $rfq, Bid $winningBid, int $idx): void
    {
        $buyer = $rfq->company;
        $supplier = $winningBid->company;
        $total = (float) $winningBid->price;
        $currency = 'AED';

        $contract = Contract::create([
            'contract_number' => sprintf('CNT-%s-%04d', $this->now->format('Y'), 2000 + $idx),
            'title' => 'Contract — '.$rfq->title,
            'description' => "Binding agreement between {$buyer->name} (buyer) and {$supplier->name} (supplier) for {$rfq->title}.",
            'purchase_request_id' => $rfq->purchase_request_id,
            'buyer_company_id' => $buyer->id,
            'branch_id' => $rfq->branch_id,
            'status' => $idx % 3 === 0 ? ContractStatus::COMPLETED : ContractStatus::ACTIVE,
            'parties' => [
                ['company_id' => $buyer->id,    'role' => 'buyer',    'signed' => true],
                ['company_id' => $supplier->id, 'role' => 'supplier', 'signed' => true],
            ],
            'amounts' => ['subtotal' => round($total / 1.05, 2), 'vat' => round($total - $total / 1.05, 2), 'total' => $total],
            'total_amount' => $total,
            'currency' => $currency,
            'payment_schedule' => [
                ['milestone' => 'advance',  'percent' => 30, 'amount' => round($total * 0.30, 2)],
                ['milestone' => 'delivery', 'percent' => 50, 'amount' => round($total * 0.50, 2)],
                ['milestone' => 'final',    'percent' => 20, 'amount' => round($total * 0.20, 2)],
            ],
            'signatures' => [
                ['party' => 'buyer',    'signed_by' => $this->users[$this->companyKey($buyer->id).'.manager']->id, 'signed_at' => $this->now->subWeeks(3)->toIso8601String()],
                ['party' => 'supplier', 'signed_by' => $this->users[$this->companyKey($supplier->id).'.manager']->id, 'signed_at' => $this->now->subWeeks(3)->toIso8601String()],
            ],
            'terms' => "Standard UAE commercial terms. Governing law: UAE Federal. Dispute resolution per UAE Civil Code.",
            'start_date' => $this->now->subWeeks(3)->toDateString(),
            'end_date' => $this->now->addWeeks(8)->toDateString(),
            'version' => 1,
            'progress_percentage' => $idx % 3 === 0 ? 100 : 45,
            'payment_terms' => 'NET_30',
            'vat_treatment' => 'STANDARD',
            'corporate_tax_applicable' => true,
            'default_wht_rate' => 0.00,
            'retention_percentage' => 5.00,
            'retention_amount' => round($total * 0.05, 2),
            'retention_release_date' => $this->now->addMonths(3)->toDateString(),
        ]);

        // Escrow account
        $escrow = EscrowAccount::create([
            'contract_id' => $contract->id,
            'bank_partner' => 'Mashreq Bank',
            'external_account_id' => 'ESC-'.$contract->id.'-'.strtoupper(substr(md5((string) $contract->id), 0, 8)),
            'currency' => $currency,
            'total_deposited' => $total,
            'total_released' => $contract->status === ContractStatus::COMPLETED ? $total : round($total * 0.30, 2),
            'status' => $contract->status === ContractStatus::COMPLETED ? 'closed' : 'active',
            'activated_at' => $this->now->subWeeks(3),
            'closed_at' => $contract->status === ContractStatus::COMPLETED ? $this->now->subDays(2) : null,
            'metadata' => ['buyer' => $buyer->name, 'supplier' => $supplier->name],
        ]);

        // Payments per milestone
        $milestones = [
            ['advance',  0.30, PaymentStatus::COMPLETED],
            ['delivery', 0.50, $contract->status === ContractStatus::COMPLETED ? PaymentStatus::COMPLETED : PaymentStatus::PROCESSING],
            ['final',    0.20, $contract->status === ContractStatus::COMPLETED ? PaymentStatus::COMPLETED : PaymentStatus::PENDING_APPROVAL],
        ];

        $buyerFinance = $this->users[$this->companyKey($buyer->id).'.finance'];
        $buyerApprover = $this->users[$this->companyKey($buyer->id).'.manager'];

        foreach ($milestones as [$milestone, $pct, $status]) {
            $amount = round($total * $pct, 2);
            $vat = round($amount * 0.05 / 1.05, 2);
            $net = round($amount - $vat, 2);

            $payment = Payment::create([
                'contract_id' => $contract->id,
                'company_id' => $buyer->id,
                'recipient_company_id' => $supplier->id,
                'buyer_id' => $buyerFinance->id,
                'status' => $status,
                'amount' => $net,
                'vat_rate' => 5.00,
                'vat_amount' => $vat,
                'total_amount' => $amount,
                'currency' => $currency,
                'milestone' => $milestone,
                'payment_gateway' => 'mashreq_bank',
                'approved_at' => $status === PaymentStatus::COMPLETED ? $this->now->subDays(5) : null,
                'approved_by' => $status === PaymentStatus::COMPLETED ? $buyerApprover->id : null,
                'invoice_issued_at' => $status === PaymentStatus::COMPLETED ? $this->now->subDays(4)->toDateString() : null,
                'due_date' => $this->now->addDays(30)->toDateString(),
                'paid_date' => $status === PaymentStatus::COMPLETED ? $this->now->subDays(3) : null,
                'rail' => 'UAEFTS',
                'fx_rate_snapshot' => 1.00000000,
                'fx_base_currency' => 'AED',
                'fx_locked_at' => $this->now->subDays(5),
                'amount_in_base' => $amount,
                'corporate_tax_applicable' => true,
                'corporate_tax_rate' => 9.00,
                'corporate_tax_amount' => round($net * 0.09, 2),
                'requires_dual_approval' => $amount >= 500000,
                'settled_at' => $status === PaymentStatus::COMPLETED ? $this->now->subDays(3) : null,
            ]);

            if ($status === PaymentStatus::COMPLETED) {
                EscrowRelease::create([
                    'escrow_account_id' => $escrow->id,
                    'payment_id' => $payment->id,
                    'type' => 'release',
                    'amount' => $amount,
                    'currency' => $currency,
                    'milestone' => $milestone,
                    'triggered_by' => 'milestone_completion',
                    'triggered_by_user_id' => $buyerApprover->id,
                    'bank_reference' => 'MSHR-REL-'.$payment->id,
                    'recorded_at' => $this->now->subDays(3),
                    'confirmed_at' => $this->now->subDays(3),
                ]);

                // Tax invoice issued by supplier to buyer
                TaxInvoice::create([
                    'invoice_number' => sprintf('INV-%d-%d', $supplier->id, 10000 + $payment->id),
                    'contract_id' => $contract->id,
                    'payment_id' => $payment->id,
                    'issue_date' => $this->now->subDays(4)->toDateString(),
                    'supply_date' => $this->now->subDays(4)->toDateString(),
                    'supplier_company_id' => $supplier->id,
                    'supplier_trn' => $supplier->tax_number,
                    'supplier_name' => $supplier->name,
                    'supplier_address' => $supplier->address,
                    'supplier_country' => 'AE',
                    'buyer_company_id' => $buyer->id,
                    'buyer_trn' => $buyer->tax_number,
                    'buyer_name' => $buyer->name,
                    'buyer_address' => $buyer->address,
                    'buyer_country' => 'AE',
                    'line_items' => [[
                        'description' => $contract->title.' — '.ucfirst($milestone).' milestone',
                        'quantity' => 1, 'unit_price' => $net, 'total' => $net,
                    ]],
                    'subtotal_excl_tax' => $net,
                    'total_discount' => 0,
                    'total_tax' => $vat,
                    'total_inclusive' => $amount,
                    'currency' => $currency,
                    'vat_treatment' => 'STANDARD',
                    'status' => 'issued',
                    'issued_by' => $this->users[$this->companyKey($supplier->id).'.finance']->id,
                    'issued_at' => $this->now->subDays(4),
                ]);
            }
        }

        // Shipment (once delivery milestone is active)
        $logisticsKey = $this->companyKey($supplier->id);
        $shipmentStatus = $contract->status === ContractStatus::COMPLETED
            ? ShipmentStatus::DELIVERED
            : ShipmentStatus::IN_TRANSIT;

        $shipment = Shipment::create([
            'tracking_number' => 'SHP-'.strtoupper(substr(md5((string) $contract->id), 0, 10)),
            'contract_id' => $contract->id,
            'company_id' => $supplier->id,
            'logistics_company_id' => $this->companies['futlog']->id,
            'status' => $shipmentStatus,
            'origin' => ['city' => $supplier->city, 'country' => 'AE', 'address' => $supplier->address],
            'destination' => ['city' => $buyer->city, 'country' => 'AE', 'address' => $buyer->address],
            'current_location' => $shipmentStatus === ShipmentStatus::DELIVERED
                ? ['city' => $buyer->city, 'country' => 'AE']
                : ['city' => 'Al Ain', 'country' => 'AE'],
            'inspection_status' => 'cleared',
            'customs_clearance_status' => 'cleared',
            'customs_documents' => [],
            'estimated_delivery' => $this->now->addDays(5),
            'actual_delivery' => $shipmentStatus === ShipmentStatus::DELIVERED ? $this->now->subDays(6) : null,
            'notes' => 'Sealed and insured shipment.',
        ]);

        foreach ([
            [ShipmentStatus::IN_PRODUCTION,   'Production started',          $this->now->subWeeks(2), $supplier->city],
            [ShipmentStatus::READY_FOR_PICKUP,'Ready for dispatch',          $this->now->subDays(10), $supplier->city],
            [ShipmentStatus::IN_TRANSIT,      'Shipment picked up',          $this->now->subDays(9),  $supplier->city],
            [ShipmentStatus::IN_TRANSIT,      'In transit via E11 highway',  $this->now->subDays(8),  'Al Ain'],
        ] as [$evStatus, $desc, $at, $loc]) {
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $evStatus,
                'description' => $desc,
                'location' => ['city' => $loc, 'country' => 'AE'],
                'event_at' => $at,
            ]);
        }

        if ($shipmentStatus === ShipmentStatus::DELIVERED) {
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::DELIVERED,
                'description' => 'Delivered and signed for at destination',
                'location' => ['city' => $buyer->city, 'country' => 'AE'],
                'event_at' => $this->now->subDays(6),
            ]);
        }

        // Feedback for completed contracts — every company ends up rating the
        // counterparty at some point, so ratings flow in both directions.
        if ($contract->status === ContractStatus::COMPLETED) {
            Feedback::create([
                'contract_id' => $contract->id,
                'rater_company_id' => $buyer->id,
                'target_company_id' => $supplier->id,
                'rater_user_id' => $this->users[$this->companyKey($buyer->id).'.manager']->id,
                'rating' => 5,
                'comment' => 'Delivered on time with excellent quality.',
                'quality_score' => 5, 'on_time_score' => 5, 'communication_score' => 4,
            ]);
            Feedback::create([
                'contract_id' => $contract->id,
                'rater_company_id' => $supplier->id,
                'target_company_id' => $buyer->id,
                'rater_user_id' => $this->users[$this->companyKey($supplier->id).'.manager']->id,
                'rating' => 5,
                'comment' => 'Clear scope and timely payments — pleasure to work with.',
                'quality_score' => 5, 'on_time_score' => 5, 'communication_score' => 5,
            ]);
        }

        // Occasional dispute (every 5th contract)
        if ($idx % 5 === 0 && $contract->status === ContractStatus::ACTIVE) {
            Dispute::create([
                'contract_id' => $contract->id,
                'company_id' => $buyer->id,
                'raised_by' => $this->users[$this->companyKey($buyer->id).'.buyer']->id,
                'against_company_id' => $supplier->id,
                'type' => DisputeType::DELIVERY,
                'status' => DisputeStatus::UNDER_NEGOTIATION,
                'title' => 'Partial delivery short of 8%',
                'description' => 'Received shipment missing approx. 8% of ordered quantity. Awaiting supplier response.',
                'claim_amount' => round($total * 0.08, 2),
                'claim_currency' => $currency,
                'requested_remedy' => 'refund',
                'severity' => DisputeSeverity::MEDIUM,
                'escalated_to_government' => false,
                'sla_due_date' => $this->now->addDays(10),
                'response_due_at' => $this->now->addDays(3),
                'acknowledged_at' => $this->now->subDays(1),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  CONSENTS — PDPL baseline: every user has cookie + processing consent
    // ──────────────────────────────────────────────────────────────────

    private function seedConsents(): void
    {
        foreach ($this->users as $user) {
            foreach (['cookies', 'data_processing', 'marketing'] as $type) {
                Consent::updateOrCreate(
                    ['user_id' => $user->id, 'consent_type' => $type],
                    [
                        'version' => $this->privacyPolicy->version,
                        'privacy_policy_version_id' => $this->privacyPolicy->id,
                        'granted_at' => $this->now->subMonths(2),
                        'ip_address' => '10.0.0.'.($user->id % 250),
                        'user_agent' => 'Mozilla/5.0 (Seeder)',
                    ]
                );
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────

    private function companyKey(int $companyId): string
    {
        foreach ($this->companies as $key => $company) {
            if ($company->id === $companyId) {
                return $key;
            }
        }
        throw new \RuntimeException("Unknown company id {$companyId}");
    }
}
