<?php
/**
 * Ram;Lop — Dev / Build server router for PHP built-in server.
 *
 * Priority order:
 *   1. Blog route redirects
 *   2. PHP APIs → backend/ source first, fallback dist/
 *   3. Static assets → dist/ (HTML, CSS, JS, images)
 *   4. Directory index → dist/ (index.html / index.php)
 *
 * This ensures development changes to backend/api/ are reflected
 * instantly.  In production (dist/ only), PHP falls back to dist/
 * when backend/ source is absent.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$distPath = __DIR__ . '/../dist';

// =============================================================================
//  1. Blog route redirects
//     Astro generates /es/blog and /en/blog as static pages.  The client-side
//     JS reads ?slug= to render the right post.  We redirect /blog/{slug}
//     (and any /{lang}/blog/{slug}) so that bookmarked URLs still work.
// =============================================================================

// /{lang}/blog/{slug} → /{lang}/blog?slug={slug}
if (preg_match('#^/(es|en)/blog/(.+)$#', $uri, $m)) {
    $locale = $m[1];
    $slug = $m[2];
    $qs = $_SERVER['QUERY_STRING'] ? '&slug=' . rawurlencode($slug) : '?slug=' . rawurlencode($slug);
    header('Location: /' . $locale . '/blog' . $qs, true, 301);
    exit;
}

// /blog/{slug} → /es/blog?slug={slug}
if (preg_match('#^/blog/(.+)$#', $uri, $m)) {
    $slug = $m[1];
    $qs = $_SERVER['QUERY_STRING'] ? '&slug=' . rawurlencode($slug) : '?slug=' . rawurlencode($slug);
    header('Location: /es/blog' . $qs, true, 301);
    exit;
}

// /blog or /blog/ → /es/blog
if ($uri === '/blog' || $uri === '/blog/') {
    header('Location: /es/blog', true, 301);
    exit;
}

// =============================================================================
//  2. PHP APIs — backend/ source first, dist/ fallback
//     In development, PHP files are edited in backend/api/.
//     In production (dist/ only), dist/api/ is used instead.
// =============================================================================

if (str_ends_with($uri, '.php') || str_starts_with($uri, '/api/')) {
    // Try backend/ source first
    $srcFile = __DIR__ . $uri;
    if (file_exists($srcFile) && !is_dir($srcFile)) {
        require $srcFile;
        return true;
    }
    // Fallback to dist/ (production / single-server mode)
    $distFile = $distPath . $uri;
    if (file_exists($distFile) && !is_dir($distFile)) {
        require $distFile;
        return true;
    }
}

// =============================================================================
//  3. Static assets from dist/ (HTML, CSS, JS, images, fonts)
// =============================================================================

$file = $distPath . $uri;
if (file_exists($file) && !is_dir($file)) {
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
    if (isset($mimeMap[$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
        header('Content-Type: ' . $mimeMap[$ext]);
    }
    readfile($file);
    return true;
}

// =============================================================================
//  4. Directory index from dist/
// =============================================================================

// Try index.html
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
