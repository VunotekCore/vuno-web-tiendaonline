<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email address');
}

// Always return the same message regardless of whether the email exists
$responseMsg = 'If an account with that email exists, you will receive a password reset link.';

try {
    $db = getDb();
    $stmt = $db->prepare('SELECT id, name FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if (!$customer) {
        // Don't reveal if email exists
        jsonResponse(['message' => $responseMsg]);
    }

    // Delete any existing unused tokens for this email
    $db->prepare('DELETE FROM password_resets WHERE email = ? AND used_at IS NULL')
        ->execute([$email]);

    // Generate new token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $db->prepare(
        'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$email, $token, $expiresAt]);

    // Build reset URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:4321';
    $baseUrl = $scheme . '://' . $host;

    // Detect language from request or default to es
    $lang = 'es';
    if (isset($input['lang']) && in_array($input['lang'], ['es', 'en'], true)) {
        $lang = $input['lang'];
    }

    $resetUrl = $baseUrl . '/' . $lang . '/reset-password?token=' . urlencode($token);

    // Send email (gracefully skip if SMTP not configured)
    $sent = sendTemplatedEmail('password_reset', $email, [
        'customer_name' => $customer['name'],
        'reset_url'     => $resetUrl,
    ]);

    jsonResponse(['message' => $responseMsg, 'debug_token' => $sent ? null : $token]);
    // In production, remove debug_token. Kept here for testing without SMTP.

} catch (\Exception $e) {
    jsonError('An error occurred. Please try again later.', 500);
}
