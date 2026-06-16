<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) jsonError('Invalid data');

$allowedKeys = ['store', 'receipt', 'imagekit', 'stripe', 'transfer', 'smtp', 'currency', 'landing', 'policies', 'size_guide', 'seo', 'shipping', 'tax'];
$settings = getSettings();
foreach ($allowedKeys as $key) {
    if (isset($input[$key]) && is_array($input[$key])) {
        $settings[$key] = $input[$key];
    }
}
saveSettings($settings);
jsonResponse(['success' => true, 'settings' => $settings]);
