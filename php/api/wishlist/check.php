<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$email = $_GET['email'] ?? '';
$productId = $_GET['product_id'] ?? '';

if (empty($email) || empty($productId)) {
    jsonError('Customer email and product_id are required');
}

$db = getDb();
$stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$customerId = $stmt->fetchColumn();

if (!$customerId) {
    jsonResponse(['inWishlist' => false]);
}

$inWishlist = isInWishlist((int)$customerId, $productId);
jsonResponse(['inWishlist' => $inWishlist]);
