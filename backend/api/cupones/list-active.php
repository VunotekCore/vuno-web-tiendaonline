<?php
declare(strict_types=1);

/**
 * List active coupons for selection dropdowns.
 * GET /api/cupones/list-active.php
 * Returns coupons that are active and within their validity date range.
 * No auth required — used in checkout dropdowns.
 */

use App\Controllers\CouponController;
use App\Models\CouponModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new CouponController(new CouponModel(\App\Config\Database::getConnection()));
$controller->listActive();
