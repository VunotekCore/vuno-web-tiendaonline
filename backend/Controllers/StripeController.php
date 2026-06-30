<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\OrderModel;
use App\Models\SettingModel;
use App\Services\EmailService;
use App\Services\StripeService;
use App\Traits\ApiResponse;

final class StripeController
{
    use ApiResponse;

    public function __construct(
        private StripeService $stripe,
        private OrderModel $orderModel,
        private ?EmailService $emailService = null,
        private ?SettingModel $settingModel = null,
    ) {
        $this->emailService ??= new EmailService();
        $this->settingModel ??= new SettingModel(\App\Config\Database::getConnection());
    }

    public function createPaymentIntent(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $items = is_array($input) ? ($input['items'] ?? []) : [];
        $customerEmail = is_string($input['customerEmail'] ?? null) ? $input['customerEmail'] : '';
        $total = isset($input['total']) && is_numeric($input['total']) ? (float) $input['total'] : null;

        if ($items === []) {
            $this->jsonError('No items provided');
        }

        try {
            $result = $this->stripe->createPaymentIntent($items, $customerEmail, $total);
            $this->jsonResponse([
                'clientSecret' => $result['clientSecret'],
                'id' => $result['id'],
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function webhook(): void
    {
        header('Content-Type: application/json');

        try {
            $payload = (string) file_get_contents('php://input');
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            $result = $this->stripe->verifyWebhook($payload, $sigHeader);

            error_log("[Stripe Webhook] Event: {$result['type']} | ID: {$result['id']}");

            if ($result['type'] === 'payment_intent.succeeded') {
                $order = $this->orderModel->getByStripeIntentId($result['id']);
                $status = is_string($order['status'] ?? null) ? $order['status'] : '';
                if ($order !== null && $status !== 'paid') {
                    $this->orderModel->updateStatus($order, 'paid', 'completed', $result['id']);
                    $this->orderModel->deductStock($result['id']);
                    $bankAccounts = $this->getBankAccounts();
                    $this->emailService->sendOrderConfirmation($order, $bankAccounts);
                    $this->emailService->sendNewOrderNotification($order);
                    error_log("[Stripe Webhook] Order {$order['id']} updated to paid via webhook");
                } else {
                    error_log("[Stripe Webhook] Order for intent {$result['id']} not found or already paid");
                }
            } elseif ($result['type'] === 'payment_intent.payment_failed') {
                $order = $this->orderModel->getByStripeIntentId($result['id']);
                if ($order !== null) {
                    $db = $this->orderModel->getDb();
                    $psId = $this->orderModel->resolvePaymentStatusId('failed');
                    $cancelledId = $this->orderModel->resolveStatusId('cancelled');
                    $orderId = $order['id'] ?? '';
                    $db->prepare('UPDATE orders SET payment_status_id = ?, status_id = ? WHERE order_number = ?')
                       ->execute([$psId, $cancelledId, $orderId]);
                    error_log("[Stripe Webhook] Order {$orderId} marked as failed: {$result['error']}");
                }
            }

            $this->jsonResponse(['received' => true]);
        } catch (\UnexpectedValueException $e) {
            error_log("[Stripe Webhook] Signature verification failed: " . $e->getMessage());
            $this->jsonError('Invalid signature', 400);
        } catch (\Throwable $e) {
            error_log("[Stripe Webhook] Error: " . $e->getMessage());
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function getBankAccounts(): array
    {
        try {
            $settings = $this->settingModel->getAll();
            $transfer = $settings['transfer'] ?? [];
            return is_array($transfer['banks'] ?? null) ? $transfer['banks'] : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
