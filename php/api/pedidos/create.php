<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/helpers.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input) || empty($input['items'])) {
    jsonError('Invalid order data');
}

try {
    $orderId = $input['id'] ?? generateOrderId();

    // Idempotency check — if order already exists, return success
    if (getOrderById($orderId)) {
        jsonResponse(['success' => true, 'id' => $orderId, 'existing' => true]);
        exit;
    }

    $order = [
        'id' => $orderId,
        'items' => $input['items'],
        'subtotal' => (float)($input['subtotal'] ?? 0),
        'shipping' => (float)($input['shipping'] ?? 0),
        'tax' => (float)($input['tax'] ?? 0),
        'total' => (float)($input['total'] ?? 0),
        'status' => $input['status'] ?? 'pending',
        'paymentMethod' => $input['paymentMethod'] ?? 'stripe',
        'paymentStatus' => $input['paymentStatus'] ?? 'pending',
        'stripePaymentIntentId' => $input['stripePaymentIntentId'] ?? null,
        'transferReceipt' => $input['transferReceipt'] ?? null,
        'selectedBankId' => $input['selectedBankId'] ?? null,
        'customer' => $input['customer'] ?? [],
        'createdAt' => $input['createdAt'] ?? date('c'),
    ];

    saveOrder($order);

    if ($order['paymentMethod'] === 'transfer') {
        try {
            sendOrderConfirmation($order);
            sendNewOrderNotification($order);
        } catch (\Throwable $e) {
            error_log('Order created but email failed: ' . $e->getMessage());
        }
    }

    jsonResponse(['success' => true, 'id' => $order['id']]);
} catch (\Throwable $e) {
    jsonError('Failed to save order: ' . $e->getMessage(), 500);
}
