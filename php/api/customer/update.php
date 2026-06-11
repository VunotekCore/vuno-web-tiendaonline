<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (empty($token)) {
    jsonError('No token provided', 401);
}

$db = getDb();

$stmt = $db->prepare(
    'SELECT c.id, c.name, c.email, c.password_hash
     FROM customer_sessions cs
     JOIN customers c ON c.id = cs.customer_id
     WHERE cs.token = ? AND cs.expires_at > NOW()
     LIMIT 1'
);
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    jsonError('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$customerId = (int)$customer['id'];

if (isset($input['name'])) {
    $name = trim($input['name']);
    if (empty($name)) jsonError('Name cannot be empty');
    $db->prepare('UPDATE customers SET name = ? WHERE id = ?')->execute([$name, $customerId]);
}

if (isset($input['email'])) {
    $email = trim($input['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email format');
    $stmt = $db->prepare('SELECT id FROM customers WHERE email = ? AND id != ? LIMIT 1');
    $stmt->execute([$email, $customerId]);
    if ($stmt->fetch()) jsonError('Email already in use', 409);
    $db->prepare('UPDATE customers SET email = ? WHERE id = ?')->execute([$email, $customerId]);
}

if (isset($input['currentPassword']) && isset($input['newPassword'])) {
    if (!password_verify($input['currentPassword'], $customer['password_hash'])) {
        jsonError('Current password is incorrect', 401);
    }
    if (strlen($input['newPassword']) < 6) jsonError('New password must be at least 6 characters');
    $newHash = password_hash($input['newPassword'], PASSWORD_BCRYPT);
    $db->prepare('UPDATE customers SET password_hash = ? WHERE id = ?')->execute([$newHash, $customerId]);
}

// Reload updated customer
$stmt = $db->prepare('SELECT id, name, email, created_at, last_order_at FROM customers WHERE id = ?');
$stmt->execute([$customerId]);
$updated = $stmt->fetch();

jsonResponse([
    'customer' => [
        'id'          => (int)$updated['id'],
        'name'        => $updated['name'],
        'email'       => $updated['email'],
        'memberSince' => date('Y-m-d', strtotime($updated['created_at'])),
        'lastOrderAt' => $updated['last_order_at'] ? date('Y-m-d', strtotime($updated['last_order_at'])) : null,
    ],
]);
