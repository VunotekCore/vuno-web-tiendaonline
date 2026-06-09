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
if (!$data || empty($data['id'])) jsonError('Coupon ID is required');

$id = (int)$data['id'];
$existing = getCouponById($id);
if (!$existing) jsonError('Coupon not found', 404);

$code = !empty($data['code']) ? strtoupper(trim($data['code'])) : $existing['code'];

if (strlen($code) > 50) jsonError('Code must be 50 characters or less');
if (!empty($data['discount_type']) && !in_array($data['discount_type'], ['percentage', 'fixed'], true)) jsonError('Invalid discount type');
if (array_key_exists('description', $data) && strlen((string)$data['description']) > 255) jsonError('Description must be 255 characters or less');
if (!empty($data['discount_value']) && !empty($data['discount_type']) && $data['discount_type'] === 'percentage' && (float)$data['discount_value'] > 100) jsonError('Percentage discount cannot exceed 100%');
if (!empty($data['max_uses']) && (int)$data['max_uses'] < 0) jsonError('Max uses must be a positive number');
if (!empty($data['max_uses_per_customer']) && (int)$data['max_uses_per_customer'] < 0) jsonError('Max uses per customer must be a positive number');
if (!empty($data['starts_at']) && !empty($data['expires_at']) && $data['expires_at'] <= $data['starts_at']) {
    jsonError('Expiry date must be after the start date');
}

$coupon = [
    'id' => $id,
    'code' => $code,
    'description' => $data['description'] ?? $existing['description'],
    'discount_type' => $data['discount_type'] ?? $existing['discount_type'],
    'discount_value' => isset($data['discount_value']) ? (float)$data['discount_value'] : $existing['discount_value'],
    'min_order_amount' => array_key_exists('min_order_amount', $data) ? (!empty($data['min_order_amount']) ? (float)$data['min_order_amount'] : null) : $existing['min_order_amount'],
    'max_uses' => array_key_exists('max_uses', $data) ? (!empty($data['max_uses']) ? (int)$data['max_uses'] : null) : $existing['max_uses'],
    'max_uses_per_customer' => array_key_exists('max_uses_per_customer', $data) ? (!empty($data['max_uses_per_customer']) ? (int)$data['max_uses_per_customer'] : null) : $existing['max_uses_per_customer'],
    'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : $existing['is_active'],
    'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $existing['starts_at'],
    'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $existing['expires_at'],
];

saveCoupon($coupon);
logAdminAction('update', 'coupon', (string)$id, 'Updated coupon: ' . $coupon['code']);
jsonResponse($coupon);
