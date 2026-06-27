<?php
declare(strict_types=1);

use App\Controllers\CategoryController;
use App\Models\CategoryModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new CategoryController(new CategoryModel(\App\Config\Database::getConnection()));
$controller->list();
