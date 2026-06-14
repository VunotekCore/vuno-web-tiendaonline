<?php
declare(strict_types=1);

// Router for PHP built-in server during Astro build
// Maps /api/* requests from astro build to php/api/*
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($uri, '/api/')) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

return false;
