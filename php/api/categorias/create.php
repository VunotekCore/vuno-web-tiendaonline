<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/helpers.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['name'])) jsonError('Name is required');

$id = 'cat-' . slugify($data['name']);
$category = [
    'id' => $id,
    'name' => $data['name'],
    'slug' => slugify($data['name']),
];
saveCategory($category);
logAdminAction('create', 'category', $id, 'Created category: ' . $data['name']);
jsonResponse($category);
