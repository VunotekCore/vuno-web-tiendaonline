<?php
/**
 * Database Storage - MySQL/PDO CRUD for all entities
 * Replaces the legacy JSON file storage.
 */

require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/imagekit.php';

// =============================================================================
//  Products
// =============================================================================

function getProducts(?int $limit = null, ?int $offset = null, ?string $search = null, ?string $category = null, ?string $lang = null): array
{
    $db = getDb();

    $join = '';
    $where = 'p.deleted_at IS NULL';
    $params = [];

    if ($category) {
        $join = ' JOIN product_categories pc ON pc.product_id = p.id JOIN categories c ON c.id = pc.category_id';
        $where .= ' AND (c.name = ? OR c.slug = ?)';
        $params[] = $category;
        $params[] = $category;
    }

    if ($search) {
        $like = '%' . $search . '%';
        $where .= ' AND p.name LIKE ?';
        $params[] = $like;
    }

    $countSql = "SELECT COUNT(DISTINCT p.id) FROM products p{$join} WHERE {$where}";
    $sql = "SELECT p.id FROM products p{$join} WHERE {$where} ORDER BY p.created_at DESC";

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    if ($limit !== null) {
        $sql .= ' LIMIT ?';
        $params[] = $limit;
    }
    if ($offset !== null) {
        $sql .= ' OFFSET ?';
        $params[] = $offset;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $products = [];
    foreach ($rows as $row) {
        $products[] = buildProduct($row['id'], $lang);
    }
    return ['items' => $products, 'total' => $total];
}

function getProductById(string $id, ?string $lang = null): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return buildProduct($row['id'], $lang);
}

function getProductBySlug(string $slug, ?string $lang = null): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM products WHERE slug = ? AND deleted_at IS NULL');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return buildProduct($row['id'], $lang);
}

function buildProduct(string $id, ?string $lang = null): array
{
    $db = getDb();

    $p = $db->prepare('SELECT * FROM products WHERE id = ?');
    $p->execute([$id]);
    $row = $p->fetch();
    if (!$row) return [];

    // Apply translations if lang is provided and not Spanish
    if ($lang && $lang !== 'es') {
        try {
            $tStmt = $db->prepare('SELECT name, description, details FROM product_translations WHERE product_id = ? AND lang = ?');
            $tStmt->execute([$id, $lang]);
            $trans = $tStmt->fetch();
            if ($trans) {
                if ($trans['name']) $row['name'] = $trans['name'];
                if ($trans['description']) $row['description'] = $trans['description'];
                $transDetails = $trans['details'];
            }
        } catch (\PDOException $e) {
            // Translation table not available yet
        }
    }

    // Details
    $det = $db->prepare('SELECT detail_text FROM product_details WHERE product_id = ? ORDER BY sort_order');
    $det->execute([$id]);
    $details = array_column($det->fetchAll(), 'detail_text');

    // Override details with translated JSON if available
    if (isset($transDetails) && $transDetails) {
        $parsed = json_decode($transDetails, true);
        if (is_array($parsed)) {
            $details = $parsed;
        }
    }

    // Images
    $img = $db->prepare(
        'SELECT pi.id, pi.url, pi.file_id, pi.color_id, pc.name AS color_name
         FROM product_images pi
         LEFT JOIN product_colors pc ON pc.id = pi.color_id
         WHERE pi.product_id = ?
         ORDER BY pi.sort_order'
    );
    $img->execute([$id]);
    $imageRows = $img->fetchAll();
    $images = [];          // Global images only (no color)
    $imageDetails = [];
    $imagesByColor = [];   // color_name → url[]
    foreach ($imageRows as $r) {
        $colorName = $r['color_name'] ?? null;
        $url = $r['url'];
        if ($colorName) {
            if (!isset($imagesByColor[$colorName])) $imagesByColor[$colorName] = [];
            $imagesByColor[$colorName][] = $url;
        } else {
            $images[] = $url;
        }
        $imageDetails[] = [
            'id'        => (int)$r['id'],
            'url'       => $url,
            'fileId'    => $r['file_id'] ?? '',
            'colorName' => $colorName,
        ];
    }

    // Categories (first one as string for backward compat)
    $catStmt = $db->prepare(
        'SELECT c.id FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = ? LIMIT 1'
    );
    $catStmt->execute([$id]);
    $catId = $catStmt->fetchColumn() ?: '';

    $category = $catId;
    if ($catId) {
        if ($lang && $lang !== 'es') {
            try {
                $ctStmt = $db->prepare('SELECT name FROM category_translations WHERE category_id = ? AND lang = ?');
                $ctStmt->execute([$catId, $lang]);
                $ctName = $ctStmt->fetchColumn();
                $category = $ctName ?: $catId;
            } catch (\PDOException $e) {
                // Translation table not available yet
            }
        } else {
            $nStmt = $db->prepare('SELECT name FROM categories WHERE id = ?');
            $nStmt->execute([$catId]);
            $category = $nStmt->fetchColumn() ?: $catId;
        }
    }

    // Colors
    $col = $db->prepare('SELECT id, name, hex, sort_order FROM product_colors WHERE product_id = ? ORDER BY sort_order');
    $col->execute([$id]);
    $colors = $col->fetchAll();
    $colorsArr = array_map(fn($c) => ['name' => $c['name'], 'hex' => $c['hex']], $colors);

    // Sizes  (inStock = any variant with stock > 0)
    $siz = $db->prepare('SELECT id, label, value, sort_order FROM product_sizes WHERE product_id = ? ORDER BY sort_order');
    $siz->execute([$id]);
    $sizes = $siz->fetchAll();

    // Variants (for inStock calculation)
    $var = $db->prepare('SELECT color_id, size_id, stock FROM product_variants WHERE product_id = ? AND is_active = 1');
    $var->execute([$id]);
    $variants = $var->fetchAll();

    $sizeStockMap = [];
    $totalStock = 0;
    foreach ($variants as $v) {
        $sizeId = $v['size_id'];
        $stock = (int)$v['stock'];
        if (!isset($sizeStockMap[$sizeId])) $sizeStockMap[$sizeId] = 0;
        $sizeStockMap[$sizeId] += $stock;
        $totalStock += $stock;
    }

    // Build variant stock matrix for admin
    $var2 = $db->prepare(
        'SELECT pc.name AS color_name, ps.value AS size_value, pv.stock
         FROM product_variants pv
         JOIN product_colors pc ON pc.id = pv.color_id
         JOIN product_sizes ps ON ps.id = pv.size_id
         WHERE pv.product_id = ? AND pv.is_active = 1
         ORDER BY ps.sort_order, pc.sort_order'
    );
    $var2->execute([$id]);
    $variantRows = $var2->fetchAll();

    $sizesArr = array_map(function ($s) use ($sizeStockMap) {
        return [
            'label' => $s['label'],
            'value' => $s['value'],
            'inStock' => ($sizeStockMap[$s['id']] ?? 0) > 0,
            'stock'  => $sizeStockMap[$s['id']] ?? 0,
        ];
    }, $sizes);

    $result = [
        'id'          => $row['id'],
        'name'        => $row['name'],
        'slug'        => $row['slug'],
        'description' => $row['description'],
        'details'     => $details ?: null,
        'price'       => (float)$row['price'],
        'currency'    => $row['currency'] ?: 'USD',
        'size_prefix' => $row['size_prefix'] ?? 'EU',
        'images'       => $images,
        'imageDetails' => $imageDetails,
        'imagesByColor' => $imagesByColor,
        'category'     => $category,
        'colors'      => $colorsArr,
        'sizes'       => $sizesArr,
        'variants'    => $variantRows,
        'totalStock'  => $totalStock,
        'lowStockThreshold' => isset($row['low_stock_threshold']) ? (int)$row['low_stock_threshold'] : 5,
        'createdAt'   => date('c', strtotime($row['created_at'])),
        'isFeatured'  => (bool)($row['is_featured'] ?? false),
    ];

    return addDisplayPricesToProduct($result);
}

