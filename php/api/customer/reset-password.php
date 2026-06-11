<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

if (empty($token)) {
    jsonError('Reset token is required');
}
if (empty($password)) {
    jsonError('New password is required');
}
if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters');
}

try {
    $db = getDb();

    // Find valid token
    $stmt = $db->prepare(
        'SELECT id, email FROM password_resets
         WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        jsonError('Invalid or expired reset token', 400);
    }

    // Find the customer
    $stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([$reset['email']]);
    $customer = $stmt->fetch();

    if (!$customer) {
        jsonError('Account not found', 404);
    }

    // Update password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
    $stmt->execute([$passwordHash, $customer['id']]);

    // Mark token as used
    $stmt = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
    $stmt->execute([$reset['id']]);

    // Invalidate all existing sessions for this customer (force re-login)
    $db->prepare('DELETE FROM customer_sessions WHERE customer_id = ?')
        ->execute([$customer['id']]);

    jsonResponse(['message' => 'Password has been reset successfully. You can now log in.']);

} catch (\Exception $e) {
    jsonError('An error occurred. Please try again later.', 500);
}
