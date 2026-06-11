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
if (!$data || empty($data['id']) || empty($data['name'])) jsonError('ID and name are required');
if (strlen($data['name']) > 200) jsonError('Name must be 200 characters or less');

$id = (int)$data['id'];
$existing = getBlogCategoryById($id);
if (!$existing) jsonError('Category not found', 404);

try {
    updateBlogCategory($id, [
        'name' => $data['name'],
        'slug' => slugify($data['name']),
    ]);
    logAdminAction('update', 'blog_category', (string)$id, 'Updated blog category: ' . $data['name']);
    jsonResponse(['success' => true]);
} catch (\Exception $e) {
    jsonError('Failed to update category: ' . $e->getMessage(), 500);
}
