<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['code'])) {
    jsonError('Coupon code is required');
}

$code = strtoupper(trim($data['code']));
$subtotal = (float)($data['subtotal'] ?? 0);
$customerEmail = $data['email'] ?? null;

$result = validateCoupon($code, $subtotal, $customerEmail);
jsonResponse($result);
