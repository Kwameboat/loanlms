#!/bin/bash
##############################################################################
# Big Cash LMS — Vercel Build Script
# This runs during `vercel build` / `vercel deploy`
# Referenced in vercel.json buildCommand
##############################################################################

set -e

echo "======================================"
echo "  Big Cash LMS — Vercel Build"
echo "======================================"

# ── 1. Install PHP dependencies (production only) ─────────────────────────
echo "[1/6] Installing Composer dependencies..."
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist \
  --quiet

# ── 2. Generate app key if not set ────────────────────────────────────────
if [ -z "$APP_KEY" ]; then
    echo "[2/6] Generating application key..."
    php artisan key:generate --force
else
    echo "[2/6] APP_KEY already set — skipping"
fi

# ── 3. Cache configuration ────────────────────────────────────────────────
echo "[3/6] Caching configuration..."
php artisan config:cache

# ── 4. Cache routes ───────────────────────────────────────────────────────
echo "[4/6] Caching routes..."
php artisan route:cache

# ── 5. Cache views ────────────────────────────────────────────────────────
echo "[5/6] Compiling Blade views..."
php artisan view:cache

# ── 6. Run migrations (if DB is configured) ───────────────────────────────
echo "[6/6] Running database migrations..."
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    php artisan migrate --force --no-interaction
    echo "Migrations complete."
else
    echo "No remote DB configured — skipping migrations."
fi

echo ""
echo "======================================"
echo "  Build complete!"
echo "======================================"
