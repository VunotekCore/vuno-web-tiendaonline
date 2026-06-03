<?php
/**
 * POST /api/productos/delete.php - Delete a product
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) jsonError('Product ID required');

deleteProduct($input['id']);
logAdminAction('delete', 'product', $input['id'], 'Deleted product: ' . ($input['name'] ?? $input['id']));
jsonResponse(['success' => true]);
