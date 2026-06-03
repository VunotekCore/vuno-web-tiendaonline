<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : null;

jsonResponse(getCategories($limit, $offset, $lang));
