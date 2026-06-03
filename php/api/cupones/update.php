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

$coupon = [
    'id' => $id,
    'code' => !empty($data['code']) ? strtoupper(trim($data['code'])) : $existing['code'],
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
