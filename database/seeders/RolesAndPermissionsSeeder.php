<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Spatie permissions are guard-specific. The web app authenticates against
     * the `web` guard (session) while the API uses the `jwt` guard (named
     * `api`). To make permission checks work in both Blade `@can` directives
     * AND the JSON API, we seed permissions and roles under BOTH guards.
     */
    private const GUARDS = ['web', 'api'];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users
            'users.view', 'users.create', 'users.update', 'users.delete',
            // Companies
            'companies.view', 'companies.create', 'companies.update', 'companies.delete', 'companies.manage',
            // Categories
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            // Purchase Requests
            'purchase-requests.view', 'purchase-requests.create', 'purchase-requests.update',
            'purchase-requests.delete', 'purchase-requests.approve',
            // RFQs
            'rfqs.view', 'rfqs.create', 'rfqs.update', 'rfqs.delete',
            // Bids
            'bids.view', 'bids.create', 'bids.update', 'bids.delete',
            'bids.evaluate', 'bids.withdraw',
            // Contracts
            'contracts.view', 'contracts.create', 'contracts.update', 'contracts.delete',
            'contracts.sign', 'contracts.amend',
            // Amendments
            'amendments.create', 'amendments.view', 'amendments.approve', 'amendments.reject',
            // Payments
            'payments.view', 'payments.create', 'payments.update',
            'payments.approve', 'payments.reject', 'payments.process', 'payments.retry',
            'payments.view-schedule', 'payments.manage-schedule',
            // Shipments
            'shipments.view', 'shipments.create', 'shipments.update', 'shipments.track',
            'shipments.view-gps', 'shipments.manage-gps',
            // Disputes
            'disputes.view', 'disputes.create', 'disputes.update',
            'disputes.escalate', 'disputes.resolve', 'disputes.assign', 'disputes.close',
            // Analytics
            'analytics.view', 'analytics.export',
            // Reports
            'reports.view', 'reports.generate', 'data.export',
            // Audit
            'audit.view', 'audit.export',
            // Uploads
            'uploads.view', 'uploads.create', 'uploads.delete',
            // Notifications
            'notifications.view', 'notifications.manage',
            // Settings
            'settings.view', 'settings.update',
            // Dashboard
            'dashboard.view',
        ];

        // Map of role => permission keys it should have. Defined once and
        // applied to every guard so the same role grants the same access in
        // both the Blade-rendered web app and the JSON API.
        $rolePermissionMap = [
            UserRole::ADMIN->value => $permissions, // all permissions
            UserRole::BUYER->value => [
                'purchase-requests.view', 'purchase-requests.create', 'purchase-requests.update', 'purchase-requests.delete',
                'rfqs.view', 'rfqs.create', 'rfqs.update', 'rfqs.delete',
                'bids.view', 'bids.evaluate',
                'contracts.view', 'contracts.create', 'contracts.sign',
                'payments.view', 'payments.create', 'payments.approve', 'payments.reject',
                'payments.view-schedule',
                'shipments.view',
                'disputes.view', 'disputes.create', 'disputes.escalate',
                'uploads.view', 'uploads.create',
                'notifications.view',
                'dashboard.view',
                'categories.view',
            ],
            UserRole::COMPANY_MANAGER->value => [
                'users.view', 'users.create', 'users.update',
                'companies.view', 'companies.update',
                'purchase-requests.view', 'purchase-requests.create', 'purchase-requests.update',
                'purchase-requests.delete', 'purchase-requests.approve',
                'rfqs.view', 'rfqs.create', 'rfqs.update', 'rfqs.delete',
                'bids.view', 'bids.evaluate',
                'contracts.view', 'contracts.create', 'contracts.update', 'contracts.sign', 'contracts.amend',
                'amendments.create', 'amendments.view', 'amendments.approve',
                'payments.view', 'payments.create', 'payments.approve', 'payments.reject', 'payments.process',
                'payments.view-schedule', 'payments.manage-schedule',
                'shipments.view', 'shipments.create', 'shipments.update',
                'disputes.view', 'disputes.create', 'disputes.update', 'disputes.escalate',
                'analytics.view',
                'uploads.view', 'uploads.create', 'uploads.delete',
                'notifications.view', 'notifications.manage',
                'dashboard.view',
                'categories.view',
            ],
            UserRole::SUPPLIER->value => [
                'rfqs.view',
                'bids.view', 'bids.create', 'bids.update', 'bids.delete', 'bids.withdraw',
                'contracts.view', 'contracts.sign',
                'payments.view',
                'shipments.view', 'shipments.create', 'shipments.update',
                'disputes.view', 'disputes.create',
                'uploads.view', 'uploads.create',
                'notifications.view',
                'dashboard.view',
                'categories.view',
            ],
            UserRole::LOGISTICS->value => [
                'rfqs.view',
                'bids.view', 'bids.create', 'bids.update', 'bids.withdraw',
                'contracts.view', 'contracts.sign',
                'payments.view',
                'shipments.view', 'shipments.create', 'shipments.update', 'shipments.track',
                'shipments.view-gps', 'shipments.manage-gps',
                'disputes.view', 'disputes.create',
                'uploads.view', 'uploads.create',
                'notifications.view',
                'dashboard.view',
                'categories.view',
            ],
            UserRole::CLEARANCE->value => [
                'rfqs.view',
                'bids.view', 'bids.create', 'bids.update', 'bids.withdraw',
                'contracts.view', 'contracts.sign',
                'payments.view',
                'shipments.view', 'shipments.update',
                'disputes.view', 'disputes.create',
                'uploads.view', 'uploads.create',
                'notifications.view',
                'dashboard.view',
                'categories.view',
            ],
            UserRole::SERVICE_PROVIDER->value => [
                'rfqs.view',
                'bids.view', 'bids.create', 'bids.update', 'bids.withdraw',
                'contracts.view', 'contracts.sign',
                'payments.view',
                'disputes.view', 'disputes.create',
                'uploads.view', 'uploads.create',
                'notifications.view',
                'dashboard.view',
                'categories.view',
            ],
            UserRole::GOVERNMENT->value => [
                'companies.view', 'companies.manage',
                'contracts.view',
                'payments.view',
                'shipments.view', 'shipments.view-gps',
                'disputes.view', 'disputes.update', 'disputes.resolve', 'disputes.assign', 'disputes.close',
                'analytics.view', 'analytics.export',
                'reports.view', 'reports.generate', 'data.export',
                'audit.view', 'audit.export',
                'notifications.view',
                'dashboard.view',
                'categories.view',
            ],
        ];

        // Seed under every guard the app supports.
        foreach (self::GUARDS as $guard) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, $guard);
            }

            foreach ($rolePermissionMap as $roleKey => $perms) {
                $role = Role::findOrCreate($roleKey, $guard);
                $role->syncPermissions(
                    Permission::whereIn('name', $perms)->where('guard_name', $guard)->get()
                );
            }
        }
    }
}
