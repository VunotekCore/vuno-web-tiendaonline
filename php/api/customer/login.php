<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonError('Email and password are required');
}

$db = getDb();

$stmt = $db->prepare('SELECT id, name, email, password_hash FROM customers WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$customer = $stmt->fetch();

if (!$customer || !password_verify($password, $customer['password_hash'])) {
    jsonError('Invalid email or password', 401);
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
$stmt = $db->prepare('INSERT INTO customer_sessions (customer_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([
    $customer['id'],
    $token,
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    $expiresAt,
]);

jsonResponse([
    'token' => $token,
    'customer' => [
        'id'    => (int)$customer['id'],
        'name'  => $customer['name'],
        'email' => $customer['email'],
    ],
]);
