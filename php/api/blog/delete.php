<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonError('Post ID is required');
}

try {
    $post = getBlogPostById($id);
    if (!$post) jsonError('Post not found', 404);

    deleteBlogPost($id);
    logAdminAction('delete', 'blog_post', (string)$id, 'Blog post deleted: ' . ($post['title'] ?? $id));
    jsonResponse(['success' => true]);
} catch (\Exception $e) {
    jsonError('Failed to delete post: ' . $e->getMessage(), 500);
}
