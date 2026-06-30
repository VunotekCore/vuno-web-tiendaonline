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

$stats = (new OrderModel(\App\Config\Database::getConnection()))->getDashboardStats();
\jsonResponse($stats);
