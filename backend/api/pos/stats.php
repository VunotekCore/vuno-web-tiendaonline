<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\PosController;
use App\Models\OrderModel;

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor', 'cashier');

$controller = new PosController(new OrderModel(Database::getConnection()));
$controller->stats();
