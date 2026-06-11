<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

// Resolve customer: prefer Bearer token, fallback to email
$customerId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if ($token) {
    $customerId = getCustomerIdFromToken($token);
}

if (!$customerId) {
    $email = $_GET['email'] ?? '';
    $productId = $_GET['product_id'] ?? '';
    if (empty($email) || empty($productId)) {
        jsonError('Customer email and product_id or authentication required');
    }
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $customerId = $stmt->fetchColumn();
} else {
    $productId = $_GET['product_id'] ?? '';
    if (empty($productId)) jsonError('product_id is required');
}

if (!$customerId) {
    jsonResponse(['inWishlist' => false]);
}

$inWishlist = isInWishlist((int)$customerId, $productId);
jsonResponse(['inWishlist' => $inWishlist]);
