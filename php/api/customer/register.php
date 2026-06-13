<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/email.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($name)) jsonError('Name is required');
if (empty($email)) jsonError('Email is required');
if (empty($password)) jsonError('Password is required');
if (strlen($password) < 6) jsonError('Password must be at least 6 characters');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email format');

$db = getDb();

$stmt = $db->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonError('This email is already registered', 409);
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare('INSERT INTO customers (name, email, password_hash, is_verified) VALUES (?, ?, ?, TRUE)');
$stmt->execute([$name, $email, $passwordHash]);
$customerId = (int)$db->lastInsertId();

// Link existing guest orders to this customer
$stmt = $db->prepare('UPDATE orders SET customer_id = ? WHERE customer_email = ? AND customer_id IS NULL');
$stmt->execute([$customerId, $email]);

// Update customer last_order_at from linked orders
if ($stmt->rowCount() > 0) {
    $db->prepare('UPDATE customers c
        SET c.last_order_at = (
            SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = ?
        )
        WHERE c.id = ?')
        ->execute([$customerId, $customerId]);
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
$stmt = $db->prepare('INSERT INTO customer_sessions (customer_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([
    $customerId,
    $token,
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    $expiresAt,
]);

// Send welcome email (best-effort, don't block registration)
try {
    sendTemplatedEmail('welcome', $email, [
        'customer_name' => $name,
        'store_url'     => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:4321'),
    ]);
} catch (\Exception $e) {
    // Silently ignore email errors
}

jsonResponse([
    'token' => $token,
    'customer' => [
        'id'    => $customerId,
        'name'  => $name,
        'email' => $email,
    ],
], 201);
