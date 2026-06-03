#!/bin/bash
# Ram;Lop Deploy Script
# Builds Astro static site + copies PHP API backend + installs Composer dependencies

CWD=$(pwd)

echo "🚀 Starting temporary PHP API on :8000 for build..."
php -S localhost:8000 php/build-router.php > /tmp/php-build.log 2>&1 &
PHP_PID=$!

echo "🚀 Building Astro static site..."
PUBLIC_API_URL=http://127.0.0.1:8000/api pnpm build

echo "🛑 Stopping temporary PHP API..."
kill $PHP_PID 2>/dev/null || true
wait $PHP_PID 2>/dev/null || true

echo "📦 Copying PHP backend to dist/..."
rm -rf dist/api dist/includes dist/blog dist/email-templates dist/database dist/config.php dist/composer.json dist/vendor
cp -r php/api dist/api
cp -r php/includes dist/includes
cp -r php/blog dist/blog
cp -r php/email-templates dist/email-templates
cp -r php/database dist/database
cp php/config.php dist/config.php
cp php/composer.json dist/composer.json

echo "🎨 Copying .env to dist/..."
if [ -f .env ]; then
    cp .env dist/.env
elif [ -f php/.env ]; then
    cp php/.env dist/.env
fi

echo "📚 Installing Composer dependencies..."
cd "$CWD/dist"
if command -v composer &> /dev/null; then
    composer install --no-dev --no-interaction --prefer-dist 2>&1 || true
else
    echo "⚠️  Composer not found. Install manually:"
    echo "   cd $(pwd) && composer install --no-dev"
fi
cd "$CWD"

echo ""
echo "✅ Deploy ready!"
echo "   📂 dist/ — full deployable directory (frontend + PHP APIs)"
echo "   📤 Upload dist/ contents to your hosting's web root"
echo ""
echo "   🔑 Remember to configure:"
echo "   - php/.env with your production keys"
echo "   - Or set env vars in your hosting panel"
