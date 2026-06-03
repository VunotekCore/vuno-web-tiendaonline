<?php
/**
 * GET /api/pedidos/get.php?id=X - Get single order by ID
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$id = $_GET['id'] ?? '';
if (!$id) jsonError('Order ID required');

$order = getOrderById($id);
if (!$order) jsonError('Order not found', 404);

jsonResponse($order);
