<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$categoryId = max(0, (int)($_GET['category_id'] ?? 0));
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : null;

// Admin can filter by status; public only sees published
$status = 'published';
if (isset($_GET['status'])) {
    startAdminSession();
    if (isAdminLoggedIn()) {
        $status = $_GET['status'];
    }
}

jsonResponse(getBlogPosts($page, $limit, $status, $categoryId, $lang));
