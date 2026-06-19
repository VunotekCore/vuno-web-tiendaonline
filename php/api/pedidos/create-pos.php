<?php
declare(strict_types=1);

/**
 * POST /api/pedidos/create-pos.php
 * POS (Point of Sale) order creation — transactional stock deduction.
 * Requires admin session with superadmin, editor, or cashier role.
 *
 * Input JSON:
 *   { "cart_items": [{ "variant_id": 1, "quantity": 2 }],
 *     "payment_method": "pos_cash",
 *     "customer_name": "Venta en Mostrador",
 *     "customer_email": "pos@ramlop.com" }
 *
 * Returns: { "success": true, "id": "RL-XXXX", "total": 900.00, "items_count": 2 }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/currency.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

startAdminSession();
if (!isAdminLoggedIn()) {
    jsonError('Unauthorized', 401);
}
requireRole('superadmin', 'editor', 'cashier');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['cart_items']) || !is_array($input['cart_items'])) {
    jsonError('Se requieren items en el carrito');
}

$paymentMethodCode = $input['payment_method'] ?? 'pos_cash';
$validPosMethods = ['pos_cash', 'pos_card', 'pos_transfer'];
if (!in_array($paymentMethodCode, $validPosMethods, true)) {
    jsonError('Método de pago POS inválido');
}

$db = getDb();
$db->beginTransaction();

try {
    $total = 0;
    $items = [];
    $orderId = generateOrderId();

    foreach ($input['cart_items'] as $item) {
        $variantId = (int)($item['variant_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);
        if ($variantId <= 0 || $qty <= 0) {
            continue;
        }

        // Lock row and fetch variant with product/color/size data
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
        $variant = $stmt->fetch();

        if (!$variant) {
            throw new \Exception("Variante ID $variantId no encontrada");
        }

        $stockBefore = (int)$variant['stock'];
        if ($stockBefore < $qty) {
            throw new \Exception(
                "Stock insuficiente: {$variant['name']} ({$variant['color_name']}/{$variant['size_label']}) " .
                "— disponible: $stockBefore, solicitado: $qty"
            );
        }

        // Deduct stock
        $stockAfter = $stockBefore - $qty;
        $db->prepare('UPDATE product_variants SET stock = ? WHERE id = ?')
           ->execute([$stockAfter, $variantId]);

        // Record stock movement with reference_type = 'pos'
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
            $_SESSION['admin_user_id'] ?? null,
        ]);

        $unitPrice = $variant['price_override'] !== null
            ? (float)$variant['price_override']
            : (float)$variant['price'];
        $subtotal = $unitPrice * $qty;
        $total += $subtotal;

        $items[] = [
            'product_id'     => $variant['product_id'],
            'variant_id'     => $variantId,
            'product_name'   => $variant['name'],
            'product_slug'   => $variant['product_slug'] ?? '',
            'product_price'  => $unitPrice,
            'quantity'       => $qty,
            'unit_price'     => $unitPrice,
            'subtotal'       => $subtotal,
            'selected_color' => $variant['color_name'],
            'selected_size'  => $variant['size_label'],
        ];
    }

    if (empty($items)) {
        throw new \Exception('No se pudieron procesar items');
    }

    // Calculate tax from settings
    $settings = getSettings();
    $taxRate = (float)($settings['tax']['rate'] ?? 0) / 100;
    $taxTotal = round($total * $taxRate, 2);
    $grandTotal = round($total + $taxTotal, 2);

    // Resolve FKs
    $paymentMethodId = resolvePaymentMethodId($paymentMethodCode);
    $statusDelivered = resolveStatusId('delivered');
    $paymentCompleted = resolvePaymentStatusId('completed');

    $customerName = $input['customer_name'] ?? 'Venta en Mostrador';
    $customerEmail = $input['customer_email'] ?? 'pos@ramlop.com';

    // Use store currency from settings
    $storeCurrency = getStoreCurrency();
    $currencyCode = $storeCurrency['code'] ?? 'USD';
    $exchangeRate = $storeCurrency['exchange_rate'] ?? 1.0;

    // Insert order (nace como delivered + completed con origin = 'pos')
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
        "Venta POS - Cajero: " . ($_SESSION['admin_email'] ?? ''),
    ]);
    $orderDbId = (int)$db->lastInsertId();

    // Insert order items
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

    // Status history
    $adminId = $_SESSION['admin_user_id'] ?? null;
    $db->prepare(
        'INSERT INTO order_status_history (order_id, from_status_id, to_status_id, changed_by, notes)
         VALUES (?, NULL, ?, ?, ?)'
    )->execute([$orderDbId, $statusDelivered, $adminId, 'POS order created — delivered at sale']);

    $db->commit();

    // Log audit
    logAdminAction('create', 'order', $orderId, "POS sale #{$orderId} — " . number_format($grandTotal, 2) . " USD", [
        'payment_method' => $paymentMethodCode,
        'total'          => $grandTotal,
        'subtotal'       => $total,
        'tax'            => $taxTotal,
        'items_count'    => count($items),
        'origin'         => 'pos',
    ]);

    jsonResponse([
        'success'     => true,
        'id'          => $orderId,
        'total'       => $grandTotal,
        'subtotal'    => $total,
        'tax'         => $taxTotal,
        'items_count' => count($items),
    ]);
} catch (\Exception $e) {
    $db->rollBack();
    jsonError('Error al procesar venta POS: ' . $e->getMessage(), 400);
}