function saveProduct(array $product): void
{
    $db = getDb();
    $db->beginTransaction();
    try {
    // Upsert product
    $lowStockThreshold = isset($product['lowStockThreshold'])
        ? max(0, min(99, (int)$product['lowStockThreshold']))
        : 5;
    $isFeatured = !empty($product['isFeatured']) ? 1 : 0;
    $stmt = $db->prepare(
        'INSERT INTO products (id, name, slug, description, price, currency, size_prefix, low_stock_threshold, is_featured, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug),
         description = VALUES(description), price = VALUES(price), currency = VALUES(currency),
         size_prefix = VALUES(size_prefix), low_stock_threshold = VALUES(low_stock_threshold), is_featured = VALUES(is_featured)'
    );
    $createdAt = $product['createdAt'] ?? date('c');
    $stmt->execute([
        $product['id'],
        $product['name'],
        $product['slug'],
        $product['description'] ?? '',
        $product['price'],
        $product['currency'] ?? 'USD',
        $product['size_prefix'] ?? 'EU',
        $lowStockThreshold,
        $isFeatured,
        date('Y-m-d H:i:s', strtotime($createdAt)),
    ]);

        // Details: delete all, re-insert
        $db->prepare('DELETE FROM product_details WHERE product_id = ?')->execute([$product['id']]);
        if (!empty($product['details']) && is_array($product['details'])) {
            $ins = $db->prepare('INSERT INTO product_details (product_id, detail_text, sort_order) VALUES (?, ?, ?)');
            foreach (array_values($product['details']) as $i => $d) {
                $ins->execute([$product['id'], $d, $i]);
            }
        }

        // Category: if string provided, link to existing category by name
        $db->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$product['id']]);
        if (!empty($product['category'])) {
            $catStmt = $db->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
            $catStmt->execute([$product['category'], slugify($product['category'])]);
            $catId = $catStmt->fetchColumn();
            if ($catId) {
                $db->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)')
                    ->execute([$product['id'], $catId]);
            }
        }

        // Colors: delete all, re-insert
        $db->prepare('DELETE FROM product_colors WHERE product_id = ?')->execute([$product['id']]);
        $colorIdMap = [];
        if (!empty($product['colors']) && is_array($product['colors'])) {
            $ins = $db->prepare('INSERT INTO product_colors (product_id, name, hex, sort_order) VALUES (?, ?, ?, ?)');
            foreach (array_values($product['colors']) as $i => $c) {
                $ins->execute([$product['id'], $c['name'], $c['hex'] ?? '#000000', $i]);
                $colorIdMap[$c['name']] = (int)$db->lastInsertId();
            }
        }

        // Sizes: delete all, re-insert
        $db->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$product['id']]);
        $sizeIdMap = [];
        if (!empty($product['sizes']) && is_array($product['sizes'])) {
            $ins = $db->prepare('INSERT INTO product_sizes (product_id, label, value, sort_order) VALUES (?, ?, ?, ?)');
            foreach (array_values($product['sizes']) as $i => $s) {
                $ins->execute([$product['id'], $s['label'], $s['value'], $i]);
                $sizeIdMap[$s['value']] = (int)$db->lastInsertId();
            }
        }

        // Variants: delete all, re-insert from colors × sizes
        $db->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$product['id']]);
        if ($colorIdMap && $sizeIdMap) {
            $ins = $db->prepare(
                'INSERT INTO product_variants (product_id, color_id, size_id, stock, is_active) VALUES (?, ?, ?, ?, 1)'
            );
            $stocks = $product['stocks'] ?? [];
            foreach ($colorIdMap as $colorName => $colorDbId) {
                foreach ($sizeIdMap as $sizeVal => $sizeDbId) {
                    if ($stocks) {
                        $stockKey = $colorName . '_' . $sizeVal;
                        $stock = isset($stocks[$stockKey]) ? max(0, (int)$stocks[$stockKey]) : 0;
                    } else {
                        // Legacy: check inStock boolean per size
                        $inStock = false;
                        foreach ($product['sizes'] as $s) {
                            if ((string)$s['value'] === (string)$sizeVal && !empty($s['inStock'])) {
                                $inStock = true;
                                break;
                            }
                        }
                        $stock = $inStock ? 1 : 0;
                    }
                    $ins->execute([$product['id'], $colorDbId, $sizeDbId, $stock]);
                }
            }
        }

        // Images: delete all, re-insert
        $db->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$product['id']]);
        if (!empty($product['images']) && is_array($product['images'])) {
            $ins = $db->prepare('INSERT INTO product_images (product_id, url, file_id, sort_order, is_primary, color_id) VALUES (?, ?, ?, ?, ?, ?)');
            $globalPrimarySet = false;
            foreach (array_values($product['images']) as $i => $img) {
                $url = is_string($img) ? $img : ($img['url'] ?? '');
                $fileId = is_string($img) ? null : ($img['fileId'] ?? null);
                $colorName = is_string($img) ? null : ($img['colorName'] ?? null);
                $colorId = $colorName ? ($colorIdMap[$colorName] ?? null) : null;
                $isPrimary = 0;
                if ($colorId === null && !$globalPrimarySet) {
                    $isPrimary = 1;
                    $globalPrimarySet = true;
                }
                $ins->execute([$product['id'], $url, $fileId, $i, $isPrimary, $colorId]);
            }
        }

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function deleteProduct(string $id): void
{
    $db = getDb();

    // Clean up associated images from ImageKit before soft-delete
    $stmt = $db->prepare('SELECT id, file_id FROM product_images WHERE product_id = ? AND file_id IS NOT NULL');
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();

    foreach ($images as $img) {
        try {
            deleteImageKitFile($img['file_id']);
        } catch (\Exception $e) {
            error_log('ImageKit cleanup failed for product ' . $id . ', file ' . $img['file_id'] . ': ' . $e->getMessage());
        }
        $db->prepare('DELETE FROM product_images WHERE id = ?')->execute([$img['id']]);
    }

    $db->prepare('UPDATE products SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
}

// =============================================================================
//  Orders
// =============================================================================

function getOrders(?int $limit = null, ?int $offset = null, ?string $search = null, ?string $status = null): array
{
    $db = getDb();

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

    if ($search) {
        $like = '%' . $search . '%';
        $conditions[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($status) {
        $conditions[] = 'os.code = ?';
        $params[] = $status;
    }

    if ($conditions) {
        $where = ' WHERE ' . implode(' AND ', $conditions);
        $countSql .= $where;
        $sql .= $where;
    }

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql .= ' ORDER BY o.created_at DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ?';
        $params[] = $limit;
    }
    if ($offset !== null) {
        $sql .= ' OFFSET ?';
        $params[] = $offset;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        $orders[] = buildOrder($row);
    }
    return ['items' => $orders, 'total' => $total];
}

function getOrderById(string $id): ?array
{
    $db = getDb();
    // Search by order_number
    $stmt = $db->prepare(
        'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
         FROM orders o
         JOIN order_statuses os ON os.id = o.status_id
         JOIN payment_methods pm ON pm.id = o.payment_method_id
         JOIN payment_statuses ps ON ps.id = o.payment_status_id
         WHERE o.order_number = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return buildOrder($row);
}

function getOrderByStripeIntentId(string $intentId): ?array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT o.*, os.code AS status_code, pm.code AS payment_method_code, ps.code AS payment_status_code
         FROM orders o
         JOIN order_statuses os ON os.id = o.status_id
         JOIN payment_methods pm ON pm.id = o.payment_method_id
         JOIN payment_statuses ps ON ps.id = o.payment_status_id
         WHERE o.stripe_payment_intent_id = ?'
    );
    $stmt->execute([$intentId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return buildOrder($row);
}

function buildOrder(array $row): array
{
    $db = getDb();

    // Items
    $itemStmt = $db->prepare(
        'SELECT * FROM order_items WHERE order_id = ?'
    );
    $itemStmt->execute([$row['id']]);
    $itemRows = $itemStmt->fetchAll();

    $items = [];
    foreach ($itemRows as $ir) {
        $items[] = [
            'product' => [
                'id'       => $ir['product_id'],
                'name'     => $ir['product_name'],
                'slug'     => $ir['product_slug'],
                'price'    => (float)$ir['product_price'],
                'currency' => $ir['product_currency'] ?: 'USD',
                'images'   => $ir['product_image'] ? [$ir['product_image']] : [],
            ],
            'quantity'     => (int)$ir['quantity'],
            'selectedColor' => $ir['selected_color'] ?? '',
            'selectedSize'  => $ir['selected_size'] ?? '',
        ];
    }

    $baseOrder = [
        'id'             => $row['order_number'],
        'items'          => $items,
        'subtotal'       => (float)$row['subtotal'],
        'shipping'       => (float)$row['shipping_total'],
        'tax'            => (float)$row['tax_total'],
        'discountTotal'  => (float)$row['discount_total'],
        'total'          => (float)$row['total'],
        'currency'       => $row['currency'] ?: 'USD',
        'exchange_rate'  => (float)($row['exchange_rate'] ?? 1.0),
        'status'         => $row['status_code'],
        'paymentMethod'  => $row['payment_method_code'],
        'paymentStatus'  => $row['payment_status_code'],
        'transferReceipt'  => $row['transfer_receipt_url'],
        'selectedBankId'  => $row['selected_bank_id'] ? (int)$row['selected_bank_id'] : null,
        'selectedBankName' => !empty($row['selected_bank_id'])
            ? (($bnStmt = $db->prepare('SELECT bank_name FROM bank_accounts WHERE id = ?')) && $bnStmt->execute([(int)$row['selected_bank_id']]) ? ($bnStmt->fetchColumn() ?: '') : '')
            : '',
        'customer'        => [
            'name'    => $row['customer_name'],
            'email'   => $row['customer_email'],
            'phone'   => $row['customer_phone'] ?? '',
            'address' => $row['shipping_line1'] ?? '',
            'city'    => $row['shipping_city'] ?? '',
            'state'   => $row['shipping_state'] ?? '',
            'zip'     => $row['shipping_zip'] ?? '',
            'country' => $row['shipping_country'] ?? '',
        ],
        'createdAt'      => date('c', strtotime($row['created_at'])),
    ];

    return addDisplayPricesToOrder($baseOrder);
}

function saveOrder(array $order): void
{
    $db = getDb();

    // Resolve status/payment FKs
    $statusId = resolveStatusId($order['status'] ?? 'pending');
    $paymentMethodId = resolvePaymentMethodId($order['paymentMethod'] ?? 'stripe');
    $paymentStatusId = resolvePaymentStatusId($order['paymentStatus'] ?? 'pending');

    // Resolve coupon if provided
    $couponId = null;
    $discountTotal = (float)($order['discountTotal'] ?? 0);
    if (!empty($order['couponCode'])) {
        $coupon = getCouponByCode($order['couponCode']);
        if ($coupon) {
            $couponId = $coupon['id'];
        }
    }

    $customer = $order['customer'] ?? [];
    $customerId = !empty($order['customerId']) ? (int)$order['customerId'] : null;

    $stmt = $db->prepare(
        'INSERT INTO orders (
            order_number, customer_id, customer_name, customer_email, customer_phone,
            shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
            subtotal, shipping_total, tax_total, discount_total, total, currency, exchange_rate,
            status_id, payment_method_id, payment_status_id,
            stripe_payment_intent_id, transfer_receipt_url, selected_bank_id, coupon_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $order['id'],
        $customerId,
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
        $discountTotal,
        $order['total'] ?? 0,
        $order['currency'] ?? 'USD',
        $order['exchange_rate'] ?? 1.0,
        $statusId,
        $paymentMethodId,
        $paymentStatusId,
        $order['stripePaymentIntentId'] ?? null,
        $order['transferReceipt'] ?? null,
        $order['selectedBankId'] ?? null,
        $couponId,
        date('Y-m-d H:i:s', strtotime($order['createdAt'] ?? 'now')),
    ]);

    $orderDbId = $db->lastInsertId();

    // Record coupon usage
    if ($couponId && $discountTotal > 0 && !empty($customer['email'])) {
        recordCouponUsage($couponId, $order['id'], $customer['email'], $discountTotal);
    }

    // Items
    if (!empty($order['items'])) {
        $itemStmt = $db->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, product_slug, product_image, product_price, product_currency, quantity, unit_price, subtotal, selected_color, selected_size)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($order['items'] as $item) {
            $prod = $item['product'] ?? [];
            $qty = $item['quantity'] ?? 1;
            $unitPrice = $prod['price'] ?? 0;
            $itemStmt->execute([
                $orderDbId,
                $prod['id'] ?? '',
                $prod['name'] ?? '',
                $prod['slug'] ?? '',
                $prod['images'][0] ?? null,
                $unitPrice,
                $prod['currency'] ?? 'USD',
                $qty,
                $unitPrice,
                $unitPrice * $qty,
                $item['selectedColor'] ?? '',
                $item['selectedSize'] ?? '',
            ]);
        }
    }

    // Log status history
    $db->prepare(
        'INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes) VALUES (?, NULL, ?, ?)'
    )->execute([$orderDbId, $statusId, 'Order created via checkout']);

    // Update customer's last_order_at
    if ($customerId) {
        $db->prepare('UPDATE customers SET last_order_at = NOW() WHERE id = ?')
           ->execute([$customerId]);
    }
}

function updateOrderStatus(string $orderId, string $status, ?string $paymentStatus = null, ?string $stripePaymentIntentId = null): void
{
    $db = getDb();

    // Find order by order_number
    $stmt = $db->prepare('SELECT id, status_id FROM orders WHERE order_number = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new \Exception('Order not found');

    $newStatusId = resolveStatusId($status);
    $oldStatusId = (int)$order['status_id'];

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE orders SET status_id = ? WHERE id = ?')->execute([$newStatusId, $order['id']]);

        if ($paymentStatus) {
            $psId = resolvePaymentStatusId($paymentStatus);
            $db->prepare('UPDATE orders SET payment_status_id = ? WHERE id = ?')->execute([$psId, $order['id']]);
        }

        if ($stripePaymentIntentId) {
            $db->prepare('UPDATE orders SET stripe_payment_intent_id = ? WHERE id = ?')
               ->execute([$stripePaymentIntentId, $order['id']]);
        }

        // Set timestamp based on status
        switch ($status) {
            case 'paid':
                $db->prepare('UPDATE orders SET paid_at = NOW() WHERE id = ?')->execute([$order['id']]);
                break;
            case 'shipped':
                $db->prepare('UPDATE orders SET shipped_at = NOW() WHERE id = ?')->execute([$order['id']]);
                break;
            case 'delivered':
                $db->prepare('UPDATE orders SET delivered_at = NOW() WHERE id = ?')->execute([$order['id']]);
                break;
            case 'cancelled':
                $db->prepare('UPDATE orders SET cancelled_at = NOW() WHERE id = ?')->execute([$order['id']]);
                break;
        }

        // Status history
        $adminId = $_SESSION['admin_user_id'] ?? null;
        $db->prepare(
            'INSERT INTO order_status_history (order_id, from_status_id, to_status_id, changed_by) VALUES (?, ?, ?, ?)'
        )->execute([$order['id'], $oldStatusId, $newStatusId, $adminId]);

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function deductStock(string $orderId): void
{
    $db = getDb();

    $stmt = $db->prepare(
        'SELECT oi.product_id, oi.selected_color, oi.selected_size, oi.quantity
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.order_number = ?'
    );
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    if (empty($items)) return;

    foreach ($items as $item) {
        $variant = resolveVariant($item['product_id'], $item['selected_color'], $item['selected_size']);
        if (!$variant) {
            error_log("[Stock] Variant not found for product {$item['product_id']}, color '{$item['selected_color']}', size '{$item['selected_size']}'");
            continue;
        }

        $qty = (int)$item['quantity'];
        $stockBefore = (int)$variant['stock'];
        $stockAfter = max(0, $stockBefore - $qty);

        $db->prepare('UPDATE product_variants SET stock = ? WHERE id = ?')
           ->execute([$stockAfter, $variant['id']]);

        $db->prepare(
            'INSERT INTO stock_movements (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$variant['id'], -$qty, $stockBefore, $stockAfter, 'order', $orderId, 'Order payment confirmed']);
    }
}

function resolveVariant(string $productId, string $colorName, string $sizeLabel): ?array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT pv.id, pv.stock
         FROM product_variants pv
         JOIN product_colors pc ON pc.id = pv.color_id AND pc.product_id = pv.product_id
         JOIN product_sizes ps ON ps.id = pv.size_id AND ps.product_id = pv.product_id
         WHERE pv.product_id = ? AND pc.name = ? AND ps.label = ?
         LIMIT 1'
    );
    $stmt->execute([$productId, $colorName, $sizeLabel]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// =============================================================================
//  Categories
// =============================================================================

function getCategories(?int $limit = null, ?int $offset = null, ?string $lang = null): array
{
    $db = getDb();

    $total = (int)$db->query('SELECT COUNT(*) FROM categories WHERE is_active = 1')->fetchColumn();

    $sql = 'SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name';
    $params = [];
    if ($limit !== null) {
        $sql .= ' LIMIT ?';
        $params[] = $limit;
    }
    if ($offset !== null) {
        $sql .= ' OFFSET ?';
        $params[] = $offset;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Load translations if needed
    $transMap = [];
    if ($lang && $lang !== 'es') {
        try {
            $tStmt = $db->prepare('SELECT category_id, name FROM category_translations WHERE lang = ?');
            $tStmt->execute([$lang]);
            while ($t = $tStmt->fetch()) {
                $transMap[$t['category_id']] = $t['name'];
            }
        } catch (\PDOException $e) {
            // Translation table not available yet
        }
    }

    return [
        'items' => array_map(function($r) use ($transMap) {
            return [
                'id'   => $r['id'],
                'name' => $transMap[$r['id']] ?? $r['name'],
                'slug' => $r['slug'],
            ];
        }, $rows),
        'total' => $total,
    ];
}

function getCategoryById(string $id): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id'   => $row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
    ];
}

function saveCategory(array $category): void
{
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO categories (id, name, slug) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)'
    );
    $stmt->execute([$category['id'], $category['name'], $category['slug']]);
}

function deleteCategory(string $id): void
{
    $db = getDb();
    $db->prepare('UPDATE categories SET is_active = 0 WHERE id = ?')->execute([$id]);
}

// =============================================================================
//  Coupons
// =============================================================================

function getCoupons(?int $limit = null, ?int $offset = null, ?string $search = null): array
{
    $db = getDb();

    $countSql = 'SELECT COUNT(*) FROM coupons';
    $sql = 'SELECT * FROM coupons ORDER BY created_at DESC';
    $params = [];

    if ($search) {
        $like = '%' . $search . '%';
        $countSql = 'SELECT COUNT(*) FROM coupons WHERE code LIKE ? OR description LIKE ?';
        $sql = 'SELECT * FROM coupons WHERE code LIKE ? OR description LIKE ? ORDER BY created_at DESC';
        $params = [$like, $like];
    }

    if ($search) {
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
    } else {
        $total = (int)$db->query($countSql)->fetchColumn();
    }

    if ($limit !== null) {
        $sql .= ' LIMIT ?';
        $params[] = $limit;
    }
    if ($offset !== null) {
        $sql .= ' OFFSET ?';
        $params[] = $offset;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Map rows to arrays + fetch use counts
    $couponIds = array_map(fn($r) => $r['id'], $rows);
    $useCounts = [];
    if (!empty($couponIds)) {
        $placeholders = implode(',', array_fill(0, count($couponIds), '?'));
        $ucStmt = $db->prepare("SELECT coupon_id, COUNT(*) AS cnt FROM coupon_usage WHERE coupon_id IN ($placeholders) GROUP BY coupon_id");
        $ucStmt->execute($couponIds);
        while ($uc = $ucStmt->fetch()) {
            $useCounts[(int)$uc['coupon_id']] = (int)$uc['cnt'];
        }
    }

    return [
        'items' => array_map(fn($r) => [
            'id'                  => $r['id'],
            'code'                => $r['code'],
            'description'         => $r['description'],
            'discount_type'       => $r['discount_type'],
            'discount_value'      => (float)$r['discount_value'],
            'min_order_amount'    => $r['min_order_amount'] ? (float)$r['min_order_amount'] : null,
            'max_uses'            => $r['max_uses'] ? (int)$r['max_uses'] : null,
            'max_uses_per_customer' => $r['max_uses_per_customer'] ? (int)$r['max_uses_per_customer'] : null,
            'is_active'           => (bool)$r['is_active'],
            'starts_at'           => $r['starts_at'],
            'expires_at'          => $r['expires_at'],
            'created_at'          => $r['created_at'],
            'use_count'           => $useCounts[(int)$r['id']] ?? 0,
        ], $rows),
        'total' => $total,
    ];
}

function getCouponById(int $id): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) return null;
    return [
        'id'                  => $r['id'],
        'code'                => $r['code'],
        'description'         => $r['description'],
        'discount_type'       => $r['discount_type'],
        'discount_value'      => (float)$r['discount_value'],
        'min_order_amount'    => $r['min_order_amount'] ? (float)$r['min_order_amount'] : null,
        'max_uses'            => $r['max_uses'] ? (int)$r['max_uses'] : null,
        'max_uses_per_customer' => $r['max_uses_per_customer'] ? (int)$r['max_uses_per_customer'] : null,
        'is_active'           => (bool)$r['is_active'],
        'starts_at'           => $r['starts_at'],
        'expires_at'          => $r['expires_at'],
    ];
}

