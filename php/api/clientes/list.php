<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'admin', 'editor', 'viewer');

$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$search = $_GET['search'] ?? '';

$result = getCustomers($limit, $offset, $search);
jsonResponse($result);
