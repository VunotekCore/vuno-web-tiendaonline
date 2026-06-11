<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (empty($token)) jsonError('Authentication required', 401);

$customerId = getCustomerIdFromToken($token);
if (!$customerId) jsonError('Unauthorized', 401);

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['product_id'])) jsonError('product_id is required');

$productId = $input['product_id'];
$quantity = (int)($input['quantity'] ?? 1);
$color = $input['color'] ?? '';
$size = $input['size'] ?? '';

addCartItem($customerId, $productId, $quantity, $color, $size);

jsonResponse(['success' => true]);
