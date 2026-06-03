<?php
/**
 * POST /api/admin/2fa/disable.php
 * Disable 2FA. Requires password + current TOTP code.
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
$password = $input['password'] ?? '';
$code     = $input['code'] ?? '';

if (empty($password) || empty($code)) {
    jsonError('Password and verification code are required');
}

$email = $_SESSION['admin_email'] ?? '';
if (!validateCredentials($email, $password)) {
    jsonError('Invalid password', 401);
}

$userId = (int)$_SESSION['admin_user_id'];
$db = getDb();

$stmt = $db->prepare('SELECT totp_secret, totp_enabled FROM admin_users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row || !$row['totp_enabled']) jsonError('2FA is not enabled');

if (!verifyTotpCode($row['totp_secret'], $code)) {
    jsonError('Invalid verification code', 401);
}

$db->prepare('UPDATE admin_users SET totp_secret = NULL, totp_enabled = 0, backup_codes = NULL WHERE id = ?')
   ->execute([$userId]);

logAdminAction('disable_2fa', 'auth', (string)$userId, '2FA disabled');

jsonResponse(['success' => true]);
