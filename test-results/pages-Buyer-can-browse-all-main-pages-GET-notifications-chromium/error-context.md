# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: pages.spec.ts >> Buyer can browse all main pages >> GET /notifications
- Location: tests\e2e\pages.spec.ts:85:9

# Error details

```
Test timeout of 30000ms exceeded while running "beforeEach" hook.
```

```
Error: page.fill: Test timeout of 30000ms exceeded.
```

# Test source

```ts
  1  | import { Page, expect } from '@playwright/test';
  2  | 
  3  | /**
  4  |  * Sign in via the public /login form. Uses the seeded password "password"
  5  |  * for every test user. Asserts the post-login page renders the dashboard
  6  |  * shell so callers can rely on a logged-in state on return.
  7  |  */
  8  | export async function login(page: Page, email: string, password = 'password'): Promise<void> {
  9  |     await page.goto('/login', { waitUntil: 'domcontentloaded' });
> 10 |     await page.fill('input[name="email"]', email);
     |                ^ Error: page.fill: Test timeout of 30000ms exceeded.
  11 |     await page.fill('input[name="password"]', password);
  12 |     // `php artisan serve` is single-threaded — using 'networkidle' deadlocks
  13 |     // because the asset chain blocks the next request. 'domcontentloaded'
  14 |     // is enough to know the post-login HTML has rendered.
  15 |     await Promise.all([
  16 |         page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  17 |         page.click('button[type="submit"]'),
  18 |     ]);
  19 |     // Either we landed on /dashboard or on a redirect inside the dashboard
  20 |     // tree (e.g. registration-success for pending companies). Anything that
  21 |     // is NOT /login is acceptable; tests then assert role-specific content.
  22 |     await expect(page).not.toHaveURL(/\/login$/);
  23 | }
  24 | 
  25 | /** Test user catalog seeded by ComprehensiveSeeder. */
  26 | export const USERS = {
  27 |     admin:           'admin@trilink.test',
  28 |     government:      'gov@trilink.test',
  29 |     companyManager:  'manager@al-ahram.test',
  30 |     buyer:           'buyer@al-ahram.test',
  31 |     branchManager:   'branch.dubai@al-ahram.test',
  32 |     finance:         'finance@al-ahram.test',
  33 |     financeManager:  'finance.mgr@al-ahram.test',
  34 |     sales:           'sales@al-ahram.test',
  35 |     salesManager:    'sales.mgr@al-ahram.test',
  36 |     supplier:        'mohammed@emirates-ind.test',
  37 |     logistics:       'driver@fastline.test',
  38 |     clearance:       'agent@cargocheck.test',
  39 |     serviceProvider: 'engineer@buildtech.test',
  40 | } as const;
  41 | 
```