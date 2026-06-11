<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['product_id'])) {
    jsonError('product_id is required');
}

// Resolve customer: prefer Bearer token, fallback to email
$customerId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if ($token) {
    $customerId = getCustomerIdFromToken($token);
}

if (!$customerId && !empty($data['email'])) {
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([$data['email']]);
    $customerId = $stmt->fetchColumn();
}

if (!$customerId) {
    jsonResponse(['success' => true]);
    return;
}

$variantId = !empty($data['variant_id']) ? (int)$data['variant_id'] : null;
removeFromWishlist((int)$customerId, $data['product_id'], $variantId);

jsonResponse(['success' => true, 'inWishlist' => false]);
