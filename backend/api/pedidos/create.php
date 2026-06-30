<?php
declare(strict_types=1);

use App\Controllers\OrderController;
use App\Models\OrderModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new OrderController(new OrderModel(\App\Config\Database::getConnection()));
$controller->create();
