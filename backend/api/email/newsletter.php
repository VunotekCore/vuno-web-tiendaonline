<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Controllers\EmailController;
use App\Models\SubscriberModel;
use App\Models\EmailTemplateModel;
use App\Services\EmailService;

$db = \App\Config\Database::getConnection();
$controller = new EmailController(
    new SubscriberModel($db),
    new EmailService(new EmailTemplateModel($db), new SubscriberModel($db)),
);
$controller->newsletterSubscribe();
