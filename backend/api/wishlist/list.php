<?php
declare(strict_types=1);

use App\Controllers\WishlistController;
use App\Models\WishlistModel;
use App\Models\CustomerModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();

$controller = new WishlistController(
    new WishlistModel(\App\Config\Database::getConnection()),
    new CustomerModel(\App\Config\Database::getConnection()),
);
$controller->list();
