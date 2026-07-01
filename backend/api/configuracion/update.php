<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\SettingController;
use App\Models\SettingModel;

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) {
    jsonError('Unauthorized', 401);
}

$db = \App\Config\Database::getConnection();
$controller = new SettingController(new SettingModel($db));
$controller->update();
