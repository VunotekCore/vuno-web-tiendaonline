<?php
declare(strict_types=1);


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

$imageId = $input['imageId'] ?? '';
if (empty($imageId)) jsonError('imageId required');

$db = getDb();

$stmt = $db->prepare('SELECT id, product_id, url, file_id FROM product_images WHERE id = ?');
$stmt->execute([$imageId]);
$image = $stmt->fetch();

if (!$image) {
    jsonError('Image not found', 404);
}

$settings = getSettings();
$ikSettings = $settings['imagekit'] ?? [];
$privateKey = $ikSettings['privateKey'] ?? $ikSettings['private_key'] ?? null;

if ($image['file_id'] && $privateKey) {
    try {
        deleteImageKitFile($image['file_id'], $privateKey);
    } catch (\RuntimeException $e) {
        jsonError('ImageKit delete failed: ' . $e->getMessage(), 502);
    }
}

$db->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
logAdminAction('delete', 'product_image', $imageId, 'Deleted product image from product_images and ImageKit');

jsonResponse(['success' => true]);
