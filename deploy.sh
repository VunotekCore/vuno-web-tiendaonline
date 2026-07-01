#!/bin/bash
# Vunotek Deploy Script
# Builds Astro static site + copies PHP API backend + vendor
#
# Usage:
#   bash deploy.sh               Full deploy (build + PHP + vendor)
#   bash deploy.sh --prod        Backend only (copia PHP + vendor a dist/, sin build frontend)
#   bash deploy.sh --no-install  Build + PHP only, skip vendor copy
#   bash deploy.sh --help        Show help

set -euo pipefail

CWD=$(pwd)
SKIP_COMPOSER=false
SKIP_FRONTEND=false

for arg in "$@"; do
  case "$arg" in
    --prod) SKIP_FRONTEND=true ;;
    --no-install) SKIP_COMPOSER=true ;;
    --help|-h)
      echo "Vunotek Deploy Script"
      echo ""
      echo "  bash deploy.sh               Full deploy (default)"
      echo "  bash deploy.sh --prod        Backend only (sin build frontend)"
      echo "  bash deploy.sh --no-install  Build + PHP only, skip vendor copy"
      echo ""
      exit 0
      ;;
  esac
done

if [ "$SKIP_FRONTEND" = true ]; then
  echo "==> Modo --prod: saltando build frontend..."
else
  echo "==> 1/5 Starting temporary PHP API on :8000 for build..."
  php -S localhost:8000 backend/dev-router.php > /tmp/php-build.log 2>&1 &
  PHP_PID=$!

  echo "==> 2/5 Building Astro static site..."
  PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

  echo "==> 3/5 Stopping temporary PHP API..."
  kill $PHP_PID 2>/dev/null || true
  wait $PHP_PID 2>/dev/null || true
fi

echo "==> 4/5 Copying PHP backend to dist/..."
# Clean previous PHP artifacts
rm -rf dist/api dist/email-templates dist/database dist/config.php dist/composer.json dist/vendor dist/encryption.key dist/.htaccess dist/install.php
rm -rf dist/bootstrap.php dist/autoload.php dist/Controllers dist/Models dist/Services dist/Traits dist/Config dist/Utils

# Copy full SOA backend structure
cp -r backend/api                    dist/api
cp -r backend/Controllers            dist/Controllers
cp -r backend/Models                 dist/Models
cp -r backend/Services               dist/Services
cp -r backend/Traits                 dist/Traits
cp -r backend/Config                 dist/Config
cp -r backend/Utils                  dist/Utils
cp -r backend/email-templates        dist/email-templates
cp -r backend/database               dist/database
cp    backend/config.php             dist/config.php
cp    backend/bootstrap.php          dist/bootstrap.php
cp    backend/autoload.php           dist/autoload.php
cp    backend/composer.json          dist/composer.json
# encryption.key NO se copia — install.php lo genera en producción

# Copy production .htaccess (rename from htaccess-production)
if [ -f backend/htaccess-production ]; then
  cp backend/htaccess-production dist/.htaccess
fi

# Copy installer (removed after first use)
if [ -f backend/install.php ]; then
  cp backend/install.php dist/install.php
fi

# Remove local database config — install.php creates it
rm -f dist/database/config.php

# Copy .env if present (--prod usa .env.production, default usa .env)
if [ "$SKIP_FRONTEND" = true ]; then
  ENV_SOURCE=".env.production"
else
  ENV_SOURCE=".env"
fi

echo "==> Copying $ENV_SOURCE to dist/..."
if [ -f "$ENV_SOURCE" ]; then
  cp "$ENV_SOURCE" dist/.env
elif [ -f backend/"$ENV_SOURCE" ]; then
  cp backend/"$ENV_SOURCE" dist/.env
fi

if [ "$SKIP_COMPOSER" = false ] && [ -d backend/vendor ]; then
  echo "==> 5/5 Copying vendor/ from backend/vendor..."
  cp -r backend/vendor dist/vendor
fi

if [ "$SKIP_COMPOSER" = true ]; then
  echo "==> 5/5 Skipping vendor copy (--no-install flag)"
fi

echo ""
echo "============================================="
echo "  ✅ Deploy ready!"
echo "  📂 dist/ — full deployable directory"
echo ""
echo "  📤 Upload dist/ contents to your hosting"
echo "  🌐 Then visit /install.php to configure"
echo "============================================="
