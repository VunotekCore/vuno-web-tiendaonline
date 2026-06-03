<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) jsonError('Coupon ID is required');

$coupon = getCouponById($id);
if (!$coupon) jsonError('Coupon not found', 404);

jsonResponse($coupon);
