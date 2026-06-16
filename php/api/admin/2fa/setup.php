<?php
declare(strict_types=1);

/**
 * POST /api/admin/2fa/setup.php
 * Generate TOTP secret and provisioning URI for current admin.
 * Requires re-authentication with current password.
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password) || !validateCredentials($_SESSION['admin_email'] ?? '', $password)) {
    jsonError('Password confirmation is required', 401);
}

// Check if 2FA is already enabled
$userId = (int)$_SESSION['admin_user_id'];
$db = getDb();
$stmt = $db->prepare('SELECT totp_enabled FROM admin_users WHERE id = ?');
$stmt->execute([$userId]);
if ((bool)$stmt->fetchColumn()) {
    jsonError('2FA is already enabled. Disable it first to regenerate.');
}

// Generate new secret (always regenerate on setup)
    $settings = getSettings();
    $storeName = $settings['store']['name'] ?? 'Ram;Lop';

    $secret = generateTotpSecret();
    $email  = $_SESSION['admin_email'];
    $uri    = getTotpProvisioningUri($secret, $email, $storeName);

// Store secret temporarily (not yet enabled)
$db->prepare('UPDATE admin_users SET totp_secret = ? WHERE id = ?')
   ->execute([$secret, $userId]);

jsonResponse([
    'secret'  => chunk_split($secret, 4, ' '),
    'qrUri'   => $uri,
    'email'   => $email,
]);
