<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/currency.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor', 'cashier');

try {
    $db = getDb();
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');

    $storeCurrency = getStoreCurrency();
    $currencySymbol = $storeCurrency['symbol'] ?? '$';
    $exchangeRate = $storeCurrency['exchange_rate'] ?? 1;

    $excludeCancelled = " AND o.status_id != (SELECT id FROM order_statuses WHERE code = 'cancelled')";

    // Today's POS sales stats (active, non-cancelled)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS order_count,
            COALESCE(SUM(total), 0) AS total_sales
        FROM orders o
        WHERE o.origin = 'pos'
        AND o.created_at BETWEEN ? AND ?
        {$excludeCancelled}
    ");
    $stmt->execute([$todayStart, $todayEnd]);
    $todayData = $stmt->fetch();

    $orderCount = (int)$todayData['order_count'];
    $totalSales = (float)$todayData['total_sales'];

    // Total items sold today via order_items (non-cancelled)
    $itemStmt = $db->prepare("
        SELECT COALESCE(SUM(oi.quantity), 0) AS total_items
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.origin = 'pos'
        AND o.created_at BETWEEN ? AND ?
        {$excludeCancelled}
    ");
    $itemStmt->execute([$todayStart, $todayEnd]);
    $totalItems = (int)$itemStmt->fetchColumn();

    $avgTicket = $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0;

    // Cancelled POS orders today
    $cancelledStmt = $db->prepare("
        SELECT 
            COUNT(*) AS cancelled_count,
            COALESCE(SUM(total), 0) AS cancelled_total
        FROM orders o
        WHERE o.origin = 'pos'
        AND o.created_at BETWEEN ? AND ?
        AND o.status_id = (SELECT id FROM order_statuses WHERE code = 'cancelled')
    ");
    $cancelledStmt->execute([$todayStart, $todayEnd]);
    $cancelledData = $cancelledStmt->fetch();

    // Recent POS orders with item count
    $recent = $db->prepare("
        SELECT o.order_number, o.customer_name, o.total,
               o.created_at, pm.code AS payment_method_code, pm.name AS payment_method_name,
               (SELECT COALESCE(SUM(oi2.quantity), 0) FROM order_items oi2 WHERE oi2.order_id = o.id) AS items_count
        FROM orders o
        JOIN payment_methods pm ON pm.id = o.payment_method_id
        WHERE o.origin = 'pos'
        {$excludeCancelled}
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $recent->execute();
    $recentOrders = $recent->fetchAll();

    // Payment method breakdown today
    $pmBreakdown = $db->prepare("
        SELECT pm.code, pm.name, COUNT(*) AS cnt, COALESCE(SUM(o.total), 0) AS total
        FROM orders o
        JOIN payment_methods pm ON pm.id = o.payment_method_id
        WHERE o.origin = 'pos' AND o.created_at BETWEEN ? AND ?
        {$excludeCancelled}
        GROUP BY pm.code, pm.name
        ORDER BY cnt DESC
    ");
    $pmBreakdown->execute([$todayStart, $todayEnd]);
    $paymentMethods = $pmBreakdown->fetchAll();

    jsonResponse([
        'success' => true,
        'today' => [
            'order_count' => $orderCount,
            'total_sales' => $totalSales,
            'display_total_sales' => round($totalSales * $exchangeRate, 2),
            'total_items' => $totalItems,
            'avg_ticket' => $avgTicket,
            'display_avg_ticket' => round($avgTicket * $exchangeRate, 2),
        ],
        'cancelled' => [
            'count' => (int)$cancelledData['cancelled_count'],
            'total' => (float)$cancelledData['cancelled_total'],
            'display_total' => round((float)$cancelledData['cancelled_total'] * $exchangeRate, 2),
        ],
        'currency' => [
            'symbol' => $currencySymbol,
            'exchange_rate' => $exchangeRate,
        ],
        'recent_orders' => array_map(function($r) use ($currencySymbol, $exchangeRate) {
            $total = (float)$r['total'];
            return [
                'order_number' => $r['order_number'],
                'customer_name' => $r['customer_name'],
                'total' => $total,
                'display_total' => round($total * $exchangeRate, 2),
                'display_symbol' => $currencySymbol,
                'items_count' => (int)$r['items_count'],
                'payment_method_code' => $r['payment_method_code'],
                'payment_method_name' => $r['payment_method_name'],
                'created_at' => date('c', strtotime($r['created_at'])),
                'time' => date('H:i', strtotime($r['created_at'])),
            ];
        }, $recentOrders),
        'payment_methods' => array_map(function($pm) use ($currencySymbol, $exchangeRate) {
            return [
                'code' => $pm['code'],
                'name' => $pm['name'],
                'count' => (int)$pm['cnt'],
                'total' => (float)$pm['total'],
                'display_total' => round((float)$pm['total'] * $exchangeRate, 2),
            ];
        }, $paymentMethods),
    ]);
} catch (\PDOException $e) {
    jsonError('Database error: ' . $e->getMessage(), 500);
}
