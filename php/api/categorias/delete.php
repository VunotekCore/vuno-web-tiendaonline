<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'editor');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id'])) jsonError('ID is required');

deleteCategory($data['id']);
logAdminAction('delete', 'category', $data['id'], 'Deleted category: ' . ($data['name'] ?? $data['id']));
jsonResponse(['success' => true]);
