#!/bin/bash
# ============================================================
# Production Deployment Script — UH Lodging Management System
# Run this after deploying code changes to production.
# ============================================================

set -e

echo "============================================"
echo "  UH Lodging System — Production Deploy"
echo "============================================"

# 1. Audit locked PHP dependencies before deployment
echo ""
echo "[1/10] Auditing composer dependencies..."
composer audit --locked --abandoned=report

# 2. Install PHP dependencies (production-only, optimized autoloader)
echo ""
echo "[2/10] Installing composer dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction

# 3. Run database migrations
echo ""
echo "[3/10] Running database migrations..."
php artisan migrate --force

# 4. Cache configuration (merges all config files into one cached file)
echo ""
echo "[4/10] Caching configuration..."
php artisan config:cache

# 5. Cache routes (compiles all routes into a single file)
echo ""
echo "[5/10] Caching routes..."
php artisan route:cache

# 6. Cache views (pre-compiles all Blade templates)
echo ""
echo "[6/10] Caching views..."
php artisan view:cache

# 7. Cache events (maps events to listeners)
echo ""
echo "[7/10] Caching events..."
php artisan event:cache

# 8. Cache Filament icons (avoids runtime icon discovery)
echo ""
echo "[8/10] Caching Filament icons..."
php artisan icons:cache

# 9. Create storage link if it doesn't exist
echo ""
echo "[9/10] Creating storage symlink..."
php artisan storage:link 2>/dev/null || true

# 10. Build frontend assets for production
echo ""
echo "[10/10] Building frontend assets..."
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
