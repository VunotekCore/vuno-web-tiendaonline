<?php
/**
 * POST /api/productos/create.php - Create a new product
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
if (!$input) jsonError('Invalid JSON');

$name = $input['name'] ?? '';
$price = (float)($input['price'] ?? 0);
if (empty($name) || $price <= 0) jsonError('Name and price required');

$images = $input['images'] ?? [];
if (count($images) > MAX_PRODUCT_IMAGES) jsonError('Maximum ' . MAX_PRODUCT_IMAGES . ' images allowed');

$product = [
    'id' => $input['id'] ?? ('prod-' . bin2hex(random_bytes(4))),
    'name' => $name,
    'slug' => $input['slug'] ?? slugify($name),
    'description' => $input['description'] ?? '',
    'details' => $input['details'] ?? null,
    'price' => $price,
    'currency' => $input['currency'] ?? 'USD',
    'size_prefix' => $input['size_prefix'] ?? 'EU',
    'images' => $images,
    'category' => $input['category'] ?? 'Heels',
    'colors' => $input['colors'] ?? [],
    'sizes' => $input['sizes'] ?? [],
    'stocks' => $input['stocks'] ?? [],
    'isFeatured' => !empty($input['isFeatured']),
    'createdAt' => date('c'),
];

saveProduct($product);
logAdminAction('create', 'product', $product['id'], 'Created product: ' . $product['name']);
jsonResponse($product, 201);
