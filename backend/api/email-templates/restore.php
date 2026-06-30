<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\EmailTemplateController;
use App\Models\EmailTemplateModel;
use App\Services\EmailService;
use App\Models\SubscriberModel;

setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}

$db = \App\Config\Database::getConnection();
$controller = new EmailTemplateController(
    new EmailTemplateModel($db),
    new EmailService(new EmailTemplateModel($db), new SubscriberModel($db)),
);
$controller->restore();
