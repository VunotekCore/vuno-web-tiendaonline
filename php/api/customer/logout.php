<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!empty($token)) {
    $db = getDb();
    $db->prepare('DELETE FROM customer_sessions WHERE token = ?')->execute([$token]);
}

jsonResponse(['success' => true]);
