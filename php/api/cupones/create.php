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

if (strlen($code) > 50) jsonError('Code must be 50 characters or less');
if (!in_array($data['discount_type'], ['percentage', 'fixed'], true)) jsonError('Invalid discount type');
if (isset($data['description']) && strlen($data['description']) > 255) jsonError('Description must be 255 characters or less');
if ($data['discount_type'] === 'percentage' && (float)$data['discount_value'] > 100) jsonError('Percentage discount cannot exceed 100%');
if (!empty($data['max_uses']) && (int)$data['max_uses'] < 0) jsonError('Max uses must be a positive number');
if (!empty($data['max_uses_per_customer']) && (int)$data['max_uses_per_customer'] < 0) jsonError('Max uses per customer must be a positive number');
if (!empty($data['starts_at']) && !empty($data['expires_at']) && $data['expires_at'] <= $data['starts_at']) {
    jsonError('Expiry date must be after the start date');
}

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
