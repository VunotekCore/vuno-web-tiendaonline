<?php
/**
 * POST /api/admin/2fa/enable.php
 * Enable 2FA after verifying the first TOTP code.
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

$input = json_decode(file_get_contents('php://input'), true);
$code  = $input['code'] ?? '';

if (empty($code)) {
    jsonError('Verification code is required');
}

$userId = (int)$_SESSION['admin_user_id'];
$db = getDb();

$stmt = $db->prepare('SELECT totp_secret, totp_enabled FROM admin_users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) jsonError('User not found', 404);
if ($row['totp_enabled']) jsonError('2FA is already enabled');
if (empty($row['totp_secret'])) jsonError('No secret generated. Run setup first.');

if (!verifyTotpCode($row['totp_secret'], $code)) {
    jsonError('Invalid code. Try again.', 401);
}

// Generate and hash backup codes
$rawCodes = generateBackupCodes();
$hashedCodes = array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $rawCodes);

$db->prepare('UPDATE admin_users SET totp_enabled = 1, backup_codes = ? WHERE id = ?')
   ->execute([json_encode($hashedCodes), $userId]);

logAdminAction('enable_2fa', 'auth', (string)$userId, '2FA enabled');

jsonResponse([
    'success'     => true,
    'backupCodes' => $rawCodes,
]);
