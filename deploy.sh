#!/bin/bash
# Vunotek Deploy Script — one-install
# Default: full build (frontend + PHP backend)
# --prod:   backend only (copia PHP a dist/ + .env.production)
#
# Usage:
#   bash deploy.sh                   Full build (frontend + backend, usa .env)
#   bash deploy.sh --prod            Backend only (copia PHP a dist/ + .env.production)
#   bash deploy.sh --help            Show help

set -euo pipefail

CWD=$(pwd)
ENV_FILE=".env"

for arg in "$@"; do
  case "$arg" in
  --prod) ENV_FILE=".env.production" ;;
  --help|-h)
      echo "Vunotek Deploy Script"
      echo ""
      echo "  bash deploy.sh                  Full build (frontend + backend, usa .env)"
      echo "  bash deploy.sh --prod           Backend only (copia PHP a dist/ + .env.production)"
      echo ""
      exit 0
      ;;
  esac
done

if [ "$ENV_FILE" = ".env" ]; then
  echo "==> 1/5 Installing Node dependencies..."
  pnpm install --frozen-lockfile

  echo "==> 2/5 Starting temporary PHP API on :8000 for build..."
  php -S localhost:8000 backend/dev-router.php > /tmp/php-build.log 2>&1 &
  PHP_PID=$!

  echo "==> 3/5 Building Astro static site..."
  pnpm build
  BUILD_EXIT=$?

  echo "==> Stopping temporary PHP API..."
  kill $PHP_PID 2>/dev/null || true
  wait $PHP_PID 2>/dev/null || true

  if [ $BUILD_EXIT -ne 0 ] || [ ! -f dist/index.html ]; then
    echo ""
    echo "❌ Build failed — dist/index.html not found"
    exit 1
  fi
else
  echo "==> 1-3/5 Skipping frontend build (--prod mode)"
fi

echo "==> 4/5 Copying PHP backend to dist/..."
# Clean previous PHP artifacts
rm -rf dist/api dist/email-templates dist/database
rm -rf dist/Controllers dist/Models dist/Services dist/Traits dist/Config dist/Utils
rm -f dist/config.php dist/bootstrap.php dist/autoload.php
rm -f dist/composer.json dist/composer.lock dist/install.php dist/vendor dist/.htaccess

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
cp    backend/composer.lock          dist/composer.lock
cp    backend/install.php            dist/install.php

# Composer vendor
if [ -d backend/vendor ]; then
  cp -r backend/vendor dist/vendor
else
  echo "  ⚠️  backend/vendor not found — run 'composer install' in backend/"
fi

# Production .htaccess (security headers, caching, compression)
if [ -f backend/htaccess-production ]; then
  cp backend/htaccess-production dist/.htaccess
fi

echo "==> 5/5 Copying environment file..."
if [ "$ENV_FILE" = ".env.production" ] && [ -f .env.production ]; then
  cp .env.production dist/.env
  echo "     Using .env.production → dist/.env"
elif [ -f .env ]; then
  cp .env dist/.env
  echo "     Using .env → dist/.env"
elif [ -f backend/.env ]; then
  cp backend/.env dist/.env
  echo "     Using backend/.env → dist/.env"
else
  echo "     ⚠️  No .env file found — create one on the server"
fi

echo ""
echo "============================================="
echo "  ✅ Deploy ready!"
echo "  📂 dist/ — full deployable directory"
echo ""
echo "  📤 Upload dist/ contents to your hosting"
echo "  🌐 Then visit /install.php to configure"
echo "============================================="
