<?php
/**
 * Dev server router for PHP built-in server.
 * Serves static files from dist/ and routes dynamic blog posts.
 *
 * Usage: php -S localhost:4321 php/dev-router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$distPath = __DIR__ . '/../dist';

// Blog routes — now served as Astro static pages + client-side ?slug= parameter
// Redirect /{lang}/blog/{slug} → /{lang}/blog?slug={slug}
// Redirect /blog/{slug} → /es/blog?slug={slug}
if (preg_match('#^/(es|en)/blog/(.+)$#', $uri, $m)) {
    $locale = $m[1];
    $slug = $m[2];
    $qs = $_SERVER['QUERY_STRING'] ? '&slug=' . rawurlencode($slug) : '?slug=' . rawurlencode($slug);
    header('Location: /' . $locale . '/blog' . $qs, true, 301);
    exit;
}
if (preg_match('#^/blog/(.+)$#', $uri, $m)) {
    $slug = $m[1];
    $qs = $_SERVER['QUERY_STRING'] ? '&slug=' . rawurlencode($slug) : '?slug=' . rawurlencode($slug);
    header('Location: /es/blog' . $qs, true, 301);
    exit;
}
// Redirect /blog/ (no lang) → /es/blog/
if ($uri === '/blog' || $uri === '/blog/') {
    header('Location: /es/blog', true, 301);
    exit;
}

// Serve from dist/
$file = $distPath . $uri;
if (file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    // Execute PHP files (APIs), readfile for static assets
    if ($ext === 'php') {
        require $file;
        return true;
    }
    $mimeMap = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'html' => 'text/html',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    if (isset($mimeMap[$ext])) {
        header('Content-Type: ' . $mimeMap[$ext]);
    }
    readfile($file);
    return true;
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
