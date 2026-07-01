<?php
declare(strict_types=1);

use App\Controllers\ProductController;
use App\Models\ProductModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor', 'cashier');

$controller = new ProductController(new ProductModel(\App\Config\Database::getConnection()));
$controller->list();
