<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (empty($token)) {
    jsonError('No token provided', 401);
}

$db = getDb();

$stmt = $db->prepare(
    'SELECT c.id FROM customer_sessions cs
     JOIN customers c ON c.id = cs.customer_id
     WHERE cs.token = ? AND cs.expires_at > NOW()
     LIMIT 1'
);
$stmt->execute([$token]);
$customerId = $stmt->fetchColumn();

if (!$customerId) {
    jsonError('Unauthorized', 401);
}

$customerId = (int)$customerId;

$stmt = $db->prepare(
    'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
     FROM orders o
     JOIN order_statuses os ON os.id = o.status_id
     JOIN payment_methods pm ON pm.id = o.payment_method_id
     JOIN payment_statuses ps ON ps.id = o.payment_status_id
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC'
);
$stmt->execute([$customerId]);
$rows = $stmt->fetchAll();

$orders = [];
foreach ($rows as $row) {
    $orders[] = buildOrder($row);
}

jsonResponse(['orders' => $orders]);
