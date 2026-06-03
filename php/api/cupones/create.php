<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['code']) || !isset($data['discount_type']) || !isset($data['discount_value'])) {
    jsonError('Code, discount_type, and discount_value are required');
}

$code = strtoupper(trim($data['code']));

// Check for duplicate code
$existing = getCouponByCode($code);
if ($existing) jsonError('A coupon with this code already exists');

$coupon = [
    'code' => $code,
    'description' => $data['description'] ?? '',
    'discount_type' => $data['discount_type'],
    'discount_value' => (float)$data['discount_value'],
    'min_order_amount' => !empty($data['min_order_amount']) ? (float)$data['min_order_amount'] : null,
    'max_uses' => !empty($data['max_uses']) ? (int)$data['max_uses'] : null,
    'max_uses_per_customer' => !empty($data['max_uses_per_customer']) ? (int)$data['max_uses_per_customer'] : null,
    'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
    'starts_at' => $data['starts_at'] ?? null,
    'expires_at' => $data['expires_at'] ?? null,
];

$id = saveCoupon($coupon);
$coupon['id'] = $id;
logAdminAction('create', 'coupon', (string)$id, 'Created coupon: ' . $code);
jsonResponse($coupon);
