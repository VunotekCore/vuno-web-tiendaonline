<?php
declare(strict_types=1);

use App\Controllers\ProductController;
use App\Models\ProductModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
$controller = new ProductController(new ProductModel(\App\Config\Database::getConnection()));
$controller->get();
