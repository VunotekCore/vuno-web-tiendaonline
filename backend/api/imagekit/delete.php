<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Models\SettingModel;
use App\Services\ImageKitService;

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonError('Invalid JSON');

$fileId = $input['fileId'] ?? '';
if (empty($fileId)) jsonError('fileId required');

try {
    $settings = (new SettingModel(\App\Config\Database::getConnection()))->getAll();
    $ikSettings = $settings['imagekit'] ?? [];
    $privateKey = $ikSettings['privateKey'] ?? $ikSettings['private_key'] ?? null;
    if (!$privateKey) jsonError('ImageKit not configured', 500);

    $service = new ImageKitService($privateKey);
    $service->delete($fileId);
    logAdminAction('delete', 'imagekit', $fileId, 'Deleted image from ImageKit');
    jsonResponse(['success' => true]);
} catch (\RuntimeException $e) {
    jsonError('ImageKit delete failed: ' . $e->getMessage(), 502);
} catch (\Throwable $e) {
    jsonError('Internal error: ' . $e->getMessage(), 500);
}
