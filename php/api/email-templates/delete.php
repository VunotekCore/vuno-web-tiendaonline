<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonError('Template ID is required');
}

try {
    $template = getEmailTemplateById($id);
    if (!$template) jsonError('Template not found', 404);

    deleteEmailTemplate($id);
    logAdminAction('delete', 'email_template', (string)$id, 'Email template deleted: ' . ($template['code'] ?? $id));
    jsonResponse(['success' => true]);
} catch (\Exception $e) {
    jsonError('Failed to delete template: ' . $e->getMessage(), 500);
}
