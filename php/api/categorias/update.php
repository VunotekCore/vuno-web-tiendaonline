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
if (!$data || empty($data['id']) || empty($data['name'])) jsonError('ID and name are required');

$existing = getCategoryById($data['id']);
if (!$existing) jsonError('Category not found', 404);

$existing['name'] = $data['name'];
$existing['slug'] = slugify($data['name']);
saveCategory($existing);
logAdminAction('update', 'category', $data['id'], 'Updated category: ' . $data['name'], [
    'from' => $existing['name'],
    'to' => $data['name'],
]);
jsonResponse($existing);