function getCouponByCode(string $code): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $r = $stmt->fetch();
    if (!$r) return null;
    return [
        'id'                  => $r['id'],
        'code'                => $r['code'],
        'description'         => $r['description'],
        'discount_type'       => $r['discount_type'],
        'discount_value'      => (float)$r['discount_value'],
        'min_order_amount'    => $r['min_order_amount'] ? (float)$r['min_order_amount'] : null,
        'max_uses'            => $r['max_uses'] ? (int)$r['max_uses'] : null,
        'max_uses_per_customer' => $r['max_uses_per_customer'] ? (int)$r['max_uses_per_customer'] : null,
        'is_active'           => (bool)$r['is_active'],
        'starts_at'           => $r['starts_at'],
        'expires_at'          => $r['expires_at'],
    ];
}

function validateCoupon(string $code, float $subtotal, ?string $customerEmail = null): array
{
    $coupon = getCouponByCode(strtoupper(trim($code)));
    if (!$coupon) {
        return ['valid' => false, 'error' => 'Coupon not found'];
    }

    $now = date('Y-m-d H:i:s');

    // Active?
    if (!$coupon['is_active']) {
        return ['valid' => false, 'error' => 'Coupon is inactive'];
    }

    // Start date
    if ($coupon['starts_at'] && $now < $coupon['starts_at']) {
        return ['valid' => false, 'error' => 'Coupon is not yet valid'];
    }

    // Expiry
    if ($coupon['expires_at'] && $now > $coupon['expires_at']) {
        return ['valid' => false, 'error' => 'Coupon has expired'];
    }

    // Min order amount
    if ($coupon['min_order_amount'] && $subtotal < $coupon['min_order_amount']) {
        return ['valid' => false, 'error' => 'Minimum order amount of $' . number_format($coupon['min_order_amount'], 2) . ' required'];
    }

    $db = getDb();

    // Max uses (global)
    if ($coupon['max_uses']) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?');
        $stmt->execute([$coupon['id']]);
        $uses = (int)$stmt->fetchColumn();
        if ($uses >= $coupon['max_uses']) {
            return ['valid' => false, 'error' => 'Coupon usage limit reached'];
        }
    }

    // Max uses per customer
    if ($coupon['max_uses_per_customer'] && $customerEmail) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND customer_email = ?');
        $stmt->execute([$coupon['id'], $customerEmail]);
        $customerUses = (int)$stmt->fetchColumn();
        if ($customerUses >= $coupon['max_uses_per_customer']) {
            return ['valid' => false, 'error' => 'You have already used this coupon'];
        }
    }

    // Calculate discount
    if ($coupon['discount_type'] === 'percentage') {
        $discount = round($subtotal * ($coupon['discount_value'] / 100), 2);
    } else {
        $discount = min($coupon['discount_value'], $subtotal);
    }

    return [
        'valid' => true,
        'coupon' => $coupon,
        'discount' => $discount,
    ];
}

