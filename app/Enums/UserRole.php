<?php

namespace App\Enums;

enum UserRole: string
{
    case BUYER = 'buyer';
    case COMPANY_MANAGER = 'company_manager';
    case SUPPLIER = 'supplier';
    case LOGISTICS = 'logistics';
    case CLEARANCE = 'clearance';
    case SERVICE_PROVIDER = 'service_provider';
    case FINANCE = 'finance';
    case FINANCE_MANAGER = 'finance_manager';
    case SALES = 'sales';
    case SALES_MANAGER = 'sales_manager';
    case GOVERNMENT = 'government';
    case ADMIN = 'admin';

    /**
     * Roles a company manager is allowed to assign to team members.
     * Excludes platform-level roles (admin, government, company_manager
     * itself) which are managed elsewhere.
     *
     * @return array<int, self>
     */
    public static function assignableByCompanyManager(): array
    {
        return [
            self::BUYER,
            self::SUPPLIER,
            self::SALES,
            self::SALES_MANAGER,
            self::FINANCE,
            self::FINANCE_MANAGER,
            self::LOGISTICS,
            self::CLEARANCE,
            self::SERVICE_PROVIDER,
        ];
    }
}
