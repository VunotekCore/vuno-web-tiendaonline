#!/bin/bash
# Ram;Lop Development Script
#
# ./dev.sh          → PHP API (:8000) + Astro HMR (:4321) en una terminal
# ./dev.sh api      → Solo PHP API (:8000)
# ./dev.sh hmr      → Solo Astro HMR (:4321, espera PHP en :8000)

CWD=$(pwd)
CMD=${1:-start}

case "$CMD" in
  start)
    echo "=== PHP API server on :8000 (background) ==="
    php -S localhost:8000 "$CWD/backend/dev-router.php" > /tmp/php-api.log 2>&1 &
    PHP_PID=$!
    echo "  PID $PHP_PID — log: /tmp/php-api.log"

    echo "=== Astro HMR on :4321 (proxy /api → :8000) ==="
    echo ""
    echo "  http://localhost:4321"
    echo "  Ctrl+C to stop both servers"
    echo ""

    trap "kill $PHP_PID 2>/dev/null; exit" INT TERM
    pnpm dev
    kill $PHP_PID 2>/dev/null
    wait $PHP_PID 2>/dev/null
    ;;

  api)
    echo "=== PHP API server on :8000 ==="
    php -S localhost:8000 "$CWD/backend/dev-router.php"
    ;;

  hmr)
    echo "=== Astro dev server on :4321 ==="
    echo "  API calls go to http://localhost:8000 (vite proxy)"
    pnpm dev
    ;;

  *)
    echo "Ram;Lop — Development"
    echo ""
    echo "  ./dev.sh         PHP :8000 + Astro HMR :4321 (recommended)"
    echo "  ./dev.sh api     PHP API server on :8000"
    echo "  ./dev.sh hmr     Astro HMR on :4321"
    echo ""
    ;;
esac
