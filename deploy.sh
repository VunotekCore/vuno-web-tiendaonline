#!/bin/bash
# Vunotek Deploy Script
# Builds Astro static site + copies PHP API backend + installs Composer dependencies
#
# Usage:
#   bash deploy.sh              # Full deploy (build + PHP + composer)
#   bash deploy.sh --no-install # Build only, skip composer install (useful if vendor/ already present)
#   bash deploy.sh --help       # Show help

set -euo pipefail

CWD=$(pwd)
SKIP_COMPOSER=false

for arg in "$@"; do
  case "$arg" in
    --no-install) SKIP_COMPOSER=true ;;
    --help|-h)
      echo "Vunotek Deploy Script"
      echo ""
      echo "  bash deploy.sh               Full deploy (default)"
      echo "  bash deploy.sh --no-install  Build + copy files, skip composer install"
      echo ""
      exit 0
      ;;
  esac
done

echo "==> 1/5 Starting temporary PHP API on :8000 for build..."
php -S localhost:8000 backend/dev-router.php > /tmp/php-build.log 2>&1 &
PHP_PID=$!

echo "==> 2/5 Building Astro static site..."
PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

echo "==> 3/5 Stopping temporary PHP API..."
kill $PHP_PID 2>/dev/null || true
wait $PHP_PID 2>/dev/null || true

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
cp    backend/encryption.key         dist/encryption.key

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

# Copy .env if present
echo "==> Copying .env to dist/..."
if [ -f .env ]; then
  cp .env dist/.env
elif [ -f backend/.env ]; then
  cp backend/.env dist/.env
fi

# Generate composer.lock if missing
if [ ! -f dist/composer.lock ]; then
  if [ -f backend/composer.lock ]; then
    cp backend/composer.lock dist/composer.lock
  fi
fi

# Copy existing vendor/ if present (avoids server-side composer)
if [ -d backend/vendor ]; then
  echo "==> Copying existing vendor/ from backend/vendor..."
  cp -r backend/vendor dist/vendor
fi

if [ "$SKIP_COMPOSER" = false ]; then
  echo "==> 5/5 Installing Composer dependencies..."
  cd "$CWD/dist"
  if command -v composer &> /dev/null; then
    composer install --no-dev --no-interaction --prefer-dist 2>&1 || true
  else
    if [ ! -d vendor ]; then
      echo ""
      echo "⚠️  Composer not found on this system."
      echo "   Install it, or run 'composer install --no-dev' locally in backend/"
      echo "   and re-run deploy.sh"
      echo ""
    fi
  fi
  cd "$CWD"
else
  echo "==> 5/5 Skipping composer install (--no-install flag)"
fi

echo ""
echo "============================================="
echo "  ✅ Deploy ready!"
echo "  📂 dist/ — full deployable directory"
echo ""
echo "  📤 Upload dist/ contents to your hosting"
echo "  🌐 Then visit /install.php to configure"
echo "============================================="
