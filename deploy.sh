#!/bin/bash
# ============================================================
# Production Deployment Script — UH Lodging Management System
# Run this after deploying code changes to production.
# ============================================================

set -e

echo "============================================"
echo "  UH Lodging System — Production Deploy"
echo "============================================"

# 1. Install PHP dependencies (production-only, optimized autoloader)
echo ""
echo "[1/9] Installing composer dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction

# 2. Run database migrations
echo ""
echo "[2/9] Running database migrations..."
php artisan migrate --force

# 3. Cache configuration (merges all config files into one cached file)
echo ""
echo "[3/9] Caching configuration..."
php artisan config:cache

# 4. Cache routes (compiles all routes into a single file)
echo ""
echo "[4/9] Caching routes..."
php artisan route:cache

# 5. Cache views (pre-compiles all Blade templates)
echo ""
echo "[5/9] Caching views..."
php artisan view:cache

# 6. Cache events (maps events to listeners)
echo ""
echo "[6/9] Caching events..."
php artisan event:cache

# 7. Cache Filament icons (avoids runtime icon discovery)
echo ""
echo "[7/9] Caching Filament icons..."
php artisan icons:cache

# 8. Create storage link if it doesn't exist
echo ""
echo "[8/9] Creating storage symlink..."
php artisan storage:link 2>/dev/null || true

# 9. Build frontend assets for production
echo ""
echo "[9/9] Building frontend assets..."
npm ci --production=false
npm run build

echo ""
echo "============================================"
echo "  Deployment complete!"
echo "============================================"
echo ""
echo "Post-deploy checklist:"
echo "  - Verify .env has APP_ENV=production"
echo "  - Verify .env has APP_DEBUG=false"
echo "  - Verify queue worker is running: php artisan queue:work --tries=3"
echo "  - Verify scheduler is configured in cron"
echo ""
