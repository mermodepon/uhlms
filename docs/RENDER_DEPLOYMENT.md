# Render Deployment Guide

This project is now prepared for Render using Docker.

## What This Repo Now Includes

- `Dockerfile` for Laravel + Nginx + PHP-FPM
- `render.yaml` for:
  - one web service
  - one queue worker
  - one scheduler cron job
- `.env.render.example` with the production variable set expected on Render

## Important Render/MySQL Note

Render does support MySQL, but not as a first-party managed database in the same way it supports Render Postgres.

For MySQL, Render's official approach is to deploy a **private MySQL service** backed by a persistent disk:

- Official docs: https://render.com/docs/deploy-mysql
- Official template: https://render.com/templates/mysql

## Important Data/Media Note

Your local database, uploaded room images, and uploaded virtual-tour panoramas are **not** in GitHub.

That means a Render deploy will not automatically include:

- your current local MySQL/XAMPP data
- files in local `storage/app/public/...` that were never committed

If you need the live Render app to show the same tours and room images, you must either:

- upload the media again in the deployed admin panel, or
- manually migrate/copy the files into the deployed app's storage setup

For the fastest first online test, keep:

```env
PUBLIC_MEDIA_DISK=public
```

and re-upload any important media after deployment.

## Step-by-Step Setup

### 1. Create the MySQL service

In Render:

1. Open the official MySQL template:
   - https://render.com/templates/mysql
2. Deploy it into the **same Render workspace** and **same region** as your app.
3. Use MySQL 8.
4. Configure:
   - `MYSQL_DATABASE=uhlms`
   - `MYSQL_USER=uhlms`
   - `MYSQL_PASSWORD=<strong password>`
   - `MYSQL_ROOT_PASSWORD=<strong password>`
5. Attach a persistent disk:
   - mount path: `/var/lib/mysql`
   - size: at least `10 GB`

When it finishes, note the internal hostname. It will look like:

```text
mysql-xxxx:3306
```

### 2. Deploy this repo as a Blueprint

In Render:

1. Choose `New` -> `Blueprint`
2. Select your GitHub repo: `mermodepon/uhlms`
3. Render should detect [render.yaml](/d:/xampp/htdocs/MIS/uhlms/render.yaml:1)
4. Review the services:
   - `uhlms-web`
   - `uhlms-queue`
   - `uhlms-scheduler`

### 3. Fill the required environment variables

Use [.env.render.example](/d:/xampp/htdocs/MIS/uhlms/.env.render.example:1) as your reference.

Set these values for all three Render services:

- `APP_KEY`
  - generate locally with:
    ```bash
    php artisan key:generate --show
    ```
- `APP_URL`
  - your Render web URL, for example:
    ```text
    https://uhlms-web.onrender.com
    ```
- `ASSET_URL`
  - same as `APP_URL`
- `DB_HOST`
  - the MySQL private service hostname, for example:
    ```text
    mysql-xxxx
    ```
- `DB_DATABASE=uhlms`
- `DB_USERNAME=uhlms`
- `DB_PASSWORD=<your mysql password>`
- `PAYMONGO_PUBLIC_KEY`
- `PAYMONGO_SECRET_KEY`
- `PAYMONGO_WEBHOOK_SECRET`

Already set in the Blueprint:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=mysql`
- `DB_PORT=3306`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `FILESYSTEM_DISK=local`
- `PUBLIC_MEDIA_DISK=public`

### 4. Deploy

Deploy the Blueprint.

The web service is configured to run this pre-deploy command:

```bash
php artisan migrate --force && php artisan optimize
```

So your database tables should be created automatically before the app starts.

### 5. Verify the web service

After deploy:

1. Open the Render web URL
2. Confirm `/up` returns healthy
3. Check:
   - homepage
   - room catalog
   - room detail
   - virtual tour page
   - `/admin`

### 6. Verify the worker and scheduler

Check Render logs:

- `uhlms-queue`
  - should be running `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600`
- `uhlms-scheduler`
  - should run `php artisan schedule:run` every minute

Your scheduled commands come from [app/Console/Kernel.php](/d:/xampp/htdocs/MIS/uhlms/app/Console/Kernel.php:24).

### 7. Re-upload media if needed

Because local uploads are not in GitHub, log in to the admin panel and re-upload:

- room type images
- virtual tour panoramas
- virtual tour thumbnails
- hotspot media

### 8. Configure PayMongo webhook

After the web service is live, register this webhook URL in PayMongo:

```text
https://your-render-domain/api/webhooks/paymongo
```

Then update:

- `PAYMONGO_WEBHOOK_SECRET`

## Suggested First Test Flow

1. Load the homepage
2. Open the room catalog
3. Open one room detail page
4. Open the virtual tour
5. Log in to `/admin`
6. Watch queue and scheduler logs
7. Trigger a reservation/payment flow

## Sources

- Render PHP/Laravel with Docker:
  https://render.com/docs/deploy-php-laravel-docker
- Render MySQL:
  https://render.com/docs/deploy-mysql
- Render Blueprint spec:
  https://render.com/docs/blueprint-spec
- Render deploy behavior:
  https://render.com/docs/deploys
