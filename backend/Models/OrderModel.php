<?php
declare(strict_types=1);

namespace App\Models;

final class OrderModel
{
    private ?CurrencyModel $currencyModel = null;

    public function __construct(private \PDO $db) {}

    public function setCurrencyModel(?CurrencyModel $m): void
    {
        $this->currencyModel = $m;
    }

    private function getCurrencyModel(): CurrencyModel
    {
        if ($this->currencyModel === null) {
            $this->currencyModel = new CurrencyModel($this->db);
        }
        return $this->currencyModel;
    }

    public function getDb(): \PDO
    {
        return $this->db;
    }

    // =========================================================================
    //  Read
    // =========================================================================

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getAll(?int $limit, ?int $offset, ?string $search, ?string $status): array
    {
        $countSql = 'SELECT COUNT(*)
            FROM orders o
            JOIN order_statuses os ON os.id = o.status_id';
        $sql = 'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
            FROM orders o
            JOIN order_statuses os ON os.id = o.status_id
            JOIN payment_methods pm ON pm.id = o.payment_method_id
            JOIN payment_statuses ps ON ps.id = o.payment_status_id';
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== null && $status !== '') {
            $conditions[] = 'os.code = ?';
            $params[] = $status;
        }

        if ($conditions !== []) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
            $countSql .= $where;
            $sql .= $where;
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        /** @var int $total */
        $total = (int) $countStmt->fetchColumn();

