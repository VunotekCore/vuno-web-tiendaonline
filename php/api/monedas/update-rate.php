<?php
/**
 * POST /api/monedas/update-rate.php - Update currency exchange rates (admin only)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/currency.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonError('Invalid JSON');

$db = getDb();

// Bulk update: [{code: 'MXN', exchange_rate: 20.5}, ...]
if (isset($input['rates']) && is_array($input['rates'])) {
    $stmt = $db->prepare('UPDATE currencies SET exchange_rate = ?, is_active = ?, sort_order = ? WHERE code = ?');
    foreach ($input['rates'] as $r) {
        $code = strtoupper(trim($r['code'] ?? ''));
        if (strlen($code) !== 3) continue;
        $rate = (float)($r['exchange_rate'] ?? 1.0);
        $active = isset($r['is_active']) ? ($r['is_active'] ? 1 : 0) : 1;
        $sort = (int)($r['sort_order'] ?? 0);
        $stmt->execute([$rate, $active, $sort, $code]);
    }

    logAdminAction('update', 'currency_rates', 'bulk', 'Updated currency exchange rates');
    jsonResponse(['success' => true]);
}

// Single update: {code: 'MXN', exchange_rate: 20.5}
$code = strtoupper(trim($input['code'] ?? ''));
$rate = (float)($input['exchange_rate'] ?? 0);
if (strlen($code) !== 3) jsonError('Invalid currency code');
if ($rate <= 0) jsonError('Exchange rate must be greater than 0');

$stmt = $db->prepare('UPDATE currencies SET exchange_rate = ? WHERE code = ?');
$stmt->execute([$rate, $code]);

// Also store as store currency if requested
if (!empty($input['set_as_store'])) {
    $db->prepare("INSERT INTO settings (section, `key`, `value`) VALUES ('currency', 'code', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$code]);
}

logAdminAction('update', 'currency_rate', $code, "Updated {$code} exchange rate to {$rate}");

jsonResponse(['success' => true, 'code' => $code, 'exchange_rate' => $rate]);
