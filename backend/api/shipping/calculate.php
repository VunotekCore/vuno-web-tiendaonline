<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\ShippingController;
use App\Models\SettingModel;

setCorsHeaders();

$controller = new ShippingController(new SettingModel(Database::getConnection()));
$controller->calculate();
