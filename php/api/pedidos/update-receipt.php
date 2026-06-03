<?php
/**
 * POST /api/pedidos/update-receipt.php
 * Updates the transfer receipt URL for a pending order
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id']) || empty($input['transferReceipt'])) {
    jsonError('Order ID and transferReceipt URL required');
}

$order = getOrderById($input['id']);
if (!$order) {
    jsonError('Order not found', 404);
}

$db = getDb();
$stmt = $db->prepare('UPDATE orders SET transfer_receipt_url = ? WHERE order_number = ?');
$stmt->execute([$input['transferReceipt'], $input['id']]);

jsonResponse(['success' => true, 'id' => $input['id']]);
