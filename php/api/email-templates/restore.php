<?php
/**
 * Restore a single template from its file-based source into DB.
 * Only superadmin can run this.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if (!$code) {
    jsonError('Template code is required');
}

try {
    $fileTemplate = loadEmailTemplateFromFile($code);
    if (!$fileTemplate) {
        jsonError("No file template found for code: {$code}", 404);
    }

    $existing = getEmailTemplateByCode($code);
    if ($existing) {
        updateEmailTemplate((int)$existing['id'], [
            'subject'   => $fileTemplate['subject'],
            'body_html' => $fileTemplate['body_html'],
            'name'      => $fileTemplate['name'],
            'is_active' => true,
        ]);
        logAdminAction('restore', 'email_template', (string)$existing['id'], 'Email template restored from file: ' . $code);
        jsonResponse(['success' => true, 'action' => 'restored', 'id' => (int)$existing['id']]);
    } else {
        $id = createEmailTemplate([
            'code'      => $fileTemplate['code'],
            'name'      => $fileTemplate['name'],
            'subject'   => $fileTemplate['subject'],
            'body_html' => $fileTemplate['body_html'],
            'is_active' => true,
        ]);
        logAdminAction('restore', 'email_template', (string)$id, 'Email template created from file: ' . $code);
        jsonResponse(['success' => true, 'action' => 'created', 'id' => $id]);
    }
} catch (\Exception $e) {
    jsonError('Failed to restore template: ' . $e->getMessage(), 500);
}
