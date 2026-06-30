<?php
declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Models\CustomerModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new CustomerController(
    new CustomerModel(\App\Config\Database::getConnection()),
);
$controller->orders();
