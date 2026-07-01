<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CouponModel;
use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\EmailTemplateModel;
use App\Models\OrderModel;
use App\Models\SettingModel;
use App\Models\SubscriberModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Traits\ApiResponse;
use App\Utils\Str;

final class OrderController
{
    use ApiResponse;

    private ?AuthService $auth = null;
    private ?SettingModel $settingModel = null;
    private ?CurrencyModel $currencyModel = null;
    private ?EmailService $emailService = null;
    private ?CustomerModel $customerModel = null;

    public function __construct(
        private OrderModel $model,
        private ?CouponModel $couponModel = null,
    ) {
        if ($this->couponModel === null) {
            $this->couponModel = new CouponModel(\App\Config\Database::getConnection());
        }
    }

    private function getAuth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService(new UserModel(\App\Config\Database::getConnection()));
        }
        return $this->auth;
    }

    private function getSettings(): SettingModel
    {
        if ($this->settingModel === null) {
            $this->settingModel = new SettingModel(\App\Config\Database::getConnection());
        }
        return $this->settingModel;
    }

    private function getCurrencyModel(): CurrencyModel
    {
        if ($this->currencyModel === null) {
            $this->currencyModel = new CurrencyModel(\App\Config\Database::getConnection());
        }
        return $this->currencyModel;
    }

    private function getEmailService(): EmailService
    {
        if ($this->emailService === null) {
            $db = \App\Config\Database::getConnection();
            $this->emailService = new EmailService(new EmailTemplateModel($db), new SubscriberModel($db));
        }
        return $this->emailService;
    }

    private function getCustomerModel(): CustomerModel
    {
        if ($this->customerModel === null) {
            $this->customerModel = new CustomerModel(\App\Config\Database::getConnection());
        }
        return $this->customerModel;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    private function queryString(string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    private function queryInt(string $key): ?int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : null;
    }

    /** @param array<string, mixed> $data */
    private function str(array $data, string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        if (is_string($val)) return $val;
        if (is_scalar($val)) return (string) $val;
        return $default;
    }

    /** @param array<string, mixed> $data */
    private function flt(array $data, string $key, float $default = 0.0): float
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (float) $val : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    private function arr(array $data, string $key): array
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_array($val) ? $val : [];
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    // =========================================================================
    //  API methods
    // =========================================================================

    public function list(): void
    {
        $result = $this->model->getAll(
            $this->queryInt('limit'),
            $this->queryInt('offset'),
            $this->queryString('search') ?: null,
            $this->queryString('status') ?: null,
        );
        $this->jsonResponse($result);
    }

    public function get(): void
    {
        $id = $this->queryString('id');
        if ($id === '') {
            $this->jsonError('Order ID required');
        }
        $order = $this->model->getById($id);
        if ($order === null) {
            $this->jsonError('Order not found', 404);
        }
        $this->jsonResponse($order);
    }

    public function create(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $items = $this->arr($body, 'items');
        if ($items === []) {
            $this->jsonError('Invalid order data');
        }

        try {
            $orderId = $this->str($body, 'id');
            if ($orderId === '') {
                $orderId = Str::generateOrderId();
            }

            // Idempotency check
            $existing = $this->model->getById($orderId);
            if ($existing !== null) {
                $this->jsonResponse(['success' => true, 'id' => $orderId, 'existing' => true]);
            }

            $customerId = $this->str($body, 'customerId') ?: null;
            if ($customerId === null) {
                /** @var mixed $authHeader */
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                $token = is_string($authHeader) ? str_replace('Bearer ', '', $authHeader) : '';
                if ($token !== '') {
                    $cid = $this->getCustomerModel()->getCustomerIdFromToken($token);
                    if ($cid !== null) {
                        $customerId = (string) $cid;
                    }
                }
            }

            $subtotal = $this->flt($body, 'subtotal');
            $shipping = $this->flt($body, 'shipping');
            $discountTotal = $this->flt($body, 'discountTotal');

            $settings = $this->getSettings()->getAll();
            $taxRate = (float) ($settings['tax']['rate'] ?? 0) / 100;
            $tax = round(($subtotal - $discountTotal) * $taxRate, 2);
            $total = round(max(0, $subtotal - $discountTotal + $shipping + $tax), 2);

            $order = [
                'id' => $orderId,
                'items' => $items,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'tax' => $tax,
                'total' => $total,
                'currency' => $this->str($body, 'currency', 'USD'),
                'exchange_rate' => $this->flt($body, 'exchange_rate', 1.0),
                'status' => $this->str($body, 'status', 'pending'),
                'paymentMethod' => $this->str($body, 'paymentMethod', 'stripe'),
                'paymentStatus' => $this->str($body, 'paymentStatus', 'pending'),
                'stripePaymentIntentId' => $this->str($body, 'stripePaymentIntentId') ?: null,
                'transferReceipt' => $this->str($body, 'transferReceipt') ?: null,
                'selectedBankId' => $this->str($body, 'selectedBankId') ?: null,
                'customer' => $this->arr($body, 'customer'),
                'customerId' => $customerId,
                'createdAt' => $this->str($body, 'createdAt', date('c')),
                'discountTotal' => $discountTotal,
                'couponCode' => $this->str($body, 'couponCode') ?: null,
            ];

            $this->saveOrder($order, $this->model);

            $order = $this->getCurrencyModel()->addDisplayPricesToOrder($order);

            if ($order['paymentMethod'] === 'transfer') {
                try {
                    $this->getEmailService()->sendNewOrderNotification($order);
                } catch (\Throwable $e) {
                    error_log('Order created but email failed: ' . $e->getMessage());
                }
            }

            $this->jsonResponse(['success' => true, 'id' => $order['id']]);
        } catch (\Throwable $e) {
            $this->jsonError('Failed to save order: ' . $e->getMessage(), 500);
        }
    }

    public function createPos(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $cartItems = $this->arr($body, 'cart_items');
        if ($cartItems === []) {
            $this->jsonError('Se requieren items en el carrito');
        }

        $paymentMethodCode = $this->str($body, 'payment_method', 'pos_cash');
        $validPosMethods = ['pos_cash', 'pos_card', 'pos_transfer'];
        if (!in_array($paymentMethodCode, $validPosMethods, true)) {
            $this->jsonError('Método de pago POS inválido');
        }

        $db = $this->model->getDb();
        $db->beginTransaction();

        try {
            $total = 0.0;
            $items = [];
            $orderId = Str::generateOrderId();

            foreach ($cartItems as $item) {
                /** @var array<string, mixed> $item */
                $variantId = is_numeric($item['variant_id'] ?? null) ? (int) $item['variant_id'] : 0;
                $qty = is_numeric($item['quantity'] ?? null) ? max(1, (int) $item['quantity']) : 1;
                if ($variantId <= 0) {
                    continue;
                }

                $stmt = $db->prepare(
                    'SELECT pv.id, pv.stock, pv.price_override,
                            p.id AS product_id, p.name, p.slug AS product_slug, p.price, p.currency,
                            pc.name AS color_name, ps.label AS size_label
                     FROM product_variants pv
                     JOIN products p ON p.id = pv.product_id
                     JOIN product_colors pc ON pc.id = pv.color_id
                     JOIN product_sizes ps ON ps.id = pv.size_id
                     WHERE pv.id = ?
                     FOR UPDATE'
                );
                $stmt->execute([$variantId]);
                /** @var ?array<string, mixed> $variant */
                $variant = $stmt->fetch();
                if (!$variant) {
                    throw new \RuntimeException("Variante ID $variantId no encontrada");
                }

                $variantStock = is_numeric($variant['stock'] ?? null) ? (int) $variant['stock'] : 0;
                $stockBefore = $variantStock;
                if ($stockBefore < $qty) {
                    $vName = is_string($variant['name'] ?? null) ? $variant['name'] : '';
                    $vColor = is_string($variant['color_name'] ?? null) ? $variant['color_name'] : '';
                    $vSize = is_string($variant['size_label'] ?? null) ? $variant['size_label'] : '';
                    throw new \RuntimeException(
                        "Stock insuficiente: {$vName} ({$vColor}/{$vSize}) " .
                        "— disponible: $stockBefore, solicitado: $qty"
                    );
                }

                $stockAfter = $stockBefore - $qty;
                $db->prepare('UPDATE product_variants SET stock = ? WHERE id = ?')
                    ->execute([$stockAfter, $variantId]);

                $adminUserId = isset($_SESSION['admin_user_id']) && is_numeric($_SESSION['admin_user_id'])
                    ? (int) $_SESSION['admin_user_id'] : null;
                $db->prepare(
                    'INSERT INTO stock_movements
                        (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $variantId,
                    -$qty,
                    $stockBefore,
                    $stockAfter,
                    'pos',
                    $orderId,
                    "Venta POS - Orden #{$orderId}",
                    $adminUserId,
                ]);

                $priceOverride = $variant['price_override'];
                $unitPrice = $priceOverride !== null
                    ? (is_numeric($priceOverride) ? (float) $priceOverride : 0)
                    : (is_numeric($variant['price'] ?? null) ? (float) $variant['price'] : 0);
                $subtotal = $unitPrice * $qty;
                $total += $subtotal;

                $items[] = [
                    'product_id' => $variant['product_id'],
                    'variant_id' => $variantId,
                    'product_name' => $variant['name'],
                    'product_slug' => $variant['product_slug'] ?? '',
                    'product_price' => $unitPrice,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'selected_color' => $variant['color_name'],
                    'selected_size' => $variant['size_label'],
                ];
            }

            if ($items === []) {
                throw new \RuntimeException('No se pudieron procesar items');
            }

            $settings = $this->getSettings()->getAll();
            $taxRate = (float) ($settings['tax']['rate'] ?? 0) / 100;
            $taxTotal = round($total * $taxRate, 2);
            $grandTotal = round($total + $taxTotal, 2);

            $paymentMethodId = $this->model->resolvePaymentMethodId($paymentMethodCode);
            $statusDelivered = $this->model->resolveStatusId('delivered');
            $paymentCompleted = $this->model->resolvePaymentStatusId('completed');

            $customerName = $this->str($body, 'customer_name', 'Venta en Mostrador');
            $customerEmail = $this->str($body, 'customer_email', 'pos@vunotek.com');

            $storeCurrency = $this->getCurrencyModel()->getStoreCurrency();
            if ($storeCurrency === null) {
                $storeCurrency = ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1.0, 'decimal_places' => 2];
            }
            $currencyCode = $storeCurrency['code'] ?? 'USD';
            $exchangeRate = $storeCurrency['exchange_rate'] ?? 1.0;

            $stmt = $db->prepare(
                'INSERT INTO orders (
                    order_number, customer_name, customer_email,
                    subtotal, shipping_total, tax_total, discount_total, total, origin, currency, exchange_rate,
                    status_id, payment_method_id, payment_status_id,
                    notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $orderId,
                $customerName,
                $customerEmail,
                $total,
                0, $taxTotal, 0,
                $grandTotal,
                'pos',
                $currencyCode,
                $exchangeRate,
                $statusDelivered,
                $paymentMethodId,
                $paymentCompleted,
                "Venta POS - Cajero: " . (is_string($_SESSION['admin_email'] ?? null) ? $_SESSION['admin_email'] : ''),
            ]);
            $orderDbId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, variant_id, product_name, product_slug, product_price,
                     quantity, unit_price, subtotal, selected_color, selected_size)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    $orderDbId,
                    $it['product_id'],
                    $it['variant_id'],
                    $it['product_name'],
                    $it['product_slug'],
                    $it['product_price'],
                    $it['quantity'],
                    $it['unit_price'],
                    $it['subtotal'],
                    $it['selected_color'],
                    $it['selected_size'],
                ]);
            }

            $adminUserId = isset($_SESSION['admin_user_id']) && is_numeric($_SESSION['admin_user_id'])
                ? (int) $_SESSION['admin_user_id'] : null;
            $this->model->logStatusHistory($orderDbId, null, $statusDelivered, $adminUserId, 'POS order created — delivered at sale');

            $db->commit();

            $this->getAuth()->logAction('create', 'order', $orderId, "POS sale #{$orderId} — " . number_format($grandTotal, 2) . " USD", [
                'payment_method' => $paymentMethodCode,
                'total' => $grandTotal,
                'subtotal' => $total,
                'tax' => $taxTotal,
                'items_count' => count($items),
                'origin' => 'pos',
            ]);

            $this->jsonResponse([
                'success' => true,
                'id' => $orderId,
                'total' => $grandTotal,
                'subtotal' => $total,
                'tax' => $taxTotal,
                'items_count' => count($items),
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->jsonError('Error al procesar venta POS: ' . $e->getMessage(), 400);
        }
    }

    public function updateStatus(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->str($body, 'id');
        $status = $this->str($body, 'status');
        if ($id === '' || $status === '') {
            $this->jsonError('Order ID and status required');
        }

        $validStatuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            $this->jsonError('Invalid status');
        }

        $order = $this->model->getById($id);
        if ($order === null) {
            $this->jsonError('Order not found', 404);
        }

        $oldStatus = is_string($order['status'] ?? null) ? $order['status'] : 'unknown';
        $paymentStatus = null;
        if ($status === 'paid') {
            $paymentStatus = 'completed';
        }
        if ($status === 'cancelled') {
            $paymentStatus = 'failed';
            $this->model->restoreStock($id);
        }

        try {
            $this->model->updateStatus($order, $status, $paymentStatus, null);
            $this->getAuth()->logAction('update', 'order', $id, 'Order status changed: ' . $oldStatus . ' → ' . $status, [
                'from_status' => $oldStatus,
                'to_status' => $status,
            ]);

            // Send response to admin client FIRST
            http_response_code(200);
            echo json_encode(['success' => true], \JSON_UNESCAPED_UNICODE);

            // Flush response to client, then send email in background
            if (function_exists('fastcgi_finish_request')) {
                session_write_close();
                fastcgi_finish_request();
            }

            if ($status !== $oldStatus && in_array($status, ['paid', 'shipped', 'delivered'], true)) {
                try {
                    $bankAccounts = $this->getSettings()->getBankAccounts();
                    $this->getEmailService()->sendOrderConfirmation($order, $bankAccounts, $status);
                } catch (\Throwable $e) {
                    error_log('Status change email failed: ' . $e->getMessage());
                }
            }

            exit;
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 404);
        }
    }

    public function confirmPayment(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $orderId = $this->str($body, 'id');
        $piId = $this->str($body, 'stripePaymentIntentId');
        if ($orderId === '' || $piId === '') {
            $this->jsonError('Missing order ID or payment intent ID');
        }

        try {
            $order = $this->model->getById($orderId);
            if ($order === null) {
                $this->jsonError('Order not found', 404);
            }

            if (is_string($order['status'] ?? null) && $order['status'] === 'paid') {
                $this->jsonResponse(['success' => true, 'id' => $orderId, 'alreadyPaid' => true]);
            }

            $this->model->updateStatus($order, 'paid', 'completed', $piId);
            $this->model->deductStock($orderId);

            $bankAccounts = $this->getSettings()->getBankAccounts();
            $this->getEmailService()->sendOrderConfirmation($order, $bankAccounts);
            $this->getEmailService()->sendNewOrderNotification($order);

            $this->jsonResponse(['success' => true, 'id' => $orderId]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to confirm payment: ' . $e->getMessage(), 500);
        }
    }

    public function updateReceipt(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = $this->str($body, 'id');
        $transferReceipt = $this->str($body, 'transferReceipt');
        if ($id === '' || $transferReceipt === '') {
            $this->jsonError('Order ID and transferReceipt URL required');
        }

        $order = $this->model->getById($id);
        if ($order === null) {
            $this->jsonError('Order not found', 404);
        }

        $db = $this->model->getDb();
        $stmt = $db->prepare('UPDATE orders SET transfer_receipt_url = ? WHERE order_number = ?');
        $stmt->execute([$transferReceipt, $id]);

        $this->jsonResponse(['success' => true, 'id' => $id]);
    }

    // =========================================================================
    //  Dashboard Stats
    // =========================================================================

    public function stats(): void
    {
        try {
            \startAdminSession();
            if (!\isAdminLoggedIn()) {
                $this->jsonError('Unauthorized', 401);
            }

            $data = $this->model->getDashboardStats();
            $this->jsonResponse($data);
        } catch (\Throwable $e) {
            $this->jsonError('Error loading dashboard stats: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    //  Private helpers
    // =========================================================================

    /**
     * Save order with coupon handling, items, and status history.
     * @param array<string, mixed> $order
     */
    private function saveOrder(array $order, OrderModel $model): void
    {
        $couponId = null;

        /** @var mixed $rawDiscount */
        $rawDiscount = $order['discountTotal'] ?? null;
        $discountTotal = is_numeric($rawDiscount) ? (float) $rawDiscount : 0;

        /** @var mixed $rawCouponCode */
        $rawCouponCode = $order['couponCode'] ?? null;
        if (is_string($rawCouponCode) && $rawCouponCode !== '') {
            $coupon = $this->couponModel->getByCode($rawCouponCode);
            if ($coupon !== null) {
                $couponId = isset($coupon['id']) && is_numeric($coupon['id']) ? (int) $coupon['id'] : null;
            }
        }

        /** @var array<string, mixed> $customer */
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];

        /** @var mixed $rawCustomerId */
        $rawCustomerId = $order['customerId'] ?? null;
        $customerId = is_numeric($rawCustomerId) ? (int) $rawCustomerId : null;

        $order['couponId'] = $couponId;
        $order['customer'] = $customer;

        $orderDbId = $model->insertOrder($order);

        /** @var array<int, array<string, mixed>> $items */
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $model->saveItems($orderDbId, $items);

        // Record coupon usage
        $customerEmail = is_string($customer['email'] ?? null) ? $customer['email'] : '';
        if ($couponId !== null && $discountTotal > 0 && $customerEmail !== '') {
            try {
                /** @var mixed $rawOrderId */
                $rawOrderId = $order['id'] ?? null;
                $orderIdStr = is_string($rawOrderId) ? $rawOrderId : '';
                if ($orderIdStr !== '') {
                    $orderDbIdForCoupon = $this->couponModel->findOrderDbIdByOrderNumber($orderIdStr);
                    if ($orderDbIdForCoupon !== null) {
                        $this->couponModel->recordUsage($couponId, $orderDbIdForCoupon, $customerEmail, $discountTotal);
                    }
                }
            } catch (\Throwable $e) {
                error_log('Failed to record coupon usage: ' . $e->getMessage());
            }
        }

        // Log status history
        /** @var mixed $rawStatus */
        $rawStatus = $order['status'] ?? null;
        $statusCode = is_string($rawStatus) ? $rawStatus : 'pending';
        $statusId = $model->resolveStatusId($statusCode);
        $model->logStatusHistory($orderDbId, null, $statusId, null, 'Order created via checkout');

        // Update customer's last_order_at
        if ($customerId !== null) {
            $model->updateCustomerLastOrderAt($customerId);
        }

        /** @var mixed $rawOrderId2 */
        $rawOrderId2 = $order['id'] ?? null;
        $deductId = is_string($rawOrderId2) ? $rawOrderId2 : '';
        if ($deductId !== '') {
            $model->deductStock($deductId);
        }
    }
}
