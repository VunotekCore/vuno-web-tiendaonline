<?php
/**
 * GET /api/productos/list.php - List products with pagination and search
 * Query params: ?limit=10&offset=0&search=zapato
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$category = isset($_GET['category']) ? trim($_GET['category']) : null;

$result = getProducts($limit, $offset, $search, $category);
jsonResponse($result);
