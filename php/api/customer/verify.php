<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

setCorsHeaders();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader)) {
    jsonError('No token provided', 401);
}

$token = str_replace('Bearer ', '', $authHeader);
if (empty($token)) {
    jsonError('No token provided', 401);
}

$db = getDb();

$stmt = $db->prepare(
    'SELECT c.id, c.name, c.email, c.created_at, c.last_order_at
     FROM customer_sessions cs
     JOIN customers c ON c.id = cs.customer_id
     WHERE cs.token = ? AND cs.expires_at > NOW()
     LIMIT 1'
);
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    jsonError('Invalid or expired token', 401);
}

// Update last activity
$db->prepare('UPDATE customer_sessions SET last_activity = NOW() WHERE token = ?')
   ->execute([$token]);

jsonResponse([
    'customer' => [
        'id'          => (int)$customer['id'],
        'name'        => $customer['name'],
        'email'       => $customer['email'],
        'memberSince' => date('Y-m-d', strtotime($customer['created_at'])),
        'lastOrderAt' => $customer['last_order_at'] ? date('Y-m-d', strtotime($customer['last_order_at'])) : null,
    ],
]);
