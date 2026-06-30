<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\ContactController;
use App\Models\SettingModel;
use App\Services\EmailService;
use App\Models\EmailTemplateModel;
use App\Models\SubscriberModel;

setCorsHeaders();

$db = Database::getConnection();
$controller = new ContactController(
    new EmailService(new EmailTemplateModel($db), new SubscriberModel($db)),
    new SettingModel($db),
);
$controller->send();
