<?php
/**
 * POST /api/productos/update.php - Update an existing product
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/helpers.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) jsonError('Product ID required');

$existing = getProductById($input['id']);
if (!$existing) jsonError('Product not found', 404);

$name = $input['name'] ?? $existing['name'];
$images = $input['images'] ?? $existing['images'];
if (count($images) > MAX_PRODUCT_IMAGES) jsonError('Maximum ' . MAX_PRODUCT_IMAGES . ' images allowed');

$product = [
    'id' => $existing['id'],
    'name' => $name,
    'slug' => $input['slug'] ?? ($name !== $existing['name'] ? slugify($name) : $existing['slug']),
    'description' => $input['description'] ?? $existing['description'],
    'details' => $input['details'] ?? $existing['details'],
    'price' => (float)($input['price'] ?? $existing['price']),
    'currency' => $input['currency'] ?? $existing['currency'] ?? 'USD',
    'images' => $images,
    'category' => $input['category'] ?? $existing['category'],
    'colors' => $input['colors'] ?? $existing['colors'],
    'sizes' => $input['sizes'] ?? $existing['sizes'],
    'stocks' => $input['stocks'] ?? [],
    'createdAt' => $existing['createdAt'],
];

saveProduct($product);

$changes = [];
foreach (['name', 'price', 'description', 'category'] as $field) {
    if (($input[$field] ?? null) !== null && (string)($input[$field]) !== (string)($existing[$field] ?? '')) {
        $changes[$field] = ['from' => $existing[$field] ?? '', 'to' => $input[$field]];
    }
}
logAdminAction('update', 'product', $product['id'], 'Updated product: ' . $product['name'], $changes);

jsonResponse($product);
