<?php
declare(strict_types=1);

/**
 * List active coupons for selection dropdowns.
 * GET /api/cupones/list-active.php
 * Returns coupons that are active and within their validity date range.
 * No auth required — used in admin config page (already authenticated) and potentially public forms.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
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

    jsonResponse(array_map(fn($r) => [
        'id'             => (int)$r['id'],
        'code'           => $r['code'],
        'discount_type'  => $r['discount_type'],
        'discount_value' => (float)$r['discount_value'],
        'description'    => $r['description'],
    ], $rows));

} catch (\PDOException $e) {
    error_log("[Coupons] list-active error: " . $e->getMessage());
    jsonError('Database error', 500);
}
