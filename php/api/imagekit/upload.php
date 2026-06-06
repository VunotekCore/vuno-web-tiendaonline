<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/imagekit.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file uploaded or upload error');
}

$folder = $_POST['folder'] ?? 'products';

try {
    $settings = getSettings();
    $ikSettings = $settings['imagekit'] ?? [];
    $privateKey = $ikSettings['privateKey'] ?? $ikSettings['private_key'] ?? null;

    if (!$privateKey) {
        jsonError('ImageKit not configured', 500);
    }

    $result = uploadImage(
        $_FILES['file']['tmp_name'],
        $_FILES['file']['name'],
        $folder,
        $privateKey
    );
    jsonResponse($result);
} catch (\Throwable $e) {
    jsonError($e->getMessage(), 500);
}
