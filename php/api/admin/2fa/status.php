<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

$userId = (int)($_SESSION['admin_user_id'] ?? 0);
$enabled = $userId > 0 && isTotpEnabledForUser($userId);

jsonResponse([
    'enabled' => $enabled,
]);
