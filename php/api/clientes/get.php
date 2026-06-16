<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin', 'admin', 'editor', 'viewer');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) jsonError('Invalid customer ID', 400);

$customer = getCustomerById($id);
if (!$customer) jsonError('Customer not found', 404);

jsonResponse($customer);
