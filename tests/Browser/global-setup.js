import { execFileSync } from 'node:child_process';
import { browserEnvironment } from './test-environment.js';

export default async function globalSetup() {
    if (browserEnvironment.APP_ENV !== 'testing' || !browserEnvironment.DB_DATABASE.endsWith('_testing')) {
        throw new Error('Refusing to prepare browser fixtures outside an isolated testing database.');
    }

    const options = {
        cwd: process.cwd(),
        env: browserEnvironment,
        stdio: 'inherit',
    };

    execFileSync('php', ['artisan', 'migrate:fresh', '--force'], options);
    execFileSync('php', ['artisan', 'db:seed', '--class=BrowserSecuritySeeder', '--force'], options);
}
