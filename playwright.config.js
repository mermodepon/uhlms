import { defineConfig, devices } from '@playwright/test';
import { browserEnvironment, browserTestBaseUrl } from './tests/Browser/test-environment.js';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: 'line',
    globalSetup: './tests/Browser/global-setup.js',
    use: {
        baseURL: browserTestBaseUrl,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php -d expose_php=Off artisan serve --host=127.0.0.1 --port=8010',
        url: browserTestBaseUrl,
        reuseExistingServer: false,
        timeout: 120_000,
        env: browserEnvironment,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
