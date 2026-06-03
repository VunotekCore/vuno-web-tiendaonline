<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$lang = isset($_GET['lang']) ? trim($_GET['lang']) : null;

jsonResponse(getBlogCategories($lang));
