import { test, expect } from '@playwright/test';
import { USERS, login } from './helpers/login';

test.describe('Authentication & public pages', () => {
    test('home page renders for guests', async ({ page }) => {
        const response = await page.goto('/');
        expect(response?.status()).toBeLessThan(500);
        // Public marketing landing page; just confirm it has the brand link.
        await expect(page.locator('text=TriLink').first()).toBeVisible();
    });

    test('login page renders', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
    });

    test('forgot-password page renders', async ({ page }) => {
        const response = await page.goto('/forgot-password');
        expect(response?.status()).toBeLessThan(500);
    });

    test('register page renders', async ({ page }) => {
        const response = await page.goto('/register');
        expect(response?.status()).toBeLessThan(500);
    });

    test('public supplier directory renders', async ({ page }) => {
        const response = await page.goto('/suppliers');
        expect(response?.status()).toBeLessThan(500);
    });

    test('login with seeded admin works', async ({ page }) => {
        await login(page, USERS.admin);
        await expect(page).not.toHaveURL(/\/login/);
    });

    test('logout returns to login', async ({ page }) => {
        await login(page, USERS.admin);
        // POST /logout via the topbar form. Some layouts hide it inside a
        // dropdown — issue the request directly to keep the test resilient.
        const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        const response = await page.request.post('/logout', {
            headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {},
        });
        expect(response.status()).toBeLessThan(400);
    });
});
