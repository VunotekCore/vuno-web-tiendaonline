<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Models\SettingModel;
use App\Controllers\ImageKitController;

setCorsHeaders();

$controller = new ImageKitController(new SettingModel(\App\Config\Database::getConnection()));
$controller->deleteImage();
