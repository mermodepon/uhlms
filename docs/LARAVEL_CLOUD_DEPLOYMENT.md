# Laravel Cloud Deployment Guide

This project is ready to deploy to Laravel Cloud for online performance testing.

## Recommended Laravel Cloud Setup

- Region: `Asia Pacific (Singapore)`
- App type: `Web`
- Database: `Managed MySQL`
- Queue worker: `1`
- Scheduler: `Enabled`
- HTTPS: platform domain or custom domain

## Why These Defaults Fit This App

- Laravel 11 + Filament admin panel
- Vite-built frontend assets
- Database-backed sessions, cache, and queue
- Public PayMongo webhook endpoint
- Guest-facing virtual tour and room image uploads

## Important Media Note

Local `public` storage is fine for local/XAMPP development, but guest-facing media should use persistent object storage in Laravel Cloud.

This repo now supports a dedicated media disk via:

```env
PUBLIC_MEDIA_DISK=s3
```

Use `PUBLIC_MEDIA_DISK=public` locally, and switch to `s3` in Laravel Cloud after configuring object storage credentials.

## Environment Variables

Use [.env.cloud.example](../.env.cloud.example) as the starting template.

Minimum production settings:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.laravel.cloud
DB_CONNECTION=mysql
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
PUBLIC_MEDIA_DISK=s3
```

Add your real values for:

- `APP_KEY`
- MySQL credentials
- S3/object storage credentials
- PayMongo keys and webhook secret

## Build and Deploy Commands

Laravel Cloud should run the equivalent of:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache
```

## Scheduler and Queue Expectations

This app has real scheduled commands in [app/Console/Kernel.php](/d:/xampp/htdocs/MIS/uhlms/app/Console/Kernel.php:24):

- `reservation:remind-near-due` hourly
- `room-holds:release-expired` every 15 minutes
- `reservations:expire-unpaid` daily at `02:00`

Run one queue worker for background jobs such as payment webhook processing.

## Post-Deploy Checklist

- `php artisan migrate --force`
- `php artisan optimize`
- Confirm guest homepage loads
- Confirm room catalog and room detail pages load images
- Confirm virtual tour loads panorama images
- Confirm Filament admin login works
- Confirm queue worker is processing jobs
- Confirm scheduler is enabled
- Confirm PayMongo webhook URL is reachable over HTTPS

## Performance Test Checklist

- Homepage first load and repeat load
- Room catalog and room detail response time
- Virtual tour initial load and panorama switching speed
- Filament admin dashboard load time
- Queue backlog stays near zero during simulated reservation/payment activity
