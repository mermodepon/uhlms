@echo off
REM ============================================================
REM Production Deployment Script — UH Lodging Management System
REM Run this after deploying code changes to production.
REM ============================================================

echo ============================================
echo   UH Lodging System — Production Deploy
echo ============================================

REM 1. Install PHP dependencies (production-only, optimized autoloader)
echo.
echo [1/9] Installing composer dependencies...
call composer install --optimize-autoloader --no-dev --no-interaction
if %errorlevel% neq 0 goto :error

REM 2. Run database migrations
echo.
echo [2/9] Running database migrations...
call php artisan migrate --force
if %errorlevel% neq 0 goto :error

REM 3. Cache configuration
echo.
echo [3/9] Caching configuration...
call php artisan config:cache
if %errorlevel% neq 0 goto :error

REM 4. Cache routes
echo.
echo [4/9] Caching routes...
call php artisan route:cache
if %errorlevel% neq 0 goto :error

REM 5. Cache views
echo.
echo [5/9] Caching views...
call php artisan view:cache
if %errorlevel% neq 0 goto :error

REM 6. Cache events
echo.
echo [6/9] Caching events...
call php artisan event:cache
if %errorlevel% neq 0 goto :error

REM 7. Cache Filament icons
echo.
echo [7/9] Caching Filament icons...
call php artisan icons:cache
if %errorlevel% neq 0 goto :error

REM 8. Create storage link
echo.
echo [8/9] Creating storage symlink...
call php artisan storage:link 2>nul

REM 9. Build frontend assets
echo.
echo [9/9] Building frontend assets...
call npm ci --production=false
call npm run build
if %errorlevel% neq 0 goto :error

echo.
echo ============================================
echo   Deployment complete!
echo ============================================
echo.
echo Post-deploy checklist:
echo   - Verify .env has APP_ENV=production
echo   - Verify .env has APP_DEBUG=false
echo   - Verify queue worker is running: php artisan queue:work --tries=3
echo   - Verify scheduler is configured
echo.
goto :eof

:error
echo.
echo !! Deployment failed at step above. Check the error and retry.
exit /b 1
