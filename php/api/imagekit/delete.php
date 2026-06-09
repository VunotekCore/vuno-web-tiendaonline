<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/imagekit.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonError('Invalid JSON');

$fileId = $input['fileId'] ?? '';
if (empty($fileId)) jsonError('fileId required');

$settings = getSettings();
$ikSettings = $settings['imagekit'] ?? [];
$privateKey = $ikSettings['privateKey'] ?? $ikSettings['private_key'] ?? null;

if (!$privateKey) {
    jsonError('ImageKit not configured', 500);
}

try {
    deleteImageKitFile($fileId, $privateKey);
    logAdminAction('delete', 'imagekit', $fileId, 'Deleted image from ImageKit');
    jsonResponse(['success' => true]);
} catch (\RuntimeException $e) {
    jsonError('ImageKit delete failed: ' . $e->getMessage(), 502);
} catch (\Exception $e) {
    jsonError('Internal error: ' . $e->getMessage(), 500);
}
