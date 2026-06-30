<?php
declare(strict_types=1);

use App\Controllers\OrderController;
use App\Models\OrderModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}
\requireRole('superadmin', 'editor', 'cashier');

$controller = new OrderController(new OrderModel(\App\Config\Database::getConnection()));
$controller->updateStatus();
