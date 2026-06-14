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
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonError('Template ID is required');
}

$existing = getEmailTemplateById($id);
if (!$existing) {
    jsonError('Template not found', 404);
}

$data = [];
foreach (['code', 'name', 'subject', 'body_html', 'body_text', 'is_active'] as $key) {
    if (array_key_exists($key, $input)) {
        $data[$key] = $input[$key];
    }
}

if (empty($data)) {
    jsonError('No fields to update');
}

try {
    updateEmailTemplate($id, $data);
    logAdminAction('update', 'email_template', (string)$id, 'Email template updated: ' . ($data['code'] ?? $existing['code']));
    jsonResponse(['success' => true]);
} catch (\Exception $e) {
    jsonError('Failed to update template: ' . $e->getMessage(), 500);
}