function saveCoupon(array $coupon): int
{
    $db = getDb();

    if (!empty($coupon['id'])) {
        // Update
        $stmt = $db->prepare(
            'UPDATE coupons SET
                code = ?, description = ?, discount_type = ?, discount_value = ?,
                min_order_amount = ?, max_uses = ?, max_uses_per_customer = ?,
                is_active = ?, starts_at = ?, expires_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $coupon['code'],
            $coupon['description'] ?? '',
            $coupon['discount_type'],
            $coupon['discount_value'],
            $coupon['min_order_amount'],
            $coupon['max_uses'],
            $coupon['max_uses_per_customer'],
            $coupon['is_active'] ? 1 : 0,
            $coupon['starts_at'],
            $coupon['expires_at'],
            $coupon['id'],
        ]);
        return $coupon['id'];
    }

    // Create
    $stmt = $db->prepare(
        'INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_uses, max_uses_per_customer, is_active, starts_at, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $coupon['code'],
        $coupon['description'] ?? '',
        $coupon['discount_type'],
        $coupon['discount_value'],
        $coupon['min_order_amount'],
        $coupon['max_uses'],
        $coupon['max_uses_per_customer'],
        $coupon['is_active'] ? 1 : 0,
        $coupon['starts_at'],
        $coupon['expires_at'],
    ]);
    return (int)$db->lastInsertId();
}

