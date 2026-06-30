<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\NewsletterController;
use App\Models\EmailTemplateModel;
use App\Models\SubscriberModel;
use App\Services\EmailService;

setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}

$db = \App\Config\Database::getConnection();
$controller = new NewsletterController(
    new SubscriberModel($db),
    new EmailTemplateModel($db),
    new EmailService(new EmailTemplateModel($db), new SubscriberModel($db)),
);
$controller->sendCampaign();
