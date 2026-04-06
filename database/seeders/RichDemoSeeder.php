<?php

namespace Database\Seeders;

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
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Rich demo seeder. Produces realistic, internally-consistent data so the
 * dynamic dashboard views render with correctly-shaped content:
 *
 *  - Contract.terms is structured JSON (array of {title, items[]})
 *  - Contract.payment_schedule milestone keys ("advance", "production",
 *    "delivery") match the substring of Payment.milestone strings, so
 *    ContractController::show can mark each milestone as paid/pending/future.
 *  - RFQ items carry name/qty/unit/specs[] so RfqController::show can
 *    surface tech specs.
 *  - Bid.ai_score has overall/compliance/rating used by RfqController::show
 *    and bids/show.blade.php.
 *  - PurchaseRequest.approval_history is populated for non-draft PRs so
 *    PurchaseRequestController::buildPrTimeline emits real events.
 *  - Shipment tracking_events use ShipmentStatus enum values as status so
 *    ShipmentController::buildTimeline maps them to UI phases correctly.
 *  - Each supplier/buyer company has at least one user, so contract
 *    "parties" can show a real contact name + email.
 */
class RichDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================
        // Categories
        // ============================================
        $catElec = Category::updateOrCreate(['name' => 'Electronics'],     ['description' => 'Electronics & components',   'level' => 0, 'path' => 'electronics',  'is_active' => true]);
        $catCons = Category::updateOrCreate(['name' => 'Construction'],    ['description' => 'Construction materials',     'level' => 0, 'path' => 'construction', 'is_active' => true]);
        $catIT   = Category::updateOrCreate(['name' => 'IT Hardware'],     ['description' => 'IT hardware & networking',   'level' => 0, 'path' => 'it-hardware',  'is_active' => true]);
        $catMed  = Category::updateOrCreate(['name' => 'Medical'],         ['description' => 'Medical equipment',          'level' => 0, 'path' => 'medical',      'is_active' => true]);
        $catOff  = Category::updateOrCreate(['name' => 'Office Supplies'], ['description' => 'Office furniture & supplies','level' => 0, 'path' => 'office',       'is_active' => true]);
        $catLog  = Category::updateOrCreate(['name' => 'Logistics'],       ['description' => 'Logistics & shipping',       'level' => 0, 'path' => 'logistics',    'is_active' => true]);

        // ============================================
        // Companies — buyer + 5 suppliers + 1 logistics
        // ============================================
        $buyer = Company::updateOrCreate(
            ['registration_number' => 'BUY-AHRAM-001'],
            [
                'name'    => 'Al-Ahram Group',
                'name_ar' => 'مجموعة الأهرام',
                'type'    => CompanyType::BUYER->value,
                'status'  => CompanyStatus::ACTIVE->value,
                'email'   => 'info@al-ahram.test',
                'phone'   => '+971 50 000 0010',
                'address' => 'Sheikh Zayed Road, Tower 12, Floor 18',
                'city'    => 'Dubai',
                'country' => 'UAE',
            ]
        );

        $supplierSeed = [
            ['SUP-EMIND-001',  'Emirates Industrial Co.',   'شركة الإمارات الصناعية',   'info@emirates-ind.test',   '+971 50 000 0021', 'Industrial Area 5',         'Sharjah',     'electronics'],
            ['SUP-KHOORY-001', 'Al-Khoory Trading LLC',     'الخوري للتجارة',           'sales@khoory.test',        '+971 50 000 0022', 'Deira, Al Maktoum St.',     'Dubai',       'construction'],
            ['SUP-DBTECH-001', 'Dubai Tech Solutions',      'دبي تك سوليوشنز',         'sales@dbtech.test',        '+971 50 000 0023', 'Dubai Internet City',       'Dubai',       'it'],
            ['SUP-GULFE-001',  'Gulf Electronics Trading',  'الخليج للإلكترونيات',      'info@gulfe.test',          '+971 50 000 0024', 'Mussafah Industrial Area',  'Abu Dhabi',   'office'],
            ['SUP-MEDCO-001',  'MedCo Diagnostics',         'ميدكو للأجهزة الطبية',    'info@medco.test',          '+971 50 000 0025', 'Healthcare City',           'Dubai',       'medical'],
        ];

        $suppliers = collect($supplierSeed)->map(fn ($s) => Company::updateOrCreate(
            ['registration_number' => $s[0]],
            [
                'name'    => $s[1],
                'name_ar' => $s[2],
                'type'    => CompanyType::SUPPLIER->value,
                'status'  => CompanyStatus::ACTIVE->value,
                'email'   => $s[3],
                'phone'   => $s[4],
                'address' => $s[5],
                'city'    => $s[6],
                'country' => 'UAE',
            ]
        ));

        $logistics = Company::updateOrCreate(
            ['registration_number' => 'LOG-FASTLINE-001'],
            [
                'name'    => 'FastLine Logistics',
                'name_ar' => 'فاست لاين للخدمات اللوجستية',
                'type'    => CompanyType::LOGISTICS->value,
                'status'  => CompanyStatus::ACTIVE->value,
                'email'   => 'dispatch@fastline.test',
                'phone'   => '+971 50 000 0050',
                'address' => 'Jebel Ali Free Zone, Block C',
                'city'    => 'Dubai',
                'country' => 'UAE',
            ]
        );

        // ============================================
        // Users — buyer + admin + a contact per supplier + logistics + a gov user
        // ============================================
        $ahmed = User::updateOrCreate(
            ['email' => 'ahmed@al-ahram.test'],
            [
                'first_name' => 'Ahmed',
                'last_name'  => 'Al-Mansoori',
                'phone'      => '+971 50 111 0001',
                'password'   => Hash::make('password'),
                'role'       => UserRole::BUYER->value,
                'status'     => UserStatus::ACTIVE->value,
                'company_id' => $buyer->id,
            ]
        );

        $manager = User::updateOrCreate(
            ['email' => 'manager@al-ahram.test'],
            [
                'first_name' => 'Khalid',
                'last_name'  => 'Hassan',
                'phone'      => '+971 50 111 0002',
                'password'   => Hash::make('password'),
                'role'       => UserRole::COMPANY_MANAGER->value,
                'status'     => UserStatus::ACTIVE->value,
                'company_id' => $buyer->id,
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'System',
                'last_name'  => 'Admin',
                'phone'      => '+971 50 999 0000',
                'password'   => Hash::make('password'),
                'role'       => UserRole::ADMIN->value,
                'status'     => UserStatus::ACTIVE->value,
            ]
        );

        $gov = User::updateOrCreate(
            ['email' => 'gov@trilink.test'],
            [
                'first_name' => 'Salim',
                'last_name'  => 'Al-Rashid',
                'phone'      => '+971 50 222 0000',
                'password'   => Hash::make('password'),
                'role'       => UserRole::GOVERNMENT->value,
                'status'     => UserStatus::ACTIVE->value,
            ]
        );

        // Contact users — one primary contact per supplier company.
        $supplierContactSeed = [
            ['mohammed@emirates-ind.test', 'Mohammed', 'Hassan',     0],
            ['fatima@khoory.test',         'Fatima',   'Al-Zaabi',   1],
            ['rashid@dbtech.test',         'Rashid',   'Al-Maktoum', 2],
            ['layla@gulfe.test',           'Layla',    'Al-Otaibi',  3],
            ['omar@medco.test',            'Omar',     'Al-Sharif',  4],
        ];
        foreach ($supplierContactSeed as $sc) {
            User::updateOrCreate(
                ['email' => $sc[0]],
                [
                    'first_name' => $sc[1],
                    'last_name'  => $sc[2],
                    'phone'      => '+971 55 100 ' . str_pad((string) ($sc[3] * 100), 4, '0', STR_PAD_LEFT),
                    'password'   => Hash::make('password'),
                    'role'       => UserRole::SUPPLIER->value,
                    'status'     => UserStatus::ACTIVE->value,
                    'company_id' => $suppliers[$sc[3]]->id,
                ]
            );
        }

        $logisticsContact = User::updateOrCreate(
            ['email' => 'driver@fastline.test'],
            [
                'first_name' => 'Yousef',
                'last_name'  => 'Al-Bedouin',
                'phone'      => '+971 55 200 0001',
                'password'   => Hash::make('password'),
                'role'       => UserRole::LOGISTICS->value,
                'status'     => UserStatus::ACTIVE->value,
                'company_id' => $logistics->id,
            ]
        );

        // ============================================
        // Purchase Requests (6) — items as array, delivery_location as array,
        // approval_history populated for non-draft.
        // ============================================
        $prSeed = [
            // [title, status, category, budget, description, items, days_from_now]
            [
                'Office Equipment & IT Hardware - Q2 2026',
                PurchaseRequestStatus::APPROVED, $catIT, 450000,
                'Laptops, monitors, and ergonomic furniture for the new Dubai branch.',
                [
                    ['name' => 'Dell Laptop XPS 15',         'qty' => 50, 'unit' => 'pcs', 'price' => 6500, 'spec' => 'i7, 32GB RAM, 1TB SSD, 3-year warranty'],
                    ['name' => 'Dell UltraSharp 27" Monitor','qty' => 50, 'unit' => 'pcs', 'price' => 1800, 'spec' => '4K IPS, USB-C, height-adjustable'],
                ],
                25,
            ],
            [
                'Construction Materials - Cement & Steel',
                PurchaseRequestStatus::SUBMITTED, $catCons, 890000,
                'Portland cement (500 tons) and steel reinforcement bars (200 tons) for the new warehouse build.',
                [
                    ['name' => 'Portland Cement', 'qty' => 500, 'unit' => 'tons', 'price' => 850,  'spec' => 'Grade 42.5N, sulfate-resistant'],
                    ['name' => 'Steel Rebar 16mm','qty' => 200, 'unit' => 'tons', 'price' => 2300, 'spec' => 'B500B grade, ribbed surface'],
                ],
                40,
            ],
            [
                'Industrial HVAC Systems',
                PurchaseRequestStatus::PENDING_APPROVAL, $catCons, 320000,
                'Industrial HVAC for the warehouse — 5 rooftop units, full ducting and installation.',
                [
                    ['name' => 'Rooftop HVAC Unit', 'qty' => 5,   'unit' => 'units', 'price' => 55000, 'spec' => '20-ton capacity, R-32 refrigerant'],
                    ['name' => 'Galvanized Ducting','qty' => 200, 'unit' => 'm',     'price' => 220,   'spec' => '0.7mm steel, fire-rated'],
                ],
                55,
            ],
            [
                'Marketing Materials & Printing',
                PurchaseRequestStatus::DRAFT, $catOff, 75000,
                'Brochures, banners, and promotional kits for Q2 trade shows.',
                [
                    ['name' => 'Trifold Brochure (full color)', 'qty' => 5000, 'unit' => 'pcs', 'price' => 4,  'spec' => '170gsm gloss paper'],
                    ['name' => 'Pop-up Banner',                 'qty' => 20,   'unit' => 'pcs', 'price' => 350,'spec' => '2x0.85m, full bleed'],
                ],
                14,
            ],
            [
                'Copper Wire & Electrical Components',
                PurchaseRequestStatus::APPROVED, $catElec, 95000,
                'Copper wire 16mm and switchgear for the electrical fit-out.',
                [
                    ['name' => 'Copper Wire 16mm', 'qty' => 10, 'unit' => 'tons', 'price' => 8500, 'spec' => 'IEC 60228 compliant, 99.9% purity'],
                    ['name' => 'Industrial Switchgear', 'qty' => 5, 'unit' => 'units', 'price' => 2000, 'spec' => 'IP65 rated'],
                ],
                30,
            ],
            [
                'Vehicle Fleet Maintenance',
                PurchaseRequestStatus::REJECTED, $catCons, 180000,
                'Spare parts and scheduled service for the company vehicle fleet.',
                [
                    ['name' => 'Brake Pad Set',     'qty' => 60, 'unit' => 'sets', 'price' => 280, 'spec' => 'OEM equivalent'],
                    ['name' => 'Engine Oil 5W-30', 'qty' => 200,'unit' => 'L',    'price' => 45,  'spec' => 'Fully synthetic'],
                ],
                10,
            ],
        ];

        $prs = collect();
        foreach ($prSeed as $i => $d) {
            [$title, $status, $cat, $budget, $desc, $items, $daysAhead] = $d;

            $approvalHistory = [];
            $statusValue = $status->value;
            $createdAt = now()->subDays(20 - $i * 2);

            if (in_array($statusValue, ['submitted', 'pending_approval', 'approved', 'rejected'], true)) {
                $approvalHistory[] = [
                    'action' => 'Submitted for approval',
                    'by'     => $ahmed->first_name . ' ' . $ahmed->last_name,
                    'at'     => $createdAt->copy()->addHours(4)->toDateTimeString(),
                ];
            }
            if ($statusValue === 'approved') {
                $approvalHistory[] = [
                    'action' => 'Approved by company manager',
                    'by'     => $manager->first_name . ' ' . $manager->last_name,
                    'at'     => $createdAt->copy()->addHours(8)->toDateTimeString(),
                ];
            }
            if ($statusValue === 'rejected') {
                $approvalHistory[] = [
                    'action' => 'Rejected by company manager',
                    'by'     => $manager->first_name . ' ' . $manager->last_name,
                    'at'     => $createdAt->copy()->addHours(8)->toDateTimeString(),
                ];
            }

            $pr = PurchaseRequest::updateOrCreate(
                ['title' => $title],
                [
                    'description'       => $desc,
                    'company_id'        => $buyer->id,
                    'buyer_id'          => $ahmed->id,
                    'category_id'       => $cat->id,
                    'status'            => $statusValue,
                    'items'             => $items,
                    'budget'            => $budget,
                    'currency'          => 'AED',
                    'delivery_location' => [
                        'address' => 'Dubai Silicon Oasis, Building 12',
                        'city'    => 'Dubai',
                        'country' => 'UAE',
                    ],
                    'required_date'     => now()->addDays($daysAhead)->toDateString(),
                    'approval_history'  => $approvalHistory,
                    'rfq_generated'     => $statusValue === 'approved',
                ]
            );

            // Backdate created_at for realistic timestamps.
            $pr->created_at = $createdAt;
            $pr->updated_at = $createdAt->copy()->addHours(8);
            $pr->saveQuietly();

            $prs->push($pr);
        }

        // ============================================
        // RFQs (6) — items have specs[] arrays.
        // ============================================
        $rfqSeed = [
            // [title, status, category, budget, description, items, deadline_days, pr_index]
            [
                'Copper Wire 16mm – Electrical Components',
                RfqStatus::OPEN, $catElec, 95000,
                'Supply of copper wire 16mm gauge, 10 metric tons, compliant with IEC 60228 standards for electrical installations.',
                [[
                    'name'  => 'Copper Wire 16mm',
                    'qty'   => 10,
                    'unit'  => 'tons',
                    'specs' => [
                        'IEC 60228 compliant copper wire',
                        'Conductor size: 16mm gauge',
                        'Purity: 99.9% copper',
                        'Packaging: Industrial spools, 500m each',
                        'Quality certificates required before shipment',
                    ],
                ]],
                15, 4,
            ],
            [
                'Construction Materials – Cement & Steel Bars',
                RfqStatus::OPEN, $catCons, 180000,
                'Procurement of Portland cement (500 tons) and steel reinforcement bars (200 tons).',
                [
                    ['name' => 'Portland Cement', 'qty' => 500, 'unit' => 'tons', 'specs' => ['Grade 42.5N', 'Sulfate-resistant', 'Bagged or bulk delivery']],
                    ['name' => 'Steel Rebar 16mm','qty' => 200, 'unit' => 'tons', 'specs' => ['B500B grade', 'Ribbed surface', 'Mill test certificate required']],
                ],
                20, 1,
            ],
            [
                'Industrial HVAC Systems – Climate Control',
                RfqStatus::OPEN, $catCons, 320000,
                'Supply and installation of industrial HVAC systems for warehouse facility.',
                [[
                    'name'  => 'Rooftop HVAC Unit',
                    'qty'   => 5,
                    'unit'  => 'units',
                    'specs' => [
                        '20-ton capacity per unit',
                        'R-32 environmentally-friendly refrigerant',
                        'Smart controls with BACnet integration',
                        'Installation and commissioning included',
                    ],
                ]],
                25, 2,
            ],
            [
                'IT Hardware – Servers & Networking',
                RfqStatus::DRAFT, $catIT, 250000,
                'Procurement of enterprise-grade servers and networking equipment.',
                [
                    ['name' => 'Rack Server', 'qty' => 10, 'unit' => 'units', 'specs' => ['Dual Xeon Gold', '256GB ECC RAM', '4x 2TB NVMe SSD', '3-year warranty']],
                ],
                30, 0,
            ],
            [
                'Medical Diagnostic Equipment',
                RfqStatus::CLOSED, $catMed, 450000,
                'Import of medical diagnostic equipment including ultrasound and X-ray devices.',
                [
                    ['name' => 'Ultrasound System', 'qty' => 2, 'unit' => 'units', 'specs' => ['4D imaging', 'Touchscreen UI', 'CE marked']],
                ],
                10, 4,
            ],
            [
                'Office Furniture – Modular Workstations',
                RfqStatus::CLOSED, $catOff, 85000,
                'Supply of ergonomic office furniture for new headquarters (200 workstations).',
                [
                    ['name' => 'Modular Workstation', 'qty' => 200, 'unit' => 'sets', 'specs' => ['Sit/stand desk', 'Mesh-back ergonomic chair', '5-year warranty']],
                ],
                5, 0,
            ],
        ];

        $rfqs = collect();
        foreach ($rfqSeed as $i => $d) {
            [$title, $status, $cat, $budget, $desc, $items, $deadlineDays, $prIdx] = $d;

            $rfq = Rfq::updateOrCreate(
                ['title' => $title],
                [
                    'rfq_number'         => sprintf('RFQ-2024-%04d', 834 - $i * 13),
                    'description'        => $desc,
                    'company_id'         => $buyer->id,
                    'purchase_request_id'=> $prs[$prIdx]->id,
                    'type'               => RfqType::SUPPLIER->value,
                    'target_role'        => UserRole::SUPPLIER->value,
                    'target_company_ids' => $suppliers->pluck('id')->toArray(),
                    'status'             => $status->value,
                    'items'              => $items,
                    'budget'             => $budget,
                    'currency'           => 'AED',
                    'deadline'           => now()->addDays($deadlineDays),
                    'delivery_location'  => 'Dubai Silicon Oasis, Building 12, UAE',
                    'is_anonymous'       => false,
                    'category_id'        => $cat->id,
                ]
            );

            $rfq->created_at = now()->subDays(15 - $i);
            $rfq->saveQuietly();

            $rfqs->push($rfq);
        }

        // ============================================
        // Bids — multiple per RFQ with realistic ai_score.
        // ============================================
        $bidStatusByOffset = [
            BidStatus::SUBMITTED,
            BidStatus::UNDER_REVIEW,
            BidStatus::ACCEPTED,
            BidStatus::REJECTED,
            BidStatus::SUBMITTED,
        ];

        foreach ($rfqs->take(4) as $rfqIdx => $rfq) {
            foreach ($suppliers->take(3) as $supIdx => $sup) {
                $price = (float) $rfq->budget * (0.92 + ($supIdx * 0.03));
                $deliveryDays = 12 + $supIdx * 4;
                $rating     = round(4.5 + (mt_rand(0, 4) / 10), 1);
                $compliance = 90 + ($supIdx * 2);
                $overall    = (int) round(($rating * 10) + $compliance) / 2;

                Bid::updateOrCreate(
                    ['rfq_id' => $rfq->id, 'company_id' => $sup->id],
                    [
                        'provider_id'        => $admin->id,
                        'status'             => $bidStatusByOffset[($rfqIdx + $supIdx) % count($bidStatusByOffset)]->value,
                        'price'              => round($price, 2),
                        'currency'           => 'AED',
                        'delivery_time_days' => $deliveryDays,
                        'payment_terms'      => '30% advance, 50% on production, 20% on delivery',
                        'payment_schedule'   => [
                            ['milestone' => 'advance',    'percentage' => 30],
                            ['milestone' => 'production', 'percentage' => 50],
                            ['milestone' => 'delivery',   'percentage' => 20],
                        ],
                        'items'              => collect($rfq->items)->map(fn ($it) => [
                            'name'       => $it['name'] ?? 'Item',
                            'qty'        => $it['qty'] ?? 1,
                            'unit_price' => round($price / max(count($rfq->items), 1) / max((int) ($it['qty'] ?? 1), 1), 2),
                        ])->toArray(),
                        'validity_date'      => now()->addDays(30),
                        'is_anonymous'       => false,
                        'attachments'        => [],
                        'ai_score'           => [
                            'overall'    => $overall,
                            'compliance' => $compliance,
                            'rating'     => $rating,
                            'notes'      => 'Competitive offer with strong delivery track record.',
                        ],
                        'notes'              => 'Includes 2-day onsite installation support and 1-year warranty.',
                    ]
                );
            }
        }

        // ============================================
        // Contracts (5) — terms is structured JSON; payment_schedule keys
        // align with Payment.milestone strings via substring match.
        // ============================================
        $contractSeed = [
            // [title, status, supplier_idx, total, start_offset, end_offset, currency_currency_label]
            ['Copper Wire 16mm – Electrical Components',  ContractStatus::ACTIVE,    0, 95000,  -30, 15],
            ['Construction Materials - Cement & Steel',   ContractStatus::ACTIVE,    1, 180000, -35, 25],
            ['IT Hardware – Servers & Networking',        ContractStatus::ACTIVE,    2, 125000, -55, 20],
            ['Office Equipment & Furniture',              ContractStatus::COMPLETED, 3, 68000,  -75, -7],
            ['Packaging Materials - Industrial Supplies', ContractStatus::COMPLETED, 4, 42000,  -90, -45],
        ];

        $contracts = collect();
        foreach ($contractSeed as $i => $d) {
            [$title, $status, $supIdx, $total, $startOffset, $endOffset] = $d;
            $supplier = $suppliers[$supIdx];

            $structuredTerms = $this->buildContractTerms($title, $total);

            $signedAt = now()->addDays($startOffset)->toDateTimeString();

            $contracts->push(Contract::updateOrCreate(
                ['contract_number' => sprintf('CNT-2024-%04d', 156 - $i * 13)],
                [
                    'title'               => $title,
                    'description'         => 'Procurement contract for ' . $title,
                    'purchase_request_id' => $prs[$i % $prs->count()]->id,
                    'buyer_company_id'    => $buyer->id,
                    'status'              => $status->value,
                    'parties'             => [
                        ['company_id' => $buyer->id,    'name' => $buyer->name,    'role' => 'buyer'],
                        ['company_id' => $supplier->id, 'name' => $supplier->name, 'role' => 'supplier'],
                    ],
                    'amounts'             => [
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
                    'signatures'          => [
                        ['company_id' => $buyer->id,    'signed_at' => $signedAt],
                        ['company_id' => $supplier->id, 'signed_at' => $signedAt],
                    ],
                    'terms'               => json_encode($structuredTerms, JSON_UNESCAPED_UNICODE),
                    'start_date'          => now()->addDays($startOffset)->toDateString(),
                    'end_date'            => now()->addDays($endOffset)->toDateString(),
                    'version'             => 1,
                ]
            ));
        }

        // ============================================
        // Payments — milestones contain the schedule key as substring,
        // so ContractController::show milestone matching works.
        // ============================================
        $paymentSeed = [
            // [contract_idx, supplier_idx, status, amount, milestone (must contain advance/production/delivery), days_offset]
            [0, 0, PaymentStatus::COMPLETED,        28500, 'Advance Payment (30%)',         -28],
            [0, 0, PaymentStatus::PENDING_APPROVAL, 47500, 'Production Milestone (50%)',     5],
            [1, 1, PaymentStatus::COMPLETED,        54000, 'Advance Payment',               -33],
            [1, 1, PaymentStatus::PROCESSING,       90000, 'Production Payment',             20],
            [2, 2, PaymentStatus::COMPLETED,        37500, 'Advance Payment',               -53],
            [2, 2, PaymentStatus::FAILED,           62500, 'Production Stage',              -3],
            [3, 3, PaymentStatus::COMPLETED,        20400, 'Advance Payment',               -73],
            [3, 3, PaymentStatus::COMPLETED,        47600, 'Delivery Payment',              -14],
            [4, 4, PaymentStatus::COMPLETED,        12600, 'Advance Payment',               -88],
            [4, 4, PaymentStatus::COMPLETED,        29400, 'Delivery Payment',              -50],
        ];

        foreach ($paymentSeed as $d) {
            [$contractIdx, $supIdx, $status, $amount, $milestone, $offset] = $d;
            $contract = $contracts[$contractIdx];
            $supplier = $suppliers[$supIdx];

            $payment = Payment::updateOrCreate(
                ['contract_id' => $contract->id, 'milestone' => $milestone],
                [
                    'company_id'           => $buyer->id,
                    'recipient_company_id' => $supplier->id,
                    'buyer_id'             => $ahmed->id,
                    'status'               => $status->value,
                    'amount'               => $amount,
                    'vat_rate'             => 5.00,
                    'vat_amount'           => $amount * 0.05,
                    'total_amount'         => $amount * 1.05,
                    'currency'             => 'AED',
                    'milestone'            => $milestone,
                    'approved_at'          => $status === PaymentStatus::COMPLETED ? now()->addDays($offset) : null,
                ]
            );
            $payment->created_at = now()->addDays($offset);
            $payment->saveQuietly();
        }

        // ============================================
        // Shipments (5) — with multi-event tracking history that maps to
        // ShipmentController phases (preparing/in_transit/at_customs/...).
        // ============================================
        $shipmentSeed = [
            // [contract_idx, status, origin city, destination city, eta_days_offset]
            [0, ShipmentStatus::IN_TRANSIT,       'Sharjah Industrial Area', 'Dubai Silicon Oasis', 5],
            [1, ShipmentStatus::IN_CLEARANCE,     'Deira, Dubai',            'Abu Dhabi',           8],
            [2, ShipmentStatus::READY_FOR_PICKUP, 'Dubai Internet City',     'Al Ain',              2],
            [3, ShipmentStatus::DELIVERED,        'Mussafah, Abu Dhabi',     'Ras Al Khaimah',     -3],
            [4, ShipmentStatus::IN_PRODUCTION,    'Healthcare City, Dubai',  'Fujairah',           12],
        ];

        foreach ($shipmentSeed as $i => $d) {
            [$contractIdx, $status, $originCity, $destCity, $etaOffset] = $d;
            $contract = $contracts[$contractIdx];

            $shipment = Shipment::updateOrCreate(
                ['tracking_number' => sprintf('SHP-2024-%04d', 234 - $i)],
                [
                    'contract_id'         => $contract->id,
                    'company_id'          => $buyer->id,
                    'logistics_company_id'=> $logistics->id,
                    'status'              => $status->value,
                    'origin'              => ['city' => $originCity, 'country' => 'UAE', 'address' => $originCity],
                    'destination'         => ['city' => $destCity,   'country' => 'UAE', 'address' => $destCity],
                    'current_location'    => ['city' => $originCity, 'country' => 'UAE'],
                    'estimated_delivery'  => now()->addDays($etaOffset),
                    'actual_delivery'     => $status === ShipmentStatus::DELIVERED ? now()->addDays($etaOffset) : null,
                    'notes'               => 'Carrier: FastLine Logistics. Driver assigned. Real-time GPS enabled.',
                ]
            );

            // Wipe any old tracking events to keep this seeder idempotent.
            $shipment->trackingEvents()->delete();

            $this->seedTrackingEvents($shipment, $status, $originCity, $destCity);
        }

        // ============================================
        // Disputes (3)
        // ============================================
        $disputeSeed = [
            [1, 1, DisputeType::QUALITY,         DisputeStatus::OPEN,         'Non-compliant product specifications', 'Received copper wire does not meet IEC 60228 standards as specified in the contract.'],
            [2, 2, DisputeType::DELIVERY,        DisputeStatus::UNDER_REVIEW, 'Shipment delayed beyond agreed timeline', 'Delivery was scheduled for March 28 but goods arrived on April 5 — beyond the contractual window.'],
            [4, 4, DisputeType::PAYMENT,         DisputeStatus::RESOLVED,     'Milestone payment verification issue',   'Supplier claimed milestone completed; buyer requested third-party inspection and the issue was resolved in supplier favor.'],
        ];

        foreach ($disputeSeed as $i => $d) {
            [$contractIdx, $supIdx, $type, $status, $title, $desc] = $d;

            Dispute::updateOrCreate(
                ['title' => $title, 'contract_id' => $contracts[$contractIdx]->id],
                [
                    'company_id'         => $buyer->id,
                    'raised_by'          => $ahmed->id,
                    'against_company_id' => $suppliers[$supIdx]->id,
                    'assigned_to'        => $status === DisputeStatus::UNDER_REVIEW ? $gov->id : null,
                    'type'               => $type->value,
                    'status'             => $status->value,
                    'description'        => $desc,
                    'sla_due_date'       => now()->addDays(7 - $i),
                    'resolution'         => $status === DisputeStatus::RESOLVED
                        ? 'Buyer confirmed milestone completion after third-party inspection. No further action required.'
                        : null,
                    'resolved_at'        => $status === DisputeStatus::RESOLVED ? now()->subDays(5) : null,
                ]
            );
        }

        $this->command->info(sprintf(
            'RichDemoSeeder: %d PRs, %d RFQs, %d Bids, %d Contracts, %d Payments, %d Shipments, %d Disputes seeded.',
            PurchaseRequest::count(), Rfq::count(), Bid::count(), Contract::count(),
            Payment::count(), Shipment::count(), Dispute::count()
        ));
    }

    /**
     * Build a structured terms array (sections of bullet items) for a contract,
     * lightly tailored by title. Stored as JSON in contracts.terms.
     *
     * @return array<int, array{title:string, items: array<int,string>}>
     */
    private function buildContractTerms(string $title, float $total): array
    {
        $isCopper = str_contains(strtolower($title), 'copper');
        $isCement = str_contains(strtolower($title), 'cement') || str_contains(strtolower($title), 'construction');
        $isIT     = str_contains(strtolower($title), 'it ') || str_contains(strtolower($title), 'server');

        $productSpecs = match (true) {
            $isCopper => [
                'Copper wire 16mm gauge, IEC 60228 compliant',
                'Quantity: 10 metric tons',
                'Purity: 99.9% copper',
                'Packaging: Industrial spools, 500m each',
            ],
            $isCement => [
                'Portland Cement Grade 42.5N, sulfate-resistant',
                'Steel Rebar B500B, ribbed surface',
                'Mill test certificates required for steel',
                'Bagged or bulk delivery accepted',
            ],
            $isIT => [
                'Enterprise rack servers (dual Xeon Gold)',
                '256GB ECC RAM, 4x 2TB NVMe SSD per unit',
                '3-year on-site warranty',
                'Pre-installed and pre-configured',
            ],
            default => [
                'Goods as per RFQ specification',
                'Quantity and unit price as per accepted bid',
                'Original manufacturer packaging',
            ],
        };

        return [
            [
                'title' => 'Product Specifications',
                'items' => $productSpecs,
            ],
            [
                'title' => 'Delivery Terms',
                'items' => [
                    'Delivery Location: Dubai Silicon Oasis, UAE',
                    'Delivery within agreed timeline (per shipment record)',
                    'Incoterms: DAP (Delivered at Place)',
                ],
            ],
            [
                'title' => 'Quality Assurance',
                'items' => [
                    'Quality certificates required before shipment',
                    'Inspection rights reserved for buyer',
                    'Warranty period: 1–3 years from delivery date',
                ],
            ],
            [
                'title' => 'Payment Terms',
                'items' => [
                    '30% advance payment upon contract signing',
                    '50% upon production completion verification',
                    '20% upon successful delivery and inspection',
                    'Total contract value: AED ' . number_format($total),
                ],
            ],
            [
                'title' => 'Dispute Resolution',
                'items' => [
                    'Governed by UAE Commercial Law',
                    'Disputes handled through TriLink platform mediation',
                    'Arbitration in Dubai, UAE if mediation fails',
                ],
            ],
        ];
    }

    /**
     * Seed a realistic tracking history for a shipment. Each event uses a
     * ShipmentStatus enum value as `status` so ShipmentController::buildTimeline
     * (which keys events by mapShipmentStatus) finds them.
     */
    private function seedTrackingEvents(Shipment $shipment, ShipmentStatus $finalStatus, string $originCity, string $destCity): void
    {
        // Linear progression of phases up to (and including) the final status.
        $progression = [
            ShipmentStatus::IN_PRODUCTION->value    => ['Goods being prepared at supplier facility',  $originCity,                  -10],
            ShipmentStatus::READY_FOR_PICKUP->value => ['Goods ready, logistics provider notified',   $originCity,                  -7],
            ShipmentStatus::IN_TRANSIT->value       => ['Shipment picked up and en route',            'Highway en route',           -3],
            ShipmentStatus::IN_CLEARANCE->value     => ['Customs clearance in progress',              $destCity . ' Customs Port',  -1],
            ShipmentStatus::DELIVERED->value        => ['Shipment delivered successfully',            $destCity,                    0],
        ];

        $stopAt = $finalStatus->value;
        $emit   = true;

        foreach ($progression as $statusValue => [$desc, $loc, $offset]) {
            if (!$emit) {
                break;
            }

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'status'      => $statusValue,
                'description' => $desc,
                'location'    => ['city' => $loc, 'country' => 'UAE', 'address' => $loc],
                'event_at'    => now()->addDays($offset),
            ]);

            if ($statusValue === $stopAt) {
                $emit = false;
            }
        }
    }
}