function deleteCoupon(int $id): void
{
    $db = getDb();
    // FK in coupon_usage has ON DELETE CASCADE
    $db->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
}

function recordCouponUsage(int $couponId, string $orderId, string $customerEmail, float $discountAmount): void
{
    $db = getDb();

    // Find the order's internal ID
    $stmt = $db->prepare('SELECT id FROM orders WHERE order_number = ?');
    $stmt->execute([$orderId]);
    $orderDbId = $stmt->fetchColumn();
    if (!$orderDbId) return;

    $db->prepare(
        'INSERT INTO coupon_usage (coupon_id, order_id, customer_email, discount_amount) VALUES (?, ?, ?, ?)'
    )->execute([$couponId, $orderDbId, $customerEmail, $discountAmount]);
}

// =============================================================================
//  Settings
// =============================================================================

function getSettings(): array
{
    $db = getDb();
    $rows = $db->query('SELECT section, `key`, `value` FROM settings ORDER BY section, `key`')->fetchAll();

    $settings = [];
    foreach ($rows as $row) {
        $section = $row['section'];
        $key = $row['key'];
        $value = $row['value'];
        if (!isset($settings[$section])) $settings[$section] = [];
        $settings[$section][$key] = $value;
    }

    // Convert 'enabled' keys to boolean
    $boolKeys = ['enabled'];
    foreach ($settings as $section => &$kv) {
        foreach ($boolKeys as $bk) {
            if (isset($kv[$bk])) {
                $kv[$bk] = $kv[$bk] === '1' || $kv[$bk] === 'true';
            }
        }
    }
    unset($kv);

    // JSON-decode nested sub-sections (landing, etc.)
    $jsonSections = ['landing'];
    foreach ($jsonSections as $jsSection) {
        if (isset($settings[$jsSection])) {
            foreach ($settings[$jsSection] as $subKey => &$subVal) {
                $decoded = json_decode($subVal, true);
                if (is_array($decoded)) {
                    $subVal = $decoded;
                }
            }
            unset($subVal);
        }
    }

    // Migrate legacy flat sections into landing JSON format.
    // Old: brand_values.image_url = "..."  →  New: landing.brand_values = { image_url: "..." }
    $legacyMapping = [
        'brand_values' => ['label_es', 'label_en', 'title_es', 'title_en', 'paragraph_es', 'paragraph_en', 'cta_es', 'cta_en', 'image_url', 'cta_link', 'cta_category_slug', 'enabled'],
    ];
    foreach ($legacyMapping as $sectionKey => $fields) {
        if (isset($settings[$sectionKey])) {
            if (!isset($settings['landing'][$sectionKey]) || !is_array($settings['landing'][$sectionKey])) {
                $settings['landing'][$sectionKey] = [];
            }
            foreach ($fields as $field) {
                if (isset($settings[$sectionKey][$field])) {
                    $settings['landing'][$sectionKey][$field] = $settings[$sectionKey][$field];
                }
            }
        }
    }

    // Inject bank accounts into transfer.banks
    $banks = getBankAccounts();
    if (!isset($settings['transfer'])) $settings['transfer'] = ['enabled' => true];
    $settings['transfer']['banks'] = $banks;

    return $settings;
}

