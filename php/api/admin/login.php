<?php
declare(strict_types=1);

/**
 * POST /api/admin/login.php - Authenticate admin with rate limiting
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

checkLoginRateLimit();

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonError('Email and password are required');
}

if (!validateCredentials($email, $password)) {
    recordLoginAttempt(false);
    jsonError('Invalid credentials', 401);
}

recordLoginAttempt(true);
clearLoginAttempts();
$result = loginAdmin($email);
jsonResponse($result);