        $sql .= ' ORDER BY o.created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->buildOrder($row);
        }
        return ['items' => $items, 'total' => $total];
    }

    /** @return ?array<string, mixed> */
    public function getById(string $id): ?array
    {
        if (ctype_digit($id)) {
            $stmt = $this->db->prepare(
                'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
                 FROM orders o
                 JOIN order_statuses os ON os.id = o.status_id
                 JOIN payment_methods pm ON pm.id = o.payment_method_id
                 JOIN payment_statuses ps ON ps.id = o.payment_status_id
                 WHERE o.id = ? OR o.order_number = ?'
            );
            $stmt->execute([(int) $id, $id]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
                 FROM orders o
                 JOIN order_statuses os ON os.id = o.status_id
                 JOIN payment_methods pm ON pm.id = o.payment_method_id
                 JOIN payment_statuses ps ON ps.id = o.payment_status_id
                 WHERE o.order_number = ?'
            );
            $stmt->execute([$id]);
        }
        /** @var ?array<string, mixed> $row */
        $row = $stmt->fetch();
        if ($row === false || $row === null) {
            return null;
        }
        return $this->buildOrder($row);
    }

    /** @return ?array<string, mixed> */
    public function getByStripeIntentId(string $intentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
             FROM orders o
             JOIN order_statuses os ON os.id = o.status_id
             JOIN payment_methods pm ON pm.id = o.payment_method_id
             JOIN payment_statuses ps ON ps.id = o.payment_status_id
             WHERE o.stripe_payment_intent_id = ?'
        );
        $stmt->execute([$intentId]);
        /** @var ?array<string, mixed> $row */
        $row = $stmt->fetch();
        if ($row === false || $row === null) {
            return null;
        }
        return $this->buildOrder($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function buildOrder(array $row): array
    {
        $orderDbId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;

        $itemStmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemStmt->execute([$orderDbId]);
        /** @var array<int, array{unit_price: string, product_price: string, product_image: ?string, product_currency: string, product_id: string, product_name: string, product_slug: string, selected_color: string, selected_size: string, quantity: string}> $itemRows */
        $itemRows = $itemStmt->fetchAll();

        $exchangeRate = is_numeric($row['exchange_rate'] ?? null) ? (float) $row['exchange_rate'] : 1.0;

        $items = [];
        foreach ($itemRows as $ir) {
            $unitPrice = (float) $ir['unit_price'];
            $items[] = [
                'product' => [
                    'id' => $ir['product_id'],
                    'name' => $ir['product_name'],
                    'slug' => $ir['product_slug'],
                    'price' => (float) $ir['product_price'],
                    'currency' => $ir['product_currency'] ?: 'USD',
                    'images' => $ir['product_image'] !== null ? [$ir['product_image']] : [],
                    'display_price' => round($unitPrice * $exchangeRate, 2),
                ],
                'quantity' => (int) $ir['quantity'],
                'selectedColor' => $ir['selected_color'],
                'selectedSize' => $ir['selected_size'],
            ];
        }

        $baseOrder = [
            'db_id' => $orderDbId,
            'id' => is_string($row['order_number'] ?? null) ? $row['order_number'] : '',
            'items' => $items,
            'subtotal' => is_numeric($row['subtotal'] ?? null) ? (float) $row['subtotal'] : 0,
            'shipping' => is_numeric($row['shipping_total'] ?? null) ? (float) $row['shipping_total'] : 0,
            'tax' => is_numeric($row['tax_total'] ?? null) ? (float) $row['tax_total'] : 0,
            'discountTotal' => is_numeric($row['discount_total'] ?? null) ? (float) $row['discount_total'] : 0,
            'total' => is_numeric($row['total'] ?? null) ? (float) $row['total'] : 0,
            'currency' => is_string($row['currency'] ?? null) ? $row['currency'] : 'USD',
            'exchange_rate' => is_numeric($row['exchange_rate'] ?? null) ? (float) $row['exchange_rate'] : 1.0,
            'origin' => is_string($row['origin'] ?? null) ? $row['origin'] : 'online',
            'status' => is_string($row['status_code'] ?? null) ? $row['status_code'] : 'pending',
            'paymentMethod' => is_string($row['payment_method_code'] ?? null) ? $row['payment_method_code'] : 'stripe',
            'paymentStatus' => is_string($row['payment_status_code'] ?? null) ? $row['payment_status_code'] : 'pending',
            'transferReceipt' => $row['transfer_receipt_url'] ?? null,
            'selectedBankId' => isset($row['selected_bank_id']) && is_numeric($row['selected_bank_id']) ? (int) $row['selected_bank_id'] : null,
            'selectedBankName' => '',
            'customer' => [
                'name' => is_string($row['customer_name'] ?? null) ? $row['customer_name'] : '',
                'email' => is_string($row['customer_email'] ?? null) ? $row['customer_email'] : '',
                'phone' => is_string($row['customer_phone'] ?? null) ? $row['customer_phone'] : '',
                'address' => is_string($row['shipping_line1'] ?? null) ? $row['shipping_line1'] : '',
                'city' => is_string($row['shipping_city'] ?? null) ? $row['shipping_city'] : '',
                'state' => is_string($row['shipping_state'] ?? null) ? $row['shipping_state'] : '',
                'zip' => is_string($row['shipping_zip'] ?? null) ? $row['shipping_zip'] : '',
                'country' => is_string($row['shipping_country'] ?? null) ? $row['shipping_country'] : '',
            ],
            'createdAt' => date('c', strtotime(is_string($row['created_at'] ?? null) ? $row['created_at'] : 'now') ?: null),
        ];

        // Resolve bank name if selected_bank_id is set
        $selectedBankId = $baseOrder['selectedBankId'];
        if ($selectedBankId !== null) {
            try {
                $bnStmt = $this->db->prepare('SELECT bank_name FROM bank_accounts WHERE id = ?');
                $bnStmt->execute([$selectedBankId]);
                /** @var string|false $bankName */
                $bankName = $bnStmt->fetchColumn();
                if ($bankName !== false) {
                    $baseOrder['selectedBankName'] = $bankName;
                }
            } catch (\PDOException) {
            }
        }

        return $this->getCurrencyModel()->addDisplayPricesToOrder($baseOrder);
    }

    // =========================================================================
    //  FK helpers
    // =========================================================================

    public function resolveStatusId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM order_statuses WHERE code = ?');
        $stmt->execute([$code]);
        /** @var string|false $id */
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("Unknown order status: $code");
        }
        return (int) $id;
    }

    public function resolvePaymentMethodId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM payment_methods WHERE code = ?');
        $stmt->execute([$code]);
        /** @var string|false $id */
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("Unknown payment method: $code");
        }
        return (int) $id;
    }

    public function resolvePaymentStatusId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM payment_statuses WHERE code = ?');
        $stmt->execute([$code]);
        /** @var string|false $id */
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("Unknown payment status: $code");
        }
        return (int) $id;
    }

    // =========================================================================
    //  Write
    // =========================================================================

    /**
     * Insert order row. Returns the auto-increment DB id.
     * @param array<string, mixed> $order
     */
    public function insertOrder(array $order): int
    {
        $orderStatus = is_string($order['status'] ?? null) ? $order['status'] : 'pending';
        $orderPaymentMethod = is_string($order['paymentMethod'] ?? null) ? $order['paymentMethod'] : 'stripe';
        $orderPaymentStatus = is_string($order['paymentStatus'] ?? null) ? $order['paymentStatus'] : 'pending';
        $statusId = $this->resolveStatusId($orderStatus);
        $paymentMethodId = $this->resolvePaymentMethodId($orderPaymentMethod);
        $paymentStatusId = $this->resolvePaymentStatusId($orderPaymentStatus);

        /** @var array<string, mixed> $customer */
        $customer = $order['customer'] ?? [];

        $stmt = $this->db->prepare(
            'INSERT INTO orders (
                order_number, customer_id, customer_name, customer_email, customer_phone,
                shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
                subtotal, shipping_total, tax_total, discount_total, total, origin, currency, exchange_rate,
                status_id, payment_method_id, payment_status_id,
                stripe_payment_intent_id, transfer_receipt_url, selected_bank_id, coupon_id,
                notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $order['id'] ?? '',
            isset($order['customerId']) && is_numeric($order['customerId']) ? (int) $order['customerId'] : null,
            $customer['name'] ?? '',
            $customer['email'] ?? '',
            $customer['phone'] ?? '',
            $customer['address'] ?? '',
            $customer['city'] ?? '',
            $customer['state'] ?? '',
            $customer['zip'] ?? '',
            $customer['country'] ?? '',
            $order['subtotal'] ?? 0,
            $order['shipping'] ?? 0,
            $order['tax'] ?? 0,
            $order['discountTotal'] ?? 0,
            $order['total'] ?? 0,
            $order['origin'] ?? 'online',
            $order['currency'] ?? 'USD',
            $order['exchange_rate'] ?? 1.0,
            $statusId,
            $paymentMethodId,
            $paymentStatusId,
            $order['stripePaymentIntentId'] ?? null,
            $order['transferReceipt'] ?? null,
            $order['selectedBankId'] ?? null,
            $order['couponId'] ?? null,
            $order['notes'] ?? null,
            date('Y-m-d H:i:s', strtotime(is_string($order['createdAt'] ?? null) ? $order['createdAt'] : 'now') ?: null),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Insert order items.
     * @param array<int, array<string, mixed>> $items
     */
    public function saveItems(int $orderDbId, array $items): void
    {
        if ($items === []) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, product_slug, product_image, product_price, product_currency, quantity, unit_price, subtotal, selected_color, selected_size)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            /** @var array<string, mixed> $prod */
            $prod = is_array($item['product'] ?? null) ? $item['product'] : [];
            $qty = is_numeric($item['quantity'] ?? null) ? (int) $item['quantity'] : 1;
            $unitPrice = is_numeric($item['price'] ?? null) ? (float) $item['price'] : (is_numeric($prod['price'] ?? null) ? (float) $prod['price'] : 0);
            $productId = is_string($item['product_id'] ?? null) ? $item['product_id'] : (is_string($prod['id'] ?? null) ? $prod['id'] : '');
            $productName = is_string($item['product_name'] ?? null) ? $item['product_name'] : (is_string($prod['name'] ?? null) ? $prod['name'] : '');
            $productSlug = is_string($item['product_slug'] ?? null) ? $item['product_slug'] : (is_string($prod['slug'] ?? null) ? $prod['slug'] : '');
            $stmt->execute([
                $orderDbId,
                $productId,
                $productName,
                $productSlug,
                is_array($prod['images'] ?? null) && isset($prod['images'][0]) && is_string($prod['images'][0]) ? $prod['images'][0] : null,
                $unitPrice,
                is_string($item['currency'] ?? null) ? $item['currency'] : (is_string($prod['currency'] ?? null) ? $prod['currency'] : 'USD'),
                $qty,
                $unitPrice,
                $unitPrice * $qty,
                is_string($item['selectedColor'] ?? null) ? $item['selectedColor'] : '',
                is_string($item['selectedSize'] ?? null) ? $item['selectedSize'] : '',
            ]);
        }
    }

    public function logStatusHistory(int $orderDbId, ?int $fromStatusId, int $toStatusId, ?int $changedBy, string $notes = ''): void
    {
        $this->db->prepare(
            'INSERT INTO order_status_history (order_id, from_status_id, to_status_id, changed_by, notes) VALUES (?, ?, ?, ?, ?)'
        )->execute([$orderDbId, $fromStatusId, $toStatusId, $changedBy, $notes]);
    }

    public function updateCustomerLastOrderAt(int $customerId): void
    {
        $this->db->prepare('UPDATE customers SET last_order_at = NOW() WHERE id = ?')->execute([$customerId]);
    }

    /**
     * @param array<string, mixed> $order
     */
    public function updateStatus(array $order, string $status, ?string $paymentStatus, ?string $stripePaymentIntentId): void
    {
        $dbId = is_numeric($order['db_id'] ?? null) ? (int) $order['db_id'] : 0;
        if ($dbId === 0) {
            throw new \RuntimeException('Order not found (no db_id)');
        }

        $newStatusId = $this->resolveStatusId($status);
        $oldStatusId = $this->resolveStatusId(is_string($order['status'] ?? null) ? $order['status'] : 'unknown');

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE orders SET status_id = ? WHERE id = ?')->execute([$newStatusId, $dbId]);

            if ($paymentStatus !== null) {
                $psId = $this->resolvePaymentStatusId($paymentStatus);
                $this->db->prepare('UPDATE orders SET payment_status_id = ? WHERE id = ?')->execute([$psId, $dbId]);
            }

            if ($stripePaymentIntentId !== null) {
                $this->db->prepare('UPDATE orders SET stripe_payment_intent_id = ? WHERE id = ?')
                    ->execute([$stripePaymentIntentId, $dbId]);
            }

            switch ($status) {
                case 'paid':
                    $this->db->prepare('UPDATE orders SET paid_at = NOW() WHERE id = ?')->execute([$dbId]);
                    break;
                case 'shipped':
                    $this->db->prepare('UPDATE orders SET shipped_at = NOW() WHERE id = ?')->execute([$dbId]);
                    break;
                case 'delivered':
                    $this->db->prepare('UPDATE orders SET delivered_at = NOW() WHERE id = ?')->execute([$dbId]);
                    break;
                case 'cancelled':
                    $this->db->prepare('UPDATE orders SET cancelled_at = NOW() WHERE id = ?')->execute([$dbId]);
                    break;
            }

            $adminUserId = isset($_SESSION['admin_user_id']) && is_numeric($_SESSION['admin_user_id'])
                ? (int) $_SESSION['admin_user_id'] : null;
            $this->logStatusHistory($dbId, $oldStatusId, $newStatusId, $adminUserId);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deductStock(string $orderId): void
    {
        $stmt = $this->db->prepare(
            'SELECT oi.product_id, oi.selected_color, oi.selected_size, oi.quantity
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.order_number = ?'
        );
        $stmt->execute([$orderId]);
        /** @var array<int, array{product_id: string, selected_color: string, selected_size: string, quantity: string}> $items */
        $items = $stmt->fetchAll();

        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            $variant = $this->resolveVariant(
                $item['product_id'],
                $item['selected_color'],
                $item['selected_size'],
            );
            if ($variant === null) {
                $prodId = $item['product_id'];
                $color = $item['selected_color'];
                $size = $item['selected_size'];
                error_log("[Stock] Variant not found for product {$prodId}, color '{$color}', size '{$size}'");
                continue;
            }

            $qty = (int) $item['quantity'];
            $stockBefore = $variant['stock'];
            $stockAfter = max(0, $stockBefore - $qty);

            $this->db->prepare('UPDATE product_variants SET stock = ? WHERE id = ?')
                ->execute([$stockAfter, $variant['id']]);

            $this->db->prepare(
                'INSERT INTO stock_movements (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$variant['id'], -$qty, $stockBefore, $stockAfter, 'order', $orderId, 'Order payment confirmed']);
        }
    }

    public function restoreStock(string $orderId): void
    {
        $stmt = $this->db->prepare(
            'SELECT oi.product_id, oi.selected_color, oi.selected_size, oi.quantity
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.order_number = ?'
        );
        $stmt->execute([$orderId]);
        /** @var array<int, array{product_id: string, selected_color: string, selected_size: string, quantity: string}> $items */
        $items = $stmt->fetchAll();

        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            $variant = $this->resolveVariant(
                $item['product_id'],
                $item['selected_color'],
                $item['selected_size'],
            );
            if ($variant === null) {
                $prodId = $item['product_id'];
                $color = $item['selected_color'];
                $size = $item['selected_size'];
                error_log("[Stock] Variant not found for product {$prodId}, color '{$color}', size '{$size}'");
                continue;
            }

            $qty = (int) $item['quantity'];
            $stockBefore = $variant['stock'];
            $stockAfter = $stockBefore + $qty;

            $this->db->prepare('UPDATE product_variants SET stock = ? WHERE id = ?')
                ->execute([$stockAfter, $variant['id']]);

            $this->db->prepare(
                'INSERT INTO stock_movements (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$variant['id'], $qty, $stockBefore, $stockAfter, 'cancellation', $orderId, 'Order cancelled — stock restored']);
        }
    }

    /**
     * @return ?array{id: int, stock: int}
     */
    public function resolveVariant(string $productId, string $colorName, string $sizeLabel): ?array
    {
        if ($productId === '' || $colorName === '' || $sizeLabel === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT pv.id, pv.stock
             FROM product_variants pv
             JOIN product_colors pc ON pc.id = pv.color_id AND pc.product_id = pv.product_id
             JOIN product_sizes ps ON ps.id = pv.size_id AND ps.product_id = pv.product_id
             WHERE pv.product_id = ? AND pc.name = ? AND ps.label = ?
             LIMIT 1'
        );
        $stmt->execute([$productId, $colorName, $sizeLabel]);
        /** @var ?array{id: int, stock: int} $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    // =========================================================================
    //  Dashboard
    // =========================================================================

    /** @return array<string, mixed> */
    public function getDashboardStats(): array
    {
        try {
            $totalStmt = $this->db->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL');
            $totalProducts = $totalStmt !== false ? (int) $totalStmt->fetchColumn() : 0;
            $totalOrderStmt = $this->db->query('SELECT COUNT(*) FROM orders');
            $totalOrders = $totalOrderStmt !== false ? (int) $totalOrderStmt->fetchColumn() : 0;

            $monthStart = date('Y-m-01 00:00:00');
            $monthEnd = date('Y-m-t 23:59:59');

            $paidStmt = $this->db->query("SELECT id FROM order_statuses WHERE code = 'paid'");
            /** @var string|false $paidStatusId */
            $paidStatusId = $paidStmt !== false ? $paidStmt->fetchColumn() : false;

            $monthlyData = ['revenue' => 0, 'cnt' => 0];
            if ($paidStatusId !== false) {
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS revenue FROM orders WHERE status_id = ? AND created_at BETWEEN ? AND ?'
                );
                $stmt->execute([(int) $paidStatusId, $monthStart, $monthEnd]);
                /** @var array{revenue: numeric-string, cnt: numeric-string} $md */
                $md = $stmt->fetch();
                if ($md !== false) {
                    $monthlyData = $md;
                }
            }

            /** @var array<int, array<string, mixed>> $recentOrders */
            $recentOrders = [];
            $recentStmt = $this->db->query(
                'SELECT o.order_number, o.customer_name, o.total, o.exchange_rate, o.currency, os.code AS status_code, o.created_at, pm.code AS payment_method_code
                 FROM orders o
                 JOIN order_statuses os ON os.id = o.status_id
                 JOIN payment_methods pm ON pm.id = o.payment_method_id
                 ORDER BY o.created_at DESC LIMIT 5'
            );
            if ($recentStmt !== false) {
                $recentOrders = $recentStmt->fetchAll();
            }

            $statusCounts = [];
            $scStmt = $this->db->query(
                'SELECT os.code, COUNT(*) AS cnt
                 FROM orders o JOIN order_statuses os ON os.id = o.status_id
                 GROUP BY os.code'
            );
            if ($scStmt !== false) {
                /** @var array<int, array{code: string, cnt: numeric-string}> $scRows */
                $scRows = $scStmt->fetchAll();
                foreach ($scRows as $sc) {
                    $statusCounts[$sc['code']] = (int) $sc['cnt'];
                }
            }

            /** @var array<int, array{id: string, name: string}> $lowStockRows */
            $lowStockRows = [];
            $lsStmt = $this->db->query(
                'SELECT DISTINCT p.id, p.name
                 FROM products p
                 JOIN product_variants pv ON pv.product_id = p.id
                 WHERE p.deleted_at IS NULL AND pv.stock <= 1 AND pv.is_active = 1
                 ORDER BY p.name'
            );
            if ($lsStmt !== false) {
                $lowStockRows = $lsStmt->fetchAll();
            }

            $monthlyRevenue = (float) ($monthlyData['revenue'] ?? 0);

            $stmt3 = $this->db->prepare('SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?');
            $stmt3->execute([$monthStart, $monthEnd]);
            $monthlyOrderCount = (int) $stmt3->fetchColumn();

            $storeCurrency = $this->getCurrencyModel()->getStoreCurrency();
            if ($storeCurrency === null) {
                $storeCurrency = ['code' => 'NIO', 'symbol' => 'C$', 'exchange_rate' => 37.0];
            }
            $storeRate = (float) ($storeCurrency['exchange_rate'] ?? 37.0);
            $storeSymbol = $storeCurrency['symbol'] ?? 'C$';
            $displayMonthlyRevenue = round($monthlyRevenue * $storeRate, 2);

            $statusCountsSafe = [];
            foreach ($statusCounts as $k => $v) {
                $statusCountsSafe[is_string($k) ? $k : (string) $k] = $v;
            }

            $lowStockProducts = array_map(fn(array $p): array => [
                'id' => is_string($p['id'] ?? null) ? $p['id'] : '',
                'name' => is_string($p['name'] ?? null) ? $p['name'] : '',
            ], $lowStockRows);

            $recentItems = array_map(function (array $r) use ($storeSymbol, $storeRate): array {
                $total = (float) ($r['total'] ?? 0);
                return [
                    'id' => is_string($r['order_number'] ?? null) ? $r['order_number'] : '',
                    'customerName' => is_string($r['customer_name'] ?? null) ? $r['customer_name'] : '',
                    'total' => $total,
                    'displayTotal' => round($total * $storeRate, 2),
                    'displaySymbol' => $storeSymbol,
                    'status' => $r['status_code'] ?? '',
                    'createdAt' => date('c', strtotime(is_string($r['created_at'] ?? null) ? $r['created_at'] : 'now') ?: null),
                    'paymentMethod' => $r['payment_method_code'] ?? '',
                ];
            }, $recentOrders);

            return [
                'totalProducts' => $totalProducts,
                'totalOrders' => $totalOrders,
                'monthlyRevenue' => $monthlyRevenue,
                'displayMonthlyRevenue' => $displayMonthlyRevenue,
                'displaySymbol' => $storeSymbol,
                'monthlyOrderCount' => $monthlyOrderCount,
                'statusCounts' => $statusCountsSafe,
                'recentOrders' => $recentItems,
                'lowStockProducts' => $lowStockProducts,
            ];
        } catch (\PDOException $e) {
            error_log('Dashboard stats error: ' . $e->getMessage());
            return [
                'totalProducts' => 0,
                'totalOrders' => 0,
                'monthlyRevenue' => 0.0,
                'monthlyOrderCount' => 0,
                'statusCounts' => [],
                'recentOrders' => [],
                'lowStockProducts' => [],
            ];
        }
    }
}
