<?php
declare(strict_types=1);

use App\Controllers\CartController;
use App\Models\CartModel;
use App\Models\CustomerModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$controller = new CartController(
    new CartModel(\App\Config\Database::getConnection()),
    new CustomerModel(\App\Config\Database::getConnection()),
);
$controller->sync();
