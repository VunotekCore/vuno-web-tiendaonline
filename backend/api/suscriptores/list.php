<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\SubscriberController;
use App\Models\SubscriberModel;

setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}

$controller = new SubscriberController(new SubscriberModel(\App\Config\Database::getConnection()));
$controller->adminList();
