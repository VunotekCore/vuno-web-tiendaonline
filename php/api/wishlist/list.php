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
    if (empty($email)) jsonError('Customer email or authentication required');
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $customerId = $stmt->fetchColumn();
}

if (!$customerId) {
    jsonResponse(['items' => [], 'total' => 0]);
}

$items = getWishlist((int)$customerId);
jsonResponse(['items' => $items, 'total' => count($items)]);
