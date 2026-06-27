<?php
declare(strict_types=1);

use App\Controllers\CouponController;
use App\Models\CouponModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new CouponController(new CouponModel(\App\Config\Database::getConnection()));
$controller->validate();