function saveSettings(array $input): void
{
    $db = getDb();
    $db->beginTransaction();
    try {
        foreach ($input as $section => $data) {
            if (!is_array($data)) continue;

            // Handle bank accounts separately
            if ($section === 'transfer' && isset($data['banks'])) {
                $enabled = !empty($data['enabled']) ? '1' : '0';
                $db->prepare('INSERT INTO settings (section, `key`, `value`) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')
                    ->execute(['transfer', 'enabled', $enabled]);

                saveBankAccounts($data['banks']);
                continue;
            }

            foreach ($data as $key => $value) {
                // Convert booleans to '1'/'0'
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                // JSON-encode nested arrays (e.g. landing sub-sections)
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $db->prepare('INSERT INTO settings (section, `key`, `value`) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')
                    ->execute([$section, $key, (string)$value]);
            }
        }
        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// =============================================================================
//  Bank Accounts
// =============================================================================

function getBankAccounts(): array
{
    $db = getDb();
    $rows = $db->query('SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
    return array_map(fn($r) => [
        'id'             => (int)$r['id'],
        'bankName'       => $r['bank_name'],
        'accountHolder'  => $r['account_holder'],
        'accountNumber'  => $r['account_number'],
        'accountType'    => $r['account_type'] ?? '',
        'routingNumber'  => $r['routing_number'] ?? '',
        'instructions'   => $r['instructions'] ?? '',
    ], $rows);
}

function saveBankAccounts(array $banks): void
{
    $db = getDb();
    $db->prepare('DELETE FROM bank_accounts')->execute();
    if (empty($banks)) return;

    $stmt = $db->prepare(
        'INSERT INTO bank_accounts (bank_name, account_holder, account_number, account_type, routing_number, instructions, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (array_values($banks) as $i => $b) {
        $stmt->execute([
            $b['bankName'] ?? '',
            $b['accountHolder'] ?? '',
            $b['accountNumber'] ?? '',
            $b['accountType'] ?? '',
            $b['routingNumber'] ?? '',
            $b['instructions'] ?? '',
            $i,
        ]);
    }
}

// =============================================================================
//  Dashboard Stats
// =============================================================================

function getDashboardStats(): array
{
    try {
        $db = getDb();

        $totalProducts = (int)$db->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn();
        $totalOrders   = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();

        // Monthly revenue (paid orders)
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');

        $paidStatusId = (int)$db->query("SELECT id FROM order_statuses WHERE code = 'paid'")->fetchColumn();
        $stmt = $db->prepare(
            'SELECT COUNT(*), COALESCE(SUM(total), 0) FROM orders WHERE status_id = ? AND created_at BETWEEN ? AND ?'
        );
        $stmt->execute([$paidStatusId, $monthStart, $monthEnd]);
        $monthlyData = $stmt->fetch();

        // Recent orders
        $recent = $db->query(
            'SELECT o.order_number, o.customer_name, o.total, os.code AS status_code, o.created_at, pm.code AS payment_method_code
             FROM orders o
             JOIN order_statuses os ON os.id = o.status_id
             JOIN payment_methods pm ON pm.id = o.payment_method_id
             ORDER BY o.created_at DESC LIMIT 5'
        )->fetchAll();

        // Status counts
        $statusCounts = [];
        $scRows = $db->query(
            'SELECT os.code, COUNT(*) AS cnt
             FROM orders o JOIN order_statuses os ON os.id = o.status_id
             GROUP BY os.code'
        )->fetchAll();
        foreach ($scRows as $sc) {
            $statusCounts[$sc['code']] = (int)$sc['cnt'];
        }

        // Low stock products: variants with stock <= 1
        $lowStockProds = $db->query(
            'SELECT DISTINCT p.id, p.name
             FROM products p
             JOIN product_variants pv ON pv.product_id = p.id
             WHERE p.deleted_at IS NULL AND pv.stock <= 1 AND pv.is_active = 1
             ORDER BY p.name'
        )->fetchAll();

        $monthlyRevenue = (float)$monthlyData['SUM(o.total)'];

        // All orders this month (includes all statuses)
        $stmt3 = $db->prepare(
            'SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?'
        );
        $stmt3->execute([$monthStart, $monthEnd]);
        $monthlyOrderCount = (int)$stmt3->fetchColumn();

        return [
            'totalProducts'    => $totalProducts,
            'totalOrders'      => $totalOrders,
            'monthlyRevenue'   => $monthlyRevenue,
            'monthlyOrderCount' => $monthlyOrderCount,
            'statusCounts'     => $statusCounts,
            'recentOrders'     => array_map(fn($r) => [
                'id'            => $r['order_number'],
                'customerName'  => $r['customer_name'],
                'total'         => (float)$r['total'],
                'status'        => $r['status_code'],
                'createdAt'     => date('c', strtotime($r['created_at'])),
                'paymentMethod' => $r['payment_method_code'],
            ], $recent),
            'lowStockProducts' => array_map(fn($p) => [
                'id'   => $p['id'],
                'name' => $p['name'],
            ], $lowStockProds),
        ];
    } catch (\PDOException $e) {
        error_log('Dashboard stats error: ' . $e->getMessage());
        return [
            'totalProducts'    => 0,
            'totalOrders'      => 0,
            'monthlyRevenue'   => 0.0,
            'monthlyOrderCount' => 0,
            'statusCounts'     => [],
            'recentOrders'     => [],
            'lowStockProducts' => [],
        ];
    }
}

// =============================================================================
//  Reviews
// =============================================================================

function getProductReviews(string $productId, bool $approvedOnly = true): array
{
    $db = getDb();
    $sql = 'SELECT * FROM product_reviews WHERE product_id = ?';
    $params = [$productId];
    if ($approvedOnly) {
        $sql .= ' AND is_approved = 1';
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return array_map(fn($r) => [
        'id'            => (int)$r['id'],
        'productId'     => $r['product_id'],
        'reviewerName'  => $r['reviewer_name'],
        'rating'        => (int)$r['rating'],
        'title'         => $r['title'],
        'comment'       => $r['comment'],
        'isApproved'    => (bool)$r['is_approved'],
        'createdAt'     => date('c', strtotime($r['created_at'])),
    ], $rows);
}

function getAllReviews(?int $limit = null, ?int $offset = null, ?string $status = null, ?string $search = null): array
{
    $db = getDb();

    $countSql = 'SELECT COUNT(*) FROM product_reviews pr JOIN products p ON p.id = pr.product_id';
    $sql = 'SELECT pr.*, p.name AS product_name, p.slug AS product_slug
            FROM product_reviews pr
            JOIN products p ON p.id = pr.product_id';
    $conditions = [];
    $params = [];

    if ($status === 'pending') {
        $conditions[] = 'pr.is_approved = 0';
    } elseif ($status === 'approved') {
        $conditions[] = 'pr.is_approved = 1';
    }

    if ($search) {
        $like = '%' . $search . '%';
        $conditions[] = '(p.name LIKE ? OR pr.reviewer_name LIKE ? OR pr.comment LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($conditions) {
        $where = ' WHERE ' . implode(' AND ', $conditions);
        $countSql .= $where;
        $sql .= $where;
    }

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql .= ' ORDER BY pr.created_at DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ?';
        $params[] = $limit;
    }
    if ($offset !== null) {
        $sql .= ' OFFSET ?';
        $params[] = $offset;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return [
        'items' => array_map(fn($r) => [
            'id'            => (int)$r['id'],
            'productId'     => $r['product_id'],
            'productName'   => $r['product_name'],
            'productSlug'   => $r['product_slug'],
            'reviewerName'  => $r['reviewer_name'],
            'reviewerEmail' => $r['reviewer_email'],
            'rating'        => (int)$r['rating'],
            'title'         => $r['title'],
            'comment'       => $r['comment'],
            'isApproved'    => (bool)$r['is_approved'],
            'createdAt'     => date('c', strtotime($r['created_at'])),
        ], $rows),
        'total' => $total,
    ];
}

function getReviewStats(string $productId): array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews WHERE product_id = ? AND is_approved = 1'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();

    // Rating distribution
    $distStmt = $db->prepare(
        'SELECT rating, COUNT(*) AS cnt FROM product_reviews
         WHERE product_id = ? AND is_approved = 1
         GROUP BY rating ORDER BY rating DESC'
    );
    $distStmt->execute([$productId]);
    $dist = [];
    while ($d = $distStmt->fetch()) {
        $dist[(int)$d['rating']] = (int)$d['cnt'];
    }

    return [
        'total'       => (int)$row['total'],
        'average'     => round((float)$row['avg_rating'], 1),
        'distribution' => $dist,
    ];
}

function saveReview(array $data): int
{
    $db = getDb();

    $stmt = $db->prepare(
        'INSERT INTO product_reviews (product_id, reviewer_name, reviewer_email, rating, title, comment, is_approved)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['product_id'],
        $data['reviewer_name'] ?? '',
        $data['reviewer_email'] ?? '',
        (int)$data['rating'],
        $data['title'] ?? '',
        $data['comment'] ?? '',
        isset($data['is_approved']) ? ($data['is_approved'] ? 1 : 0) : 0,
    ]);
    return (int)$db->lastInsertId();
}

function approveReview(int $id): void
{
    $db = getDb();
    $db->prepare('UPDATE product_reviews SET is_approved = 1 WHERE id = ?')->execute([$id]);
}

function deleteReview(int $id): void
{
    $db = getDb();
    $db->prepare('DELETE FROM product_reviews WHERE id = ?')->execute([$id]);
}

// =============================================================================
//  Wishlist
// =============================================================================

function getWishlist(int $customerId): array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT wi.product_id, wi.variant_id, wi.created_at,
                p.name, p.slug, p.price, p.currency
         FROM wishlist_items wi
         JOIN products p ON p.id = wi.product_id
         WHERE wi.customer_id = ? AND p.deleted_at IS NULL
         ORDER BY wi.created_at DESC'
    );
    $stmt->execute([$customerId]);
    $rows = $stmt->fetchAll();

    return array_map(fn($r) => [
        'productId' => $r['product_id'],
        'variantId' => $r['variant_id'],
        'name'      => $r['name'],
        'slug'      => $r['slug'],
        'price'     => (float)$r['price'],
        'currency'  => $r['currency'] ?: 'USD',
        'createdAt' => date('c', strtotime($r['created_at'])),
    ], $rows);
}

function getWishlistByEmail(string $email): array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $customerId = $stmt->fetchColumn();
    if (!$customerId) return [];
    return getWishlist((int)$customerId);
}

