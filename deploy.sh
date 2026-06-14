#!/bin/bash
# Ram;Lop Deploy Script
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
      echo "Ram;Lop Deploy Script"
      echo ""
      echo "  bash deploy.sh               Full deploy (default)"
      echo "  bash deploy.sh --no-install  Build + copy files, skip composer install"
      echo ""
      exit 0
      ;;
  esac
done

echo "==> 1/5 Starting temporary PHP API on :8000 for build..."
php -S localhost:8000 php/build-router.php > /tmp/php-build.log 2>&1 &
PHP_PID=$!

echo "==> 2/5 Building Astro static site..."
PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

echo "==> 3/5 Stopping temporary PHP API..."
kill $PHP_PID 2>/dev/null || true
wait $PHP_PID 2>/dev/null || true

echo "==> 4/5 Copying PHP backend to dist/..."
# Clean previous PHP artifacts
rm -rf dist/api dist/includes dist/email-templates dist/database dist/config.php dist/composer.json dist/vendor dist/encryption.key dist/.htaccess dist/install.php

# Copy PHP API endpoints
cp -r php/api dist/api
cp -r php/includes dist/includes
cp -r php/email-templates dist/email-templates
cp -r php/database dist/database
cp php/config.php dist/config.php
cp php/composer.json dist/composer.json
cp php/encryption.key dist/encryption.key

# Copy production .htaccess (rename from htaccess-production)
if [ -f php/htaccess-production ]; then
  cp php/htaccess-production dist/.htaccess
fi

# Copy installer (removed after first use)
if [ -f php/install.php ]; then
  cp php/install.php dist/install.php
fi

# Remove local database config — install.php creates it
rm -f dist/database/config.php

# Copy .env if present
echo "==> Copying .env to dist/..."
if [ -f .env ]; then
  cp .env dist/.env
elif [ -f php/.env ]; then
  cp php/.env dist/.env
fi

# Generate composer.lock if missing
if [ ! -f dist/composer.lock ]; then
  if [ -f php/composer.lock ]; then
    cp php/composer.lock dist/composer.lock
  fi
fi

# Copy existing vendor/ if present (avoids server-side composer)
if [ -d php/vendor ]; then
  echo "==> Copying existing vendor/ from php/vendor..."
  cp -r php/vendor dist/vendor
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
      echo "   Install it, or run 'composer install --no-dev' locally in php/"
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
