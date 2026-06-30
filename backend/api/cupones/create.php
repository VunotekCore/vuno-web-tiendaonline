<?php
declare(strict_types=1);

use App\Controllers\CouponController;
use App\Models\CouponModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}
\requireRole('superadmin', 'editor');

$controller = new CouponController(new CouponModel(\App\Config\Database::getConnection()));
$controller->create();
