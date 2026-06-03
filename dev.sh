#!/bin/bash
# Ram;Lop Development Script
#
# Single-terminal (recommended, mimics production):
#   ./dev.sh          → builds + PHP server on :4321
#
# Two-terminal (HMR for frontend, PHP for APIs on :8000):
#   Terminal 1: ./dev.sh api   → PHP server on :8000
#   Terminal 2: ./dev.sh hmr   → Astro dev on :4321 (no proxy)

CWD=$(pwd)
CMD=${1:-start}

case "$CMD" in
  start)
    echo "=== Starting temporary PHP API on :8000 for build ==="
    php -S localhost:8000 php/build-router.php > /tmp/php-build.log 2>&1 &
    PHP_PID=$!

    echo "=== Building static frontend to dist/ ==="
    PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

    echo "=== Stopping temporary PHP API ==="
    kill $PHP_PID 2>/dev/null || true
    wait $PHP_PID 2>/dev/null || true

    echo "=== Copying PHP backend to dist/ ==="
    rm -rf dist/api dist/includes dist/email-templates dist/database dist/config.php dist/composer.json dist/vendor
    cp -r php/api dist/api
    cp -r php/includes dist/includes
    cp -r php/email-templates dist/email-templates
    cp -r php/database dist/database
    cp php/config.php dist/config.php
    cp php/composer.json dist/composer.json
    if [ -f .env ]; then
        cp .env dist/.env
    fi
    echo "=== Composer install ==="
    cd "$CWD/dist"
    if [ -f composer.json ]; then
      composer install --no-dev --no-interaction --prefer-dist 2>&1 || true
    fi
    cd "$CWD"

    echo ""
    echo "🚀 http://localhost:4321"
    php -S localhost:4321 php/dev-router.php
    ;;

  api)
    if [ ! -f dist/index.html ]; then
      echo "=== Starting temporary PHP API on :8000 for build ==="
      php -S localhost:8000 php/build-router.php > /tmp/php-build.log 2>&1 &
      PHP_PID=$!

      echo "=== Building frontend ==="
      PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

      kill $PHP_PID 2>/dev/null || true
      wait $PHP_PID 2>/dev/null || true

      rm -rf dist/api dist/includes dist/email-templates dist/database dist/config.php dist/composer.json dist/vendor
      cp -r php/api dist/api
      cp -r php/includes dist/includes
      cp -r php/email-templates dist/email-templates
      cp -r php/database dist/database
      cp php/config.php dist/config.php
      cp php/composer.json dist/composer.json
      if [ -f .env ]; then
        cp .env dist/.env
      fi
    fi
    cd "$CWD/dist" && composer install --no-dev --no-interaction --prefer-dist 2>&1 || true && cd "$CWD"
    echo "=== PHP API server on :8000 (run './dev.sh hmr' in another terminal) ==="
    php -S localhost:8000 php/dev-router.php
    ;;

  hmr)
    echo "=== Astro dev server on :4321 ==="
    echo "   API calls go to http://localhost:8000 (CORS)"
    echo "   Make sure PHP is running on :8000"
    echo ""
    pnpm dev
    ;;

  *)
    echo "Ram;Lop — Development"
    echo ""
    echo "  ./dev.sh         Build + PHP server on :4321 (recommended)"
    echo "  ./dev.sh api     PHP API server on :8000"
    echo "  ./dev.sh hmr     Astro HMR on :4321 (calls APIs on :8000 via CORS)"
    echo ""
    ;;
esac
