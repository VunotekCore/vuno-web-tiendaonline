<?php
declare(strict_types=1);

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

if (empty($input['code']) || empty($input['name']) || empty($input['subject']) || !isset($input['body_html'])) {
    jsonError('Missing required fields: code, name, subject, body_html');
}

if (!preg_match('/^[a-z0-9_]+$/', $input['code'])) {
    jsonError('Code must be lowercase alphanumeric with underscores only');
}

try {
    $existing = getEmailTemplateByCode($input['code']);
    if ($existing) {
        jsonError('A template with this code already exists');
    }

    $id = createEmailTemplate([
        'code'      => $input['code'],
        'name'      => $input['name'],
        'subject'   => $input['subject'],
        'body_html' => $input['body_html'],
        'body_text' => $input['body_text'] ?? null,
        'is_active' => !empty($input['is_active']),
    ]);

    logAdminAction('create', 'email_template', (string)$id, 'Email template created: ' . $input['code'] . ' - ' . $input['name']);
    jsonResponse(['success' => true, 'id' => $id]);
} catch (\Exception $e) {
    jsonError('Failed to create template: ' . $e->getMessage(), 500);
}
