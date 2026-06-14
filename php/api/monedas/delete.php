<?php
declare(strict_types=1);

/**
 * POST /api/monedas/delete.php - Deactivate a currency (admin only)
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
if (!$input || empty($input['code'])) jsonError('Currency code required');

$code = strtoupper(trim($input['code']));

// Prevent deleting USD (base currency)
if ($code === 'USD') jsonError('Cannot delete base currency (USD)', 400);

// Check if this is the store currency
$storeCurrency = getStoreCurrency();
if ($storeCurrency['code'] === $code) {
    jsonError('Cannot delete the active store currency. Change store currency first.', 400);
}

$db = getDb();
$stmt = $db->prepare('UPDATE currencies SET is_active = 0 WHERE code = ?');
$stmt->execute([$code]);

logAdminAction('deactivate', 'currency', $code, "Deactivated currency: {$code}");

jsonResponse(['success' => true, 'code' => $code]);
