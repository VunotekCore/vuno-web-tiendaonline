<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$slug = $_GET['slug'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($slug) {
    $post = getBlogPostBySlug($slug);
} elseif ($id) {
    $post = getBlogPostById($id);
} else {
    jsonError('Provide id or slug parameter');
}

if (!$post) {
    jsonError('Post not found', 404);
}

jsonResponse($post);
