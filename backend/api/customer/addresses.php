<?php
declare(strict_types=1);

use App\Controllers\AddressController;
use App\Models\AddressModel;
use App\Models\CustomerModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new AddressController(
    new AddressModel(\App\Config\Database::getConnection()),
    new CustomerModel(\App\Config\Database::getConnection()),
);
$controller->handle();
