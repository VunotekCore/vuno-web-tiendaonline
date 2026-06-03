<?php
/**
 * POST /api/admin/2fa/verify.php
 * Verify TOTP code or backup code during login step-up.
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

startAdminSession();

if (empty($_SESSION['admin_totp_pending'])) {
    jsonError('No pending 2FA verification');
}

$input = json_decode(file_get_contents('php://input'), true);
$code  = $input['code'] ?? '';

if (empty($code)) {
    jsonError('Verification code is required');
}

$userId = $_SESSION['admin_totp_user_id'];
$db = getDb();

$stmt = $db->prepare('SELECT totp_secret, backup_codes FROM admin_users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    jsonError('User not found', 404);
}

$valid = false;

// Try TOTP code
if ($row['totp_secret'] && verifyTotpCode($row['totp_secret'], $code)) {
    $valid = true;
}

// Try backup code
if (!$valid && $row['backup_codes']) {
    $backupCodes = json_decode($row['backup_codes'], true) ?? [];
    foreach ($backupCodes as $i => $hashedCode) {
        if (password_verify($code, $hashedCode)) {
            unset($backupCodes[$i]);
            $db->prepare('UPDATE admin_users SET backup_codes = ? WHERE id = ?')
               ->execute([json_encode(array_values($backupCodes)), $userId]);
            $valid = true;
            break;
        }
    }
}

if (!$valid) {
    jsonError('Invalid verification code', 401);
}

// Complete login
session_regenerate_id(true);
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_email'] = $_SESSION['admin_totp_email'];
$_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
$_SESSION['admin_user_id'] = $userId;
unset($_SESSION['admin_totp_pending'], $_SESSION['admin_totp_email'], $_SESSION['admin_totp_user_id']);

logAdminAction('login', 'auth', (string)$userId, '2FA login: ' . $_SESSION['admin_email']);

jsonResponse(['success' => true]);
