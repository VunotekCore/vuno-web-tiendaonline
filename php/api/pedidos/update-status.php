<?php
declare(strict_types=1);

/**
 * POST /api/pedidos/update-status.php - Update order status
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id']) || empty($input['status'])) {
    jsonError('Order ID and status required');
}

$validStatuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
if (!in_array($input['status'], $validStatuses)) {
    jsonError('Invalid status');
}

$paymentStatus = null;
if ($input['status'] === 'paid') $paymentStatus = 'completed';
if ($input['status'] === 'cancelled') {
    $paymentStatus = 'failed';
    restoreStock($input['id']);
}

try {
    $order = getOrderById($input['id']);
    $oldStatus = $order['status'] ?? 'unknown';
    $newStatus = $input['status'];
    updateOrderStatus($input['id'], $newStatus, $paymentStatus);
    logAdminAction('update', 'order', $input['id'], 'Order status changed: ' . $oldStatus . ' → ' . $newStatus, [
        'from_status' => $oldStatus,
        'to_status' => $newStatus,
    ]);

    if ($newStatus !== $oldStatus && in_array($newStatus, ['paid', 'shipped', 'delivered'])) {
        try {
            $bankAccounts = getBankAccounts();
            sendOrderConfirmation($order, $bankAccounts, $newStatus);
        } catch (\Throwable $e) {
            error_log('Status change email failed: ' . $e->getMessage());
        }
    }

    jsonResponse(['success' => true]);
} catch (\Exception $e) {
    jsonError($e->getMessage(), 404);
}
