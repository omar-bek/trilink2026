import { Page, expect } from '@playwright/test';

/**
 * Sign in via the public /login form. Uses the seeded password "password"
 * for every test user. Asserts the post-login page renders the dashboard
 * shell so callers can rely on a logged-in state on return.
 */
export async function login(page: Page, email: string, password = 'password'): Promise<void> {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    // `php artisan serve` is single-threaded — using 'networkidle' deadlocks
    // because the asset chain blocks the next request. 'domcontentloaded'
    // is enough to know the post-login HTML has rendered.
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.click('button[type="submit"]'),
    ]);
    // Either we landed on /dashboard or on a redirect inside the dashboard
    // tree (e.g. registration-success for pending companies). Anything that
    // is NOT /login is acceptable; tests then assert role-specific content.
    await expect(page).not.toHaveURL(/\/login$/);
}

/** Test user catalog seeded by ComprehensiveSeeder. */
export const USERS = {
    admin:           'admin@trilink.test',
    government:      'gov@trilink.test',
    companyManager:  'manager@al-ahram.test',
    buyer:           'buyer@al-ahram.test',
    branchManager:   'branch.dubai@al-ahram.test',
    finance:         'finance@al-ahram.test',
    financeManager:  'finance.mgr@al-ahram.test',
    sales:           'sales@al-ahram.test',
    salesManager:    'sales.mgr@al-ahram.test',
    supplier:        'mohammed@emirates-ind.test',
    logistics:       'driver@fastline.test',
    clearance:       'agent@cargocheck.test',
    serviceProvider: 'engineer@buildtech.test',
} as const;
