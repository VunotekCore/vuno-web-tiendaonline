<?php
declare(strict_types=1);

/**
 * GET /api/productos/get.php?id=X    - Get single product by ID
 * GET /api/productos/get.php?slug=X  - Get single product by slug
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$lang = isset($_GET['lang']) ? trim($_GET['lang']) : null;
$slug = $_GET['slug'] ?? '';
$id   = $_GET['id'] ?? '';

if ($slug) {
    $product = getProductBySlug($slug, $lang);
} elseif ($id) {
    $product = getProductById($id, $lang);
} else {
    jsonError('Product slug or ID required');
}

if (!$product) jsonError('Product not found', 404);

jsonResponse($product);
