<?php

namespace Database\Seeders;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $buyerCompany = Company::updateOrCreate(
            ['registration_number' => 'BUY-1001'],
            [
                'name' => 'Blue Ocean Trading',
                'type' => CompanyType::BUYER->value,
                'status' => CompanyStatus::ACTIVE->value,
                'email' => 'info@blue-ocean.test',
                'phone' => '+971500000001',
                'city' => 'Dubai',
                'country' => 'UAE',
            ]
        );

        $supplierCompany = Company::updateOrCreate(
            ['registration_number' => 'SUP-2001'],
            [
                'name' => 'Desert Supplies LLC',
                'type' => CompanyType::SUPPLIER->value,
                'status' => CompanyStatus::ACTIVE->value,
                'email' => 'sales@desert-supplies.test',
                'phone' => '+971500000002',
                'city' => 'Sharjah',
                'country' => 'UAE',
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN->value,
                'status' => UserStatus::ACTIVE->value,
                'company_id' => null,
            ]
        );
        $admin->assignRole(UserRole::ADMIN->value);

        $buyer = User::updateOrCreate(
            ['email' => 'buyer@example.com'],
            [
                'first_name' => 'Ahmed',
                'last_name' => 'Buyer',
                'password' => Hash::make('password'),
                'role' => UserRole::BUYER->value,
                'status' => UserStatus::ACTIVE->value,
                'company_id' => $buyerCompany->id,
            ]
        );
        $buyer->assignRole(UserRole::BUYER->value);

        $supplier = User::updateOrCreate(
            ['email' => 'supplier@example.com'],
            [
                'first_name' => 'Sara',
                'last_name' => 'Supplier',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPPLIER->value,
                'status' => UserStatus::ACTIVE->value,
                'company_id' => $supplierCompany->id,
            ]
        );
        $supplier->assignRole(UserRole::SUPPLIER->value);

        $equipmentCategory = Category::updateOrCreate(
            ['name' => 'Industrial Equipment'],
            [
                'description' => 'Machinery and plant equipment',
                'level' => 0,
                'path' => 'industrial-equipment',
                'is_active' => true,
            ]
        );

        $buyerCompany->categories()->syncWithoutDetaching([$equipmentCategory->id]);
        $supplierCompany->categories()->syncWithoutDetaching([$equipmentCategory->id]);

        $purchaseRequest = PurchaseRequest::updateOrCreate(
            ['title' => 'Hydraulic Pumps for Plant A'],
            [
                'description' => 'Need 10 pumps for Plant A maintenance cycle.',
                'company_id' => $buyerCompany->id,
                'buyer_id' => $buyer->id,
                'category_id' => $equipmentCategory->id,
                'status' => PurchaseRequestStatus::APPROVED->value,
                'items' => [
                    ['name' => 'Hydraulic Pump', 'qty' => 10, 'unit' => 'pcs'],
                ],
                'budget' => 125000,
                'currency' => 'AED',
                'delivery_location' => 'Jebel Ali Industrial Zone',
                'required_date' => now()->addDays(14)->toDateString(),
                'approval_history' => [
                    ['by' => 'company_manager', 'status' => 'approved', 'at' => now()->toDateTimeString()],
                ],
                'rfq_generated' => true,
            ]
        );

        $rfq = Rfq::updateOrCreate(
            ['title' => 'RFQ - Hydraulic Pumps'],
            [
                'rfq_number' => 'RFQ-DEMO-1001',
                'description' => 'Quotation request for hydraulic pumps.',
                'company_id' => $buyerCompany->id,
                'purchase_request_id' => $purchaseRequest->id,
                'type' => RfqType::SUPPLIER->value,
                'target_role' => UserRole::SUPPLIER->value,
                'target_company_ids' => [$supplierCompany->id],
                'status' => RfqStatus::OPEN->value,
                'items' => [
                    ['name' => 'Hydraulic Pump', 'qty' => 10, 'spec' => '45L/min'],
                ],
                'budget' => 120000,
                'currency' => 'AED',
                'deadline' => now()->addDays(7),
                'delivery_location' => 'Jebel Ali Industrial Zone',
                'is_anonymous' => false,
                'category_id' => $equipmentCategory->id,
            ]
        );

        Bid::updateOrCreate(
            ['rfq_id' => $rfq->id, 'company_id' => $supplierCompany->id],
            [
                'provider_id' => $supplier->id,
                'status' => BidStatus::SUBMITTED->value,
                'price' => 116500,
                'currency' => 'AED',
                'delivery_time_days' => 12,
                'payment_terms' => '50% advance, 50% on delivery',
                'payment_schedule' => [
                    ['milestone' => 'advance', 'percentage' => 50],
                    ['milestone' => 'delivery', 'percentage' => 50],
                ],
                'items' => [
                    ['name' => 'Hydraulic Pump', 'qty' => 10, 'unit_price' => 11650],
                ],
                'validity_date' => now()->addDays(10),
                'is_anonymous' => false,
                'attachments' => [],
                'ai_score' => ['score' => 88, 'notes' => 'Competitive pricing and lead time'],
                'notes' => 'Includes installation support for 2 days.',
            ]
        );
    }
}
