<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$id = (int)($_GET['id'] ?? 0);
$code = trim($_GET['code'] ?? '');

if (!$id && !$code) {
    jsonError('Provide id or code parameter');
}

if ($id) {
    $template = getEmailTemplateById($id);
} else {
    $template = getEmailTemplateByCode($code);
    if (!$template) {
        $fileTemplate = loadEmailTemplateFromFile($code);
        if ($fileTemplate) {
            jsonResponse($fileTemplate);
        }
    }
}

if (!$template) {
    jsonError('Template not found', 404);
}

jsonResponse($template);
