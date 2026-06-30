<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\SizeGuideController;
use App\Models\SettingModel;
use App\Models\SizeGuideModel;

setCorsHeaders();

$db = Database::getConnection();
$controller = new SizeGuideController(
    new SizeGuideModel($db),
    new SettingModel($db),
);
$controller->public();
