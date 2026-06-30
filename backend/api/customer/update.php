<?php
declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Models\CustomerModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$controller = new CustomerController(
    new CustomerModel(\App\Config\Database::getConnection()),
);
$controller->update();
