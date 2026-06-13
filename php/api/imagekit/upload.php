<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/imagekit.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$folder = $_POST['folder'] ?? 'products';

// Receipt uploads from checkout: public with validation
// All other folders require admin auth
if ($folder === 'receipts') {
    // Rate limiting by IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'receipt_upload_' . str_replace('.', '_', $ip);
    $rateFile = sys_get_temp_dir() . '/' . $rateKey;
    $rateLimit = 5;
    $rateWindow = 3600;

    if (file_exists($rateFile)) {
        $rateData = json_decode(file_get_contents($rateFile), true) ?: ['count' => 0, 'time' => 0];
        if (time() - $rateData['time'] < $rateWindow) {
            if ($rateData['count'] >= $rateLimit) {
                jsonError('Demasiados intentos. Intenta de nuevo más tarde.', 429);
            }
            $rateData['count']++;
        } else {
            $rateData = ['count' => 1, 'time' => time()];
        }
    } else {
        $rateData = ['count' => 1, 'time' => time()];
    }
    file_put_contents($rateFile, json_encode($rateData), LOCK_EX);

    // Validate file type for receipts
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $mime = mime_content_type($_FILES['file']['tmp_name'] ?? '');
    if (!in_array($mime, $allowedMimes, true)) {
        jsonError('Tipo de archivo no permitido. Solo JPG, PNG, WebP o PDF.', 400);
    }

    $maxSize = 10 * 1024 * 1024;
    if ($_FILES['file']['size'] > $maxSize) {
        jsonError('El archivo excede el tamaño máximo de 10MB.', 400);
    }
} else {
    if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
    requireRole('superadmin', 'editor');
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file uploaded or upload error');
}

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
