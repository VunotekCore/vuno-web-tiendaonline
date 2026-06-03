<?php
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

if (empty($input['title']) || empty($input['slug']) || empty($input['content'])) {
    jsonError('Missing required fields: title, slug, content');
}

try {
    $id = createBlogPost([
        'title'          => $input['title'],
        'slug'           => $input['slug'],
        'excerpt'        => $input['excerpt'] ?? '',
        'content'        => $input['content'],
        'featured_image' => $input['featured_image'] ?? '',
        'author'         => $input['author'] ?? 'Ram;Lop',
        'status'         => $input['status'] ?? 'draft',
        'category_id'    => !empty($input['category_id']) ? (int)$input['category_id'] : null,
    ]);

    logAdminAction('create', 'blog_post', (string)$id, 'Blog post created: ' . $input['title']);
    jsonResponse(['success' => true, 'id' => $id]);
} catch (\Exception $e) {
    jsonError('Failed to create post: ' . $e->getMessage(), 500);
}
