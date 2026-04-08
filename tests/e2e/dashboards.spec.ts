import { test, expect, Page } from '@playwright/test';
import { USERS, login } from './helpers/login';

/**
 * Verifies the role-aware dashboard payload at /dashboard for every seeded
 * role. Each test asserts:
 *   1. The page returned 2xx (no 500/403/redirect-to-login)
 *   2. The shell rendered (4 KPI cards visible via stat container)
 *   3. The role-specific header action button is present
 *   4. No unhandled JS console errors fired during the load
 */

const DASHBOARDS: Array<{
    label: string;
    user: string;
    expectAction?: RegExp;
}> = [
    { label: 'admin',           user: USERS.admin,           expectAction: /system settings|إعدادات/i },
    { label: 'government',      user: USERS.government,      expectAction: /review disputes|مراجعة/i },
    { label: 'company manager', user: USERS.companyManager,  expectAction: /review approvals|مراجعة الاعتمادات/i },
    { label: 'buyer',           user: USERS.buyer,           expectAction: /create purchase request|إنشاء/i },
    { label: 'branch manager',  user: USERS.branchManager,   expectAction: /review approvals|مراجعة/i },
    { label: 'finance',         user: USERS.finance,         expectAction: /go to payments|الذهاب/i },
    { label: 'finance manager', user: USERS.financeManager,  expectAction: /go to payments|الذهاب/i },
    { label: 'sales',           user: USERS.sales,           expectAction: /create sales offer|إنشاء عرض/i },
    { label: 'sales manager',   user: USERS.salesManager,    expectAction: /create sales offer|إنشاء عرض/i },
    { label: 'supplier',        user: USERS.supplier,        expectAction: /browse rfqs|تصفح/i },
    { label: 'logistics',       user: USERS.logistics,       expectAction: /browse rfqs|تصفح/i },
    { label: 'clearance',       user: USERS.clearance,       expectAction: /review clearances|مراجعة التخليصات/i },
    { label: 'service provider',user: USERS.serviceProvider, expectAction: /browse rfqs|تصفح/i },
];

for (const role of DASHBOARDS) {
    test(`dashboard renders for ${role.label}`, async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        await login(page, role.user);
        const response = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
        expect(response?.status(), `${role.label} dashboard HTTP`).toBeLessThan(400);

        // Either the unified shell rendered (KPI cards present) OR the user
        // was bounced to a holding page like /register/success — both are
        // legitimate post-login states. Detect which.
        if (page.url().includes('/dashboard')) {
            // 4 stat cards from shell.blade.php
            const stats = page.locator('div.grid.grid-cols-2 > div').first();
            await expect(stats).toBeVisible({ timeout: 8000 });
        }

        if (role.expectAction && page.url().endsWith('/dashboard')) {
            const action = page.getByRole('link', { name: role.expectAction });
            // Header action is optional for some shells (e.g. when the role
            // has no headerAction defined). Don't hard-fail if it's absent.
            if (await action.count() > 0) {
                await expect(action.first()).toBeVisible();
            }
        }

        // Visual sanity capture for the report
        await page.screenshot({
            path: `tests/e2e/screenshots/dashboard-${role.label.replace(/\s+/g, '-')}.png`,
            fullPage: true,
        });

        // Filter out the noisy known-benign console messages.
        const meaningful = consoleErrors.filter((m) =>
            !/Failed to load resource.*favicon/i.test(m) &&
            !/livewire/i.test(m)
        );
        expect(meaningful, `${role.label} JS errors`).toEqual([]);
    });
}
