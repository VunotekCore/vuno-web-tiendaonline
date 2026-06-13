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
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonError('Post ID is required');
}

$data = [];
foreach (['title', 'slug', 'excerpt', 'thumbnail_image', 'content', 'featured_image', 'author', 'status', 'category_id', 'meta_title', 'meta_description'] as $key) {
    if (array_key_exists($key, $input)) {
        $data[$key] = $input[$key];
    }
}

if (empty($data)) {
    jsonError('No fields to update');
}

// Convert category_id to int if present
if (array_key_exists('category_id', $data)) {
    $data['category_id'] = !empty($data['category_id']) ? (int)$data['category_id'] : null;
}

try {
    updateBlogPost($id, $data);
    logAdminAction('update', 'blog_post', (string)$id, 'Blog post updated: ' . ($data['title'] ?? $id));
    jsonResponse(['success' => true]);
} catch (\Exception $e) {
    jsonError('Failed to update post: ' . $e->getMessage(), 500);
}
