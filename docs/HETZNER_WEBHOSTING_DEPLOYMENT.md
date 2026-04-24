# Hetzner Web Hosting Deployment Guide

This project can be deployed to Hetzner Web Hosting for online demos, staging, UAT, and light real-world use if it is prepared for shared-hosting constraints.

## Recommended Use Case

Hetzner Web Hosting is a good fit for this repo when you want:

- a public online test environment
- the guest pages and admin panel available over HTTPS
- MySQL-backed reservations and basic operations
- a simpler and cheaper deployment target than a VPS

It is a less ideal fit when you need fully production-like background processing for queued jobs and always-on automation.

## Why This App Needs Some Preparation

This repo is not just a basic Laravel brochure site. It includes:

- Laravel 11 and Filament admin
- Vite-built frontend assets
- database-backed sessions and cache
- public uploads for room and virtual-tour media
- scheduled commands from [app/Console/Kernel.php](/d:/xampp/htdocs/MIS/uhlms/app/Console/Kernel.php:24)
- PayMongo webhook handling in [app/Http/Controllers/PaymentWebhookController.php](/d:/xampp/htdocs/MIS/uhlms/app/Http/Controllers/PaymentWebhookController.php:14)

For Hetzner Web Hosting, the safest default is to simplify queue behavior and rely on cron for scheduled tasks.

## Recommended Hetzner Profile

Use [.env.hetzner.example](/d:/xampp/htdocs/MIS/uhlms/.env.hetzner.example:1) as the starting template.

Key choices in this profile:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=mysql`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `PUBLIC_MEDIA_DISK=public`
- `QUEUE_CONNECTION=sync`

### Why `QUEUE_CONNECTION=sync`

This app normally uses the database queue, but on shared hosting there may be no reliable long-running queue worker.

Using `sync` makes webhook and other queued work run immediately during the request instead of waiting for a worker.

This is usually the more practical choice for Hetzner Web Hosting, especially for:

- PayMongo webhook processing
- staging or demo environments
- lower-volume deployments

If you later confirm reliable queue execution on your hosting plan, you can switch back to `QUEUE_CONNECTION=database`.

## Expected Hosting Setup

Before deploying, confirm the Hetzner Web Hosting plan supports the features you need.

Recommended minimum target:

- PHP 8.2 or newer
- MySQL database
- HTTPS
- cron jobs
- SSH access preferred
- ability to set the document root to `public/`

Higher Hetzner Web Hosting plans may also include Node.js and Redis, but this guide assumes the most conservative shared-hosting workflow.

## Deployment Strategy

The safest deployment flow for this repo on shared hosting is:

1. Build frontend assets locally
2. Upload the application files
3. Configure `.env`
4. Run migrations and production caches
5. Set up storage access
6. Configure cron
7. Test guest, admin, media, and payment flows

## Build Assets Locally

Because this project uses Vite, build assets before upload unless you have confirmed Node.js is available and convenient on-host.

Run locally:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

This should generate the production assets under `public/build`.

## Upload the App

Upload the project to your hosting account, excluding folders that do not need to be deployed from local development where appropriate, such as:

- `.git/`
- `node_modules/`
- `tests/`

Keep these important folders and files:

- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `storage/`
- `vendor/` if you are not running Composer on-host
- `composer.json`
- `composer.lock`
- `artisan`

## Document Root

Set the web root to:

```text
public/
```

This is the standard Laravel requirement.

If the hosting panel does not let you point directly to `public/`, stop and confirm the fallback deployment structure before going live.

## Environment Variables

Use [.env.hetzner.example](/d:/xampp/htdocs/MIS/uhlms/.env.hetzner.example:1) as the reference.

Minimum production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
SESSION_DRIVER=database
CACHE_STORE=database
PUBLIC_MEDIA_DISK=public
QUEUE_CONNECTION=sync
```

Add your real values for:

- `APP_KEY`
- MySQL credentials
- SMTP mail credentials
- PayMongo keys
- `PAYMONGO_WEBHOOK_SECRET`

Generate an application key if needed:

```bash
php artisan key:generate --show
```

## Database Setup

Create or import the MySQL database, then run:

```bash
php artisan migrate --force
```

This app expects database-backed support tables, including sessions and cache-related tables, based on the configured drivers.

## Production Optimization

After the `.env` file is correct, run:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache
```

Or use:

```bash
php artisan optimize
```

if your hosting environment supports the same behavior you want in production.

## Media and Storage

This app uses public uploads for guest-facing media, including virtual-tour panoramas and related files.

Relevant references:

- [config/filesystems.php](/d:/xampp/htdocs/MIS/uhlms/config/filesystems.php:71)
- [docs/VIRTUAL_TOUR_SETUP.md](/d:/xampp/htdocs/MIS/uhlms/docs/VIRTUAL_TOUR_SETUP.md:69)

Try creating the storage symlink:

```bash
php artisan storage:link
```

Then verify that files under `storage/app/public/...` are reachable from the browser via:

```text
/storage/...
```

If symlinks are restricted on the hosting plan, media access needs a hosting-specific workaround before relying on uploads in production.

## Cron / Scheduler

This app has scheduled tasks configured in [app/Console/Kernel.php](/d:/xampp/htdocs/MIS/uhlms/app/Console/Kernel.php:24):

- `reservation:remind-near-due` hourly
- `room-holds:release-expired` every 15 minutes
- `reservations:expire-unpaid` daily at `02:00`

Configure cron to run Laravel's scheduler regularly:

```bash
php artisan schedule:run
```

If Hetzner allows one-minute cron intervals, use that. Otherwise, use the shortest available interval and accept that some automation may run less precisely.

## Payments / Webhooks

Your online payment flow is partly synchronous and partly background-oriented:

- payment initiation happens in [app/Http/Controllers/GuestPaymentController.php](/d:/xampp/htdocs/MIS/uhlms/app/Http/Controllers/GuestPaymentController.php:66)
- webhook handling happens in [app/Http/Controllers/PaymentWebhookController.php](/d:/xampp/htdocs/MIS/uhlms/app/Http/Controllers/PaymentWebhookController.php:14)

For Hetzner Web Hosting, this guide recommends:

- `QUEUE_CONNECTION=sync`
- HTTPS enabled
- public PayMongo webhook URL configured

Webhook URL format:

```text
https://your-domain.example/api/webhooks/paymongo
```

After registering the webhook in PayMongo, update:

- `PAYMONGO_WEBHOOK_SECRET`

## Suggested Verification Checklist

After deployment, confirm:

- homepage loads
- room catalog loads
- room detail pages load images
- virtual tour page loads
- uploaded media is publicly accessible
- `/admin` loads and login works
- reservation form submits successfully
- cron is active
- payment page loads when online payments are enabled
- PayMongo webhook reaches the app and updates data correctly

## Practical Assessment

For this repo, Hetzner Web Hosting is:

- good for demos, staging, UAT, and faculty/client review
- workable for lower-volume real use with careful setup
- less ideal than a VPS or cloud server for queue-heavy and automation-heavy production use

The main compromises are:

- background processing is less flexible
- scheduler timing depends on shared-hosting cron
- media/storage behavior must be verified on the real hosting plan

## Recommended Next Test

Before treating the deployment as ready, test these in order:

1. static and guest pages
2. Filament admin login
3. media upload and retrieval
4. reservation submission
5. cron-driven automation
6. full PayMongo flow
