<?php
declare(strict_types=1);

/**
 * POST /api/monedas/create.php - Create a new currency (admin only)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonError('Invalid JSON');

$code = strtoupper(trim($input['code'] ?? ''));
$name = trim($input['name'] ?? '');
$symbol = trim($input['symbol'] ?? '');
$rate = (float)($input['exchange_rate'] ?? 1.0);
$decimals = (int)($input['decimal_places'] ?? 2);
$sortOrder = (int)($input['sort_order'] ?? 0);

if (strlen($code) !== 3) jsonError('Currency code must be 3 characters');
if (empty($name)) jsonError('Currency name is required');
if (empty($symbol)) jsonError('Currency symbol is required');
if ($rate <= 0) jsonError('Exchange rate must be greater than 0');

$db = getDb();

// Check if code already exists
$stmt = $db->prepare('SELECT id FROM currencies WHERE code = ?');
$stmt->execute([$code]);
if ($stmt->fetch()) jsonError('Currency code already exists', 409);

$stmt = $db->prepare(
    'INSERT INTO currencies (code, name, symbol, exchange_rate, decimal_places, is_active, sort_order)
     VALUES (?, ?, ?, ?, ?, 1, ?)'
);
$stmt->execute([$code, $name, $symbol, $rate, $decimals, $sortOrder]);

logAdminAction('create', 'currency', $code, "Created currency: {$code} - {$name}");

jsonResponse([
    'success' => true,
    'currency' => [
        'code' => $code,
        'name' => $name,
        'symbol' => $symbol,
        'exchange_rate' => $rate,
        'decimal_places' => $decimals,
        'is_active' => true,
    ],
], 201);
