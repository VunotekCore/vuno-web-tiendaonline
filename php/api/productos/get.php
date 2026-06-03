<?php
/**
 * GET /api/productos/get.php?id=X - Get single product by ID
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$id = $_GET['id'] ?? '';
if (!$id) jsonError('Product ID required');

$product = getProductById($id);
if (!$product) jsonError('Product not found', 404);

jsonResponse($product);
