<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\CurrencyController;
use App\Models\CurrencyModel;

setCorsHeaders();

$controller = new CurrencyController(new CurrencyModel(Database::getConnection()));
$controller->list();
