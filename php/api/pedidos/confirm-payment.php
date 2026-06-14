<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['id'] ?? '';
$piId = $input['stripePaymentIntentId'] ?? '';

if (!$orderId || !$piId) {
    jsonError('Missing order ID or payment intent ID');
}

try {
    $order = getOrderById($orderId);
    if (!$order) {
        jsonError('Order not found', 404);
    }

    if ($order['status'] === 'paid') {
        jsonResponse(['success' => true, 'id' => $orderId, 'alreadyPaid' => true]);
    }

    updateOrderStatus($orderId, 'paid', 'completed', $piId);
    deductStock($orderId);

    $bankAccounts = getBankAccounts();
    sendOrderConfirmation($order, $bankAccounts);
    sendNewOrderNotification($order);

    jsonResponse(['success' => true, 'id' => $orderId]);
} catch (\Exception $e) {
    jsonError('Failed to confirm payment: ' . $e->getMessage(), 500);
}