function addToWishlist(int $customerId, string $productId, ?int $variantId = null): bool
{
    $db = getDb();
    try {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO wishlist_items (customer_id, product_id, variant_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$customerId, $productId, $variantId]);
        return $stmt->rowCount() > 0;
    } catch (\Exception $e) {
        return false;
    }
}

function removeFromWishlist(int $customerId, string $productId, ?int $variantId = null): void
{
    $db = getDb();
    $sql = 'DELETE FROM wishlist_items WHERE customer_id = ? AND product_id = ?';
    $params = [$customerId, $productId];
    if ($variantId !== null) {
        $sql .= ' AND variant_id = ?';
        $params[] = $variantId;
    }
    $db->prepare($sql)->execute($params);
}

function isInWishlist(int $customerId, string $productId): bool
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ? AND product_id = ?'
    );
    $stmt->execute([$customerId, $productId]);
    return (int)$stmt->fetchColumn() > 0;
}

// =============================================================================
//  Resolver helpers
// =============================================================================

function resolveStatusId(string $code): int
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM order_statuses WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new \Exception("Unknown order status: $code");
    return (int)$id;
}

function resolvePaymentMethodId(string $code): int
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM payment_methods WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new \Exception("Unknown payment method: $code");
    return (int)$id;
}

function resolvePaymentStatusId(string $code): int
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM payment_statuses WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new \Exception("Unknown payment status: $code");
    return (int)$id;
}

// =============================================================================
//  Blog
// =============================================================================

function getBlogCategoryById(int $id): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM blog_categories WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getBlogCategories(?string $lang = null): array
{
    $db = getDb();
    $rows = $db->query('SELECT * FROM blog_categories ORDER BY name')->fetchAll();

    if ($lang && $lang !== 'es') {
        try {
            $tStmt = $db->prepare('SELECT category_id, name FROM blog_category_translations WHERE lang = ?');
            $tStmt->execute([$lang]);
            $transMap = [];
            while ($t = $tStmt->fetch()) {
                $transMap[(int)$t['category_id']] = $t['name'];
            }
            foreach ($rows as &$r) {
                if (isset($transMap[(int)$r['id']])) {
                    $r['name'] = $transMap[(int)$r['id']];
                }
            }
            unset($r);
        } catch (\PDOException $e) {
            // Translation table not available yet
        }
    }

    return $rows;
}

function getBlogPosts(int $page = 1, int $limit = 10, string $status = '', int $categoryId = 0, ?string $lang = null): array
{
    $db = getDb();
    $where = ['p.deleted_at IS NULL'];
    $params = [];

    if ($status) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    if ($categoryId > 0) {
        $where[] = 'p.category_id = ?';
        $params[] = $categoryId;
    }

    $whereClause = implode(' AND ', $where);
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p
         LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE {$whereClause}
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Apply translations if needed
    if ($lang && $lang !== 'es') {
        try {
            $postIds = array_map(fn($i) => $i['id'], $items);
            if (!empty($postIds)) {
                $placeholders = implode(',', array_fill(0, count($postIds), '?'));
                $tStmt = $db->prepare("SELECT blog_post_id, title, excerpt, content FROM blog_post_translations WHERE blog_post_id IN ($placeholders) AND lang = ?");
                $tParams = $postIds;
                $tParams[] = $lang;
                $tStmt->execute($tParams);
                $transMap = [];
                while ($t = $tStmt->fetch()) {
                    $transMap[(int)$t['blog_post_id']] = $t;
                }
                foreach ($items as &$item) {
                    $tid = (int)$item['id'];
                    if (isset($transMap[$tid])) {
                        if ($transMap[$tid]['title']) $item['title'] = $transMap[$tid]['title'];
                        if ($transMap[$tid]['excerpt']) $item['excerpt'] = $transMap[$tid]['excerpt'];
                        if ($transMap[$tid]['content']) $item['content'] = $transMap[$tid]['content'];
                    }
                }
                unset($item);
            }
        } catch (\PDOException $e) {
            // Translation table not available yet
        }
    }

    // Count total
    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM blog_posts p WHERE {$whereClause}"
    );
    $countParams = array_slice($params, 0, -2);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)];
}

function getBlogPostById(int $id, ?string $lang = null): ?array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p
         LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.deleted_at IS NULL'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;

    if ($lang && $lang !== 'es') {
        try {
            $tStmt = $db->prepare('SELECT title, excerpt, content FROM blog_post_translations WHERE blog_post_id = ? AND lang = ?');
            $tStmt->execute([$id, $lang]);
            $trans = $tStmt->fetch();
            if ($trans) {
                if ($trans['title']) $row['title'] = $trans['title'];
                if ($trans['excerpt']) $row['excerpt'] = $trans['excerpt'];
                if ($trans['content']) $row['content'] = $trans['content'];
            }

            // Translate category name
            if ($row['category_id']) {
                $ctStmt = $db->prepare('SELECT name FROM blog_category_translations WHERE category_id = ? AND lang = ?');
                $ctStmt->execute([(int)$row['category_id'], $lang]);
                $ctName = $ctStmt->fetchColumn();
                if ($ctName) $row['category_name'] = $ctName;
            }
        } catch (\PDOException $e) {
            // Translation table not available yet
        }
    }

    return $row;
}

function getBlogPostBySlug(string $slug, ?string $lang = null): ?array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p
         LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.deleted_at IS NULL'
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) return null;

    if ($lang && $lang !== 'es') {
        try {
            $tStmt = $db->prepare('SELECT title, excerpt, content FROM blog_post_translations WHERE blog_post_id = ? AND lang = ?');
            $tStmt->execute([$row['id'], $lang]);
            $trans = $tStmt->fetch();
            if ($trans) {
                if ($trans['title']) $row['title'] = $trans['title'];
                if ($trans['excerpt']) $row['excerpt'] = $trans['excerpt'];
                if ($trans['content']) $row['content'] = $trans['content'];
            }

            // Translate category name
            if ($row['category_id']) {
                $ctStmt = $db->prepare('SELECT name FROM blog_category_translations WHERE category_id = ? AND lang = ?');
                $ctStmt->execute([(int)$row['category_id'], $lang]);
                $ctName = $ctStmt->fetchColumn();
                if ($ctName) $row['category_name'] = $ctName;
            }
        } catch (\PDOException $e) {
            // Translation table not available yet
        }
    }

    return $row;
}

function createBlogPost(array $data): int
{
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO blog_posts (title, slug, excerpt, content, featured_image, author, status, category_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $data['title'],
        $data['slug'],
        $data['excerpt'] ?? '',
        $data['content'],
        $data['featured_image'] ?? null,
        $data['author'] ?? 'Ram;Lop',
        $data['status'] ?? 'draft',
        $data['category_id'] ?? null,
    ]);

    $id = (int)$db->lastInsertId();

    // Set published_at if status is published
    if (($data['status'] ?? '') === 'published') {
        $db->prepare('UPDATE blog_posts SET published_at = COALESCE(published_at, NOW()) WHERE id = ?')
           ->execute([$id]);
    }

    return $id;
}

function updateBlogPost(int $id, array $data): void
{
    $db = getDb();
    $fields = [];
    $params = [];

    foreach (['title', 'slug', 'excerpt', 'content', 'featured_image', 'author', 'status', 'category_id'] as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "{$key} = ?";
            $params[] = $data[$key];
        }
    }

    if (empty($fields)) return;

    $fields[] = 'updated_at = NOW()';
    $params[] = $id;

    $db->prepare('UPDATE blog_posts SET ' . implode(', ', $fields) . ' WHERE id = ?')
       ->execute($params);

    // Set published_at when transitioning to published
    if (($data['status'] ?? '') === 'published') {
        $db->prepare('UPDATE blog_posts SET published_at = COALESCE(published_at, NOW()) WHERE id = ?')
           ->execute([$id]);
    }
}

