<?php
declare(strict_types=1);

use App\Controllers\NotificationController;
use App\Models\NotificationModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) {\jsonError('Unauthorized', 401);}

$controller = new NotificationController(new NotificationModel(\App\Config\Database::getConnection()));
$controller->list();
