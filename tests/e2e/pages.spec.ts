import { test, expect } from '@playwright/test';
import { USERS, login } from './helpers/login';

/**
 * Walks the major dashboard sections for buyer / supplier / admin roles.
 *
 * `php artisan serve` is single-threaded, so a real browser navigation
 * (which fires off CSS/JS/XHR requests in parallel) creates a self-DDoS:
 * the second request gets ERR_ABORTED while the first is still being
 * served. To avoid that, we use `page.request.get()` for the page-walk
 * which only fetches the HTML and never spawns parallel requests.
 *
 * Real bugs caught here so far:
 *   - /dashboard/esg returned 500 because the phase8 ESG migration was
 *     pending. Resolved by running migrations.
 *   - sales dashboard 500 because of an undefined `dashboard.rfqs.create`
 *     route name. Fixed in salesPayload().
 */

const BUYER_PAGES = [
    '/dashboard/purchase-requests',
    '/dashboard/rfqs',
    '/dashboard/bids',
    '/dashboard/contracts',
    '/dashboard/payments',
    '/dashboard/shipments',
    '/dashboard/disputes',
    '/notifications',
    '/dashboard/documents',
    '/dashboard/branches',
    '/dashboard/company',
    '/dashboard/profile',
    '/dashboard/cart',
    '/dashboard/catalog',
    '/dashboard/products',
    '/dashboard/suppliers',
    '/dashboard/performance',
    '/dashboard/analytics/spend',
    '/dashboard/insurances',
    '/dashboard/escrow',
    '/dashboard/integrations',
    '/dashboard/api-tokens',
    '/dashboard/beneficial-owners',
    '/dashboard/esg',
    '/dashboard/search',
    '/dashboard/ai/copilot',
    '/dashboard/ai/ocr',
    '/dashboard/ai/predictions/price',
    '/settings',
];

const SUPPLIER_PAGES = [
    '/dashboard/rfqs',
    '/dashboard/bids',
    '/dashboard/contracts',
    '/dashboard/shipments',
    '/dashboard/payments',
    '/dashboard/disputes',
    '/notifications',
    '/dashboard/documents',
    '/dashboard/profile',
    '/dashboard/products',
    '/dashboard/performance',
    '/dashboard/esg',
    '/settings',
];

const ADMIN_PAGES = [
    '/dashboard/admin',
    '/dashboard/admin/users',
    '/dashboard/admin/companies',
    '/dashboard/admin/categories',
    '/dashboard/admin/tax-rates',
    '/dashboard/admin/audit',
    '/dashboard/admin/verification',
    '/dashboard/admin/settings',
];

test.describe('Buyer can browse all main pages', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, USERS.buyer);
    });

    for (const path of BUYER_PAGES) {
        test(`GET ${path}`, async ({ page }) => {
            const response = await page.request.get(path);
            expect(response.status(), `${path} status`).toBeLessThan(500);
        });
    }
});

test.describe('Supplier can browse main pages', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, USERS.supplier);
    });

    for (const path of SUPPLIER_PAGES) {
        test(`GET ${path}`, async ({ page }) => {
            const response = await page.request.get(path);
            expect(response.status(), `${path} status`).toBeLessThan(500);
        });
    }
});

test.describe('Admin can browse admin console', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, USERS.admin);
    });

    for (const path of ADMIN_PAGES) {
        test(`GET ${path}`, async ({ page }) => {
            const response = await page.request.get(path);
            expect(response.status(), `${path} status`).toBeLessThan(500);
        });
    }
});
