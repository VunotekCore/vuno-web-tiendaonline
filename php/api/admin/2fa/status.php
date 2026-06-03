<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

$db = getDb();
$stmt = $db->prepare('SELECT totp_enabled FROM admin_users WHERE id = ?');
$stmt->execute([$_SESSION['admin_user_id']]);
$user = $stmt->fetch();

if (!$user) jsonError('User not found', 404);

jsonResponse([
    'enabled' => (bool) ($user['totp_enabled'] ?? false),
]);
