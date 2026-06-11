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
$items = $input['items'] ?? [];

setCartItems($customerId, $items);

// Return updated cart
$serverItems = getCartItems($customerId);
jsonResponse(['items' => $serverItems, 'total' => count($serverItems)]);
