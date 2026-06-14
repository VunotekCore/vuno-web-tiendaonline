<?php
declare(strict_types=1);

/**
 * POST /api/stripe/webhook.php
 * Receives Stripe webhook events
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/email.php';

header('Content-Type: application/json');

try {
    $result = handleWebhook();
    error_log("[Stripe Webhook] Event: {$result['type']} | ID: {$result['id']}");

    if ($result['type'] === 'payment_intent.succeeded') {
        $order = getOrderByStripeIntentId($result['id']);
        if ($order && $order['status'] !== 'paid') {
            updateOrderStatus($order['id'], 'paid', 'completed');
            deductStock($order['id']);
            $bankAccounts = getBankAccounts();
            sendOrderConfirmation($order, $bankAccounts);
            sendNewOrderNotification($order);
            error_log("[Stripe Webhook] Order {$order['id']} updated to paid via webhook");
        } else {
            error_log("[Stripe Webhook] Order for intent {$result['id']} not found or already paid");
        }
    } elseif ($result['type'] === 'payment_intent.payment_failed') {
        $order = getOrderByStripeIntentId($result['id']);
        if ($order) {
            // Update order status and payment status for failed payments
            $db = getDb();
            $psId = resolvePaymentStatusId('failed');
            $cancelledId = resolveStatusId('cancelled');
            $db->prepare('UPDATE orders SET payment_status_id = ?, status_id = ? WHERE order_number = ?')
               ->execute([$psId, $cancelledId, $order['id']]);
            error_log("[Stripe Webhook] Order {$order['id']} marked as failed: {$result['error']}");
        }
    }

    jsonResponse(['received' => true]);
} catch (\UnexpectedValueException $e) {
    error_log("[Stripe Webhook] Signature verification failed: " . $e->getMessage());
    jsonError('Invalid signature', 400);
} catch (\Exception $e) {
    error_log("[Stripe Webhook] Error: " . $e->getMessage());
    jsonError($e->getMessage(), 500);
}
