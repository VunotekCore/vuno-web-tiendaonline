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
    php -S localhost:8000 backend/dev-router.php > /tmp/php-build.log 2>&1 &
    PHP_PID=$!

    echo "=== Building static frontend to dist/ ==="
    PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

    echo "=== Stopping temporary PHP API ==="
    kill $PHP_PID 2>/dev/null || true
    wait $PHP_PID 2>/dev/null || true

    echo ""
    echo "🚀 http://localhost:4321"
    echo "   Static assets from dist/ (built frontend)"
    echo "   PHP APIs from backend/ source (live, no copy needed)"
    echo ""
    php -S localhost:4321 backend/dev-router.php
    ;;

  api)
    echo "=== PHP API server on :8000 ==="
    echo "   API served from backend/ source (no dist/ dependency)"
    echo "   Run './dev.sh hmr' in another terminal for Astro HMR"
    echo ""
    php -S localhost:8000 backend/dev-router.php
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
