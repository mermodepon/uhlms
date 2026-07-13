import { expect, test } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

async function monitorSecurityFailures(page) {
    const runtimeErrors = [];
    let documentPolicy = '';

    page.on('pageerror', (error) => runtimeErrors.push(`pageerror: ${error.message}`));
    page.on('response', (response) => {
        if (response.request().resourceType() === 'document') {
            documentPolicy = response.headers()['content-security-policy'] || '';
        }
    });
    page.on('console', (message) => {
        if (message.type() === 'error') {
            runtimeErrors.push(`console: ${message.text()}`);
        }
    });

    await page.addInitScript(() => {
        window.__securityPolicyViolations = [];
        document.addEventListener('securitypolicyviolation', (event) => {
            window.__securityPolicyViolations.push({
                blockedURI: event.blockedURI,
                directive: event.effectiveDirective,
                lineNumber: event.lineNumber,
                sample: event.sample,
                sourceFile: event.sourceFile,
            });
        });
    });

    return async () => {
        const violations = await page.evaluate(() => window.__securityPolicyViolations || []);
        const missingNonces = await page.locator('script:not([src]):not([nonce])').evaluateAll((scripts) => scripts.map((script) => ({
            preview: script.textContent.trim().slice(0, 160),
            type: script.type,
        })));
        const expectedNonce = documentPolicy.match(/'nonce-([^']+)'/)?.[1] || '';
        const mismatchedNonces = await page.locator('script:not([src])[nonce]').evaluateAll(
            (scripts, nonce) => scripts
                .filter((script) => script.nonce !== nonce)
                .map((script) => ({ nonce: script.nonce, preview: script.textContent.trim().slice(0, 160) })),
            expectedNonce,
        );

        expect({ violations, missingNonces, mismatchedNonces }).toEqual({
            violations: [],
            missingNonces: [],
            mismatchedNonces: [],
        });
        expect(runtimeErrors).toEqual([]);
    };
}

async function expectEnforcingHeaders(response) {
    const headers = response.headers();

    expect(headers['content-security-policy']).toContain("frame-ancestors 'none'");
    expect(headers['content-security-policy-report-only']).toBeUndefined();
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(headers['permissions-policy']).toContain('camera=()');
    expect(headers['x-powered-by']).toBeUndefined();
}

test('public booking, guest, and virtual-tour pages work under enforcing CSP', async ({ page }) => {
    const assertNoSecurityFailures = await monitorSecurityFailures(page);

    const homeResponse = await page.goto('/');
    expect(homeResponse).not.toBeNull();
    await expectEnforcingHeaders(homeResponse);
    await expect(page.locator('body')).toContainText('University Homestay');

    await page.goto('/rooms');
    await page.locator('#guests_filter').selectOption('2');
    await page.getByRole('button', { name: 'Update Search' }).click();
    await expect(page).toHaveURL(/guests=2/);

    await page.goto('/reserve');
    await page.locator('#add-room-request').click();
    await expect(page.locator('[data-extra-room-type]')).toHaveCount(1);

    await page.goto('/account/login');
    await expect(page.locator('input[type="email"]')).toBeVisible();

    await page.goto('/tour');
    await expect(page.locator('#fullscreen-btn')).toBeVisible();
    await expect(page.locator('#gyro-btn')).toBeVisible();

    await assertNoSecurityFailures();
});

test('Filament login, dashboard, reports, and Livewire navigation work under enforcing CSP', async ({ page }) => {
    const assertNoSecurityFailures = await monitorSecurityFailures(page);

    const loginResponse = await page.goto('/admin/login');
    expect(loginResponse).not.toBeNull();
    await expectEnforcingHeaders(loginResponse);

    await page.locator('input[type="email"]').fill('browser-admin@example.test');
    await page.locator('input[type="password"]').fill('browser-password');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/reports');
    await expect(page.locator('body')).toContainText('Monthly Report');

    await page.goto('/admin/rooms');
    const tableSearch = page.getByPlaceholder('Search').first();
    await expect(tableSearch).toBeVisible();
    await tableSearch.fill('101');
    await expect(tableSearch).toHaveValue('101');

    await assertNoSecurityFailures();
});
