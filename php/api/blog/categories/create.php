<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/storage.php';
require_once __DIR__ . '/../../../includes/helpers.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['name'])) jsonError('Name is required');
if (strlen($data['name']) > 200) jsonError('Name must be 200 characters or less');

try {
    $id = createBlogCategory([
        'name' => $data['name'],
        'slug' => slugify($data['name']),
        'description' => $data['description'] ?? '',
    ]);
    logAdminAction('create', 'blog_category', (string)$id, 'Created blog category: ' . $data['name']);
    jsonResponse(['success' => true, 'id' => $id]);
} catch (\Exception $e) {
    jsonError('Failed to create category: ' . $e->getMessage(), 500);
}
