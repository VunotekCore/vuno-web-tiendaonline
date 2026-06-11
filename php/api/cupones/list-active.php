<?php
/**
 * List active coupons for selection dropdowns.
 * GET /api/cupones/list-active.php
 * Returns coupons that are active and within their validity date range.
 * No auth required — used in admin config page (already authenticated) and potentially public forms.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDb();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'SELECT id, code, discount_type, discount_value, description
         FROM coupons
         WHERE is_active = 1
           AND (starts_at IS NULL OR starts_at <= ?)
           AND (expires_at IS NULL OR expires_at >= ?)
         ORDER BY code ASC'
    );
    $stmt->execute([$now, $now]);
    $rows = $stmt->fetchAll();

    echo json_encode(array_map(fn($r) => [
        'id'             => (int)$r['id'],
        'code'           => $r['code'],
        'discount_type'  => $r['discount_type'],
        'discount_value' => (float)$r['discount_value'],
        'description'    => $r['description'],
    ], $rows));

} catch (\PDOException $e) {
    error_log("[Coupons] list-active error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
