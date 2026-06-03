<?php
/**
 * POST /api/imagekit/upload.php
 * Uploads an image to ImageKit
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/imagekit.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file uploaded or upload error');
}

$folder = $_POST['folder'] ?? 'products';

try {
    $result = uploadImage(
        $_FILES['file']['tmp_name'],
        $_FILES['file']['name'],
        $folder
    );
    jsonResponse($result);
} catch (\Exception $e) {
    jsonError($e->getMessage(), 500);
}
