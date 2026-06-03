<?php
/**
 * Dev server router for PHP built-in server.
 * Serves static files from dist/ and routes dynamic blog posts.
 *
 * Usage: php -S localhost:4321 php/dev-router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$distPath = __DIR__ . '/../dist';

// Blog dynamic route: /blog/{slug}
if (preg_match('#^/blog/(.+)$#', $uri, $m)) {
    $slug = $m[1];
    // Skip if it's a known static file
    if ($slug !== 'post.php' && $slug !== 'index.php' && !file_exists($distPath . $uri)) {
        $_GET['slug'] = $slug;
        require __DIR__ . '/blog/index.php';
        return true;
    }
}

// Serve from dist/
$file = $distPath . $uri;
if (file_exists($file) && !is_dir($file)) {
    return false; // Let PHP serve the static file
}

// Try index.html for directories
$indexFile = rtrim($file, '/') . '/index.html';
if (file_exists($indexFile)) {
    require $indexFile;
    return true;
}

// Try index.php
$indexPhp = rtrim($file, '/') . '/index.php';
if (file_exists($indexPhp)) {
    require $indexPhp;
    return true;
}

return false;