function deleteBlogPost(int $id): void
{
    $db = getDb();
    $db->prepare('UPDATE blog_posts SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
}

// =============================================================================
//  Email Templates
// =============================================================================

function getEmailTemplates(int $page = 1, int $limit = 10, string $search = ''): array
{
    $db = getDb();
    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = '(code LIKE ? OR name LIKE ? OR subject LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereClause = implode(' AND ', $where);
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare(
        "SELECT * FROM email_templates
         WHERE {$whereClause}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM email_templates WHERE {$whereClause}"
    );
    $countParams = array_slice($params, 0, -2);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)];
}

function getEmailTemplateById(int $id): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM email_templates WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getEmailTemplateByCode(string $code): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM email_templates WHERE code = ?');
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

function createEmailTemplate(array $data): int
{
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO email_templates (code, name, subject, body_html, body_text, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $data['code'],
        $data['name'],
        $data['subject'],
        $data['body_html'],
        $data['body_text'] ?? null,
        !empty($data['is_active']) ? 1 : 0,
    ]);
    return (int)$db->lastInsertId();
}

function updateEmailTemplate(int $id, array $data): void
{
    $db = getDb();
    $fields = [];
    $params = [];

    foreach (['code', 'name', 'subject', 'body_html', 'body_text'] as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "{$key} = ?";
            $params[] = $data[$key];
        }
    }

    if (array_key_exists('is_active', $data)) {
        $fields[] = 'is_active = ?';
        $params[] = !empty($data['is_active']) ? 1 : 0;
    }

    if (empty($fields)) return;

    $fields[] = 'updated_at = NOW()';
    $params[] = $id;

    $db->prepare('UPDATE email_templates SET ' . implode(', ', $fields) . ' WHERE id = ?')
       ->execute($params);
}

function deleteEmailTemplate(int $id): void
{
    $db = getDb();
    $db->prepare('DELETE FROM email_templates WHERE id = ?')->execute([$id]);
}

function emailTemplateFileExists(string $code): ?string
{
    $path = __DIR__ . '/../email-templates/' . $code . '.html';
    return file_exists($path) ? $path : null;
}

function loadEmailTemplateFromFile(string $code): ?array
{
    $path = emailTemplateFileExists($code);
    if (!$path) return null;

    $raw = file_get_contents($path);

    $subject = '';
    $preheader = '';
    if (preg_match('/^Subject:\s*(.+)$/m', $raw, $m)) $subject = trim($m[1]);
    if (preg_match('/^Preheader:\s*(.+)$/m', $raw, $m)) $preheader = trim($m[1]);

    $body = preg_replace('/^(Subject|Preheader):\s*.+\n+/m', '', $raw);
    $body = trim($body);

    return [
        'code'      => $code,
        'name'      => str_replace('_', ' ', ucfirst($code)),
        'subject'   => $subject,
        'preheader' => $preheader,
        'body_html' => $body,
        'is_active' => true,
    ];
}

// =============================================================================
//  Customer helpers
// =============================================================================

function getCustomerIdFromToken(string $token): ?int
{
    if (empty($token)) return null;
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT c.id FROM customer_sessions cs
         JOIN customers c ON c.id = cs.customer_id
         WHERE cs.token = ? AND cs.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

// =============================================================================
//  Cart (persistent)
// =============================================================================

function getCartItems(int $customerId): array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT ci.product_id, ci.quantity, ci.selected_color, ci.selected_size,
                p.name, p.slug, p.price, p.currency
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         WHERE ci.customer_id = ? AND p.deleted_at IS NULL
         ORDER BY ci.created_at ASC'
    );
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function setCartItems(int $customerId, array $items): void
{
    $db = getDb();
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM cart_items WHERE customer_id = ?')->execute([$customerId]);
        if (!empty($items)) {
            $stmt = $db->prepare(
                'INSERT INTO cart_items (customer_id, product_id, quantity, selected_color, selected_size)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $stmt->execute([
                    $customerId,
                    $item['product_id'] ?? $item['product']['id'] ?? '',
                    (int)($item['quantity'] ?? 1),
                    $item['selected_color'] ?? '',
                    $item['selected_size'] ?? '',
                ]);
            }
        }
        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function addCartItem(int $customerId, string $productId, int $quantity, string $color, string $size): void
{
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO cart_items (customer_id, product_id, quantity, selected_color, selected_size)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
    $stmt->execute([$customerId, $productId, $quantity, $color, $size]);
}

function removeCartItem(int $customerId, string $productId, string $color, string $size): void
{
    $db = getDb();
    $stmt = $db->prepare(
        'DELETE FROM cart_items WHERE customer_id = ? AND product_id = ? AND selected_color = ? AND selected_size = ?'
    );
    $stmt->execute([$customerId, $productId, $color, $size]);
}

function clearCart(int $customerId): void
{
    $db = getDb();
    $db->prepare('DELETE FROM cart_items WHERE customer_id = ?')->execute([$customerId]);
}

// =============================================================================
//  Customer Addresses
// =============================================================================

function getCustomerAddresses(int $customerId): array
{
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default_shipping DESC, is_default_billing DESC, created_at ASC'
    );
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

function getCustomerAddress(int $addressId, int $customerId): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ? LIMIT 1');
    $stmt->execute([$addressId, $customerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function createCustomerAddress(int $customerId, array $data): int
{
    $db = getDb();

    // If this is the first address or explicitly set as default, update others
    $existing = getCustomerAddresses($customerId);
    $isDefaultShipping = !empty($data['is_default_shipping']) || empty($existing);
    $isDefaultBilling = !empty($data['is_default_billing']) || empty($existing);

    if ($isDefaultShipping) {
        $db->prepare('UPDATE customer_addresses SET is_default_shipping = FALSE WHERE customer_id = ?')
            ->execute([$customerId]);
    }
    if ($isDefaultBilling) {
        $db->prepare('UPDATE customer_addresses SET is_default_billing = FALSE WHERE customer_id = ?')
            ->execute([$customerId]);
    }

    $stmt = $db->prepare(
        'INSERT INTO customer_addresses (customer_id, label, address_line1, address_line2, city, state, zip, country, phone, is_default_shipping, is_default_billing)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $customerId,
        $data['label'] ?? '',
        $data['address_line1'] ?? '',
        $data['address_line2'] ?? '',
        $data['city'] ?? '',
        $data['state'] ?? '',
        $data['zip'] ?? '',
        $data['country'] ?? 'GT',
        $data['phone'] ?? '',
        $isDefaultShipping ? 1 : 0,
        $isDefaultBilling ? 1 : 0,
    ]);
    return (int)$db->lastInsertId();
}

function updateCustomerAddress(int $addressId, int $customerId, array $data): void
{
    $db = getDb();
    $existing = getCustomerAddress($addressId, $customerId);
    if (!$existing) return;

    $fields = ['label', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'country', 'phone'];
    $sets = [];
    $params = [];
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            $sets[] = "$f = ?";
            $params[] = $data[$f];
        }
    }

    if (!empty($sets)) {
        $params[] = $addressId;
        $db->prepare('UPDATE customer_addresses SET ' . implode(', ', $sets) . ' WHERE id = ? AND customer_id = ?')
            ->execute(array_merge($params, [$addressId, $customerId]));
    }

    // Handle defaults
    if (!empty($data['is_default_shipping'])) {
        $db->prepare('UPDATE customer_addresses SET is_default_shipping = FALSE WHERE customer_id = ?')
            ->execute([$customerId]);
        $db->prepare('UPDATE customer_addresses SET is_default_shipping = TRUE WHERE id = ? AND customer_id = ?')
            ->execute([$addressId, $customerId]);
    }
    if (!empty($data['is_default_billing'])) {
        $db->prepare('UPDATE customer_addresses SET is_default_billing = FALSE WHERE customer_id = ?')
            ->execute([$customerId]);
        $db->prepare('UPDATE customer_addresses SET is_default_billing = TRUE WHERE id = ? AND customer_id = ?')
            ->execute([$addressId, $customerId]);
    }
}

function deleteCustomerAddress(int $addressId, int $customerId): void
{
    $db = getDb();
    $stmt = $db->prepare('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?');
    $stmt->execute([$addressId, $customerId]);
}
