<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$status = $_GET['status'] ?? '';
$categoryId = max(0, (int)($_GET['category_id'] ?? 0));

jsonResponse(getBlogPosts($page, $limit, $status, $categoryId));
