# Testing

The automated suite uses the isolated MySQL database `uhlms_testing`. It must never point at the production `uhlms` database.

## One-time setup

1. Create the database:

   ```powershell
   D:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS uhlms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```

2. Copy `.env.testing.example` to an untracked `.env.testing` and set local test-only database credentials if they differ from the defaults.

## Commands

```powershell
php artisan test
php artisan migrate:fresh --env=testing
php artisan route:list --except-vendor
php artisan schedule:list
php artisan optimize
php artisan optimize:clear
```

`tests/TestCase.php` aborts the suite unless it is using MySQL database `uhlms_testing`.
Run the suite sequentially: `RefreshDatabase` rebuilds the shared test schema and concurrent test processes would interfere with one another.

## Enforced-CSP browser smoke test

Install Chromium once, then run the isolated browser suite:

```powershell
npx playwright install chromium
npm run test:browser-security
```

The browser setup always runs `migrate:fresh` against a database whose name ends in `_testing`, seeds a test-only staff account, and refuses to run in any environment other than `testing`.

## JavaScript dependency security

Audit the complete production and development dependency tree with:

```powershell
npm run audit:security
```

High or critical advisories cause this command to fail. The ordinary localhost startup workflow does not run the audit, so an unavailable npm registry does not prevent local startup. Production deployment and the opt-in Cloudflare CSP-enforcement preflight perform a clean `npm ci --include=dev` followed by this audit before building frontend assets.

Cloudflare starts in `report-only` mode by default. To promote CSP only after the clean dependency install, dependency audit, full Laravel suite, production frontend build, and Chromium smoke suite pass, run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_cloudflare.ps1 -EnforceCsp
```

Run the same command without `-EnforceCsp` to roll back immediately to `report-only`; this does not require a code or database rollback.
