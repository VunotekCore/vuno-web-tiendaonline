<?php
/**
 * GET /api/pedidos/list.php - List orders with pagination, search, and status filter
 * Query params: ?limit=10&offset=0&search=maria&status=pending
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor', 'viewer');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : null;

$result = getOrders($limit, $offset, $search, $status);
jsonResponse($result);
