<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db = getDb();
    $rows = $db->query(
        'SELECT au.id, au.email, au.name, ar.code AS role_code, ar.name AS role_name, au.created_at
         FROM admin_users au
         JOIN admin_roles ar ON ar.id = au.role_id
         ORDER BY au.created_at ASC'
    )->fetchAll();

    jsonResponse([
        'items' => array_map(fn($r) => [
            'id'       => (int)$r['id'],
            'email'    => $r['email'],
            'name'     => $r['name'],
            'role'     => $r['role_code'],
            'roleName' => $r['role_name'],
            'createdAt' => date('c', strtotime($r['created_at'])),
        ], $rows),
        'roles' => $db->query('SELECT code, name FROM admin_roles ORDER BY id')->fetchAll(),
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['user_id']) || empty($data['role'])) jsonError('user_id and role are required');

    $db = getDb();
    $roleStmt = $db->prepare('SELECT id FROM admin_roles WHERE code = ?');
    $roleStmt->execute([$data['role']]);
    $roleId = $roleStmt->fetchColumn();
    if (!$roleId) jsonError('Invalid role');

    $db->prepare('UPDATE admin_users SET role_id = ? WHERE id = ?')->execute([$roleId, (int)$data['user_id']]);
    logAdminAction('update', 'admin_user', (string)$data['user_id'], 'Role changed to ' . $data['role']);
    jsonResponse(['success' => true]);
}
