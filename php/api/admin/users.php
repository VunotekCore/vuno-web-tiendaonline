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
$db = getDb();

if ($method === 'GET') {
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
    if (!$data) jsonError('Invalid request body');

    // Create user
    if (!empty($data['action']) && $data['action'] === 'create') {
        $email = trim($data['email'] ?? '');
        $name = trim($data['name'] ?? $email);
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'editor';

        if (!$email || !$password) jsonError('Email and password are required');
        if (strlen($password) < 6) jsonError('Password must be at least 6 characters');

        // Check duplicate
        $stmt = $db->prepare('SELECT id FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) jsonError('A user with this email already exists', 409);

        $roleStmt = $db->prepare('SELECT id FROM admin_roles WHERE code = ?');
        $roleStmt->execute([$role]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) jsonError('Invalid role');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO admin_users (email, name, password_hash, role_id) VALUES (?, ?, ?, ?)')
            ->execute([$email, $name, $hash, $roleId]);

        $userId = (int)$db->lastInsertId();
        logAdminAction('create', 'admin_user', (string)$userId, 'Created user: ' . $email);
        jsonResponse(['success' => true, 'id' => $userId]);
    }

    // Update role (existing behavior)
    if (!empty($data['user_id']) && !empty($data['role']) && empty($data['action'])) {
        $roleStmt = $db->prepare('SELECT id FROM admin_roles WHERE code = ?');
        $roleStmt->execute([$data['role']]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) jsonError('Invalid role');

        $db->prepare('UPDATE admin_users SET role_id = ? WHERE id = ?')->execute([$roleId, (int)$data['user_id']]);
        logAdminAction('update', 'admin_user', (string)$data['user_id'], 'Role changed to ' . $data['role']);
        jsonResponse(['success' => true]);
    }

    // Update user (name, email)
    if (!empty($data['action']) && $data['action'] === 'update') {
        $userId = (int)($data['user_id'] ?? 0);
        $newEmail = trim($data['email'] ?? '');
        $newName = trim($data['name'] ?? '');
        $newPassword = $data['password'] ?? '';

        if (!$userId) jsonError('user_id is required');

        $fields = [];
        $params = [];

        if ($newEmail) {
            // Check duplicate
            $stmt = $db->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ?');
            $stmt->execute([$newEmail, $userId]);
            if ($stmt->fetchColumn()) jsonError('Another user already has this email', 409);
            $fields[] = 'email = ?';
            $params[] = $newEmail;
        }
        if ($newName) {
            $fields[] = 'name = ?';
            $params[] = $newName;
        }
        if ($newPassword) {
            if (strlen($newPassword) < 6) jsonError('Password must be at least 6 characters');
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (empty($fields)) jsonError('No fields to update');

        $params[] = $userId;
        $db->prepare('UPDATE admin_users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        logAdminAction('update', 'admin_user', (string)$userId, 'Updated user');
        jsonResponse(['success' => true]);
    }

    jsonError('Invalid request');
}

if ($method === 'DELETE') {
    $userId = (int)($_GET['id'] ?? 0);
    if (!$userId) jsonError('user_id is required');

    // Prevent deleting own account
    if ($userId === (int)($_SESSION['admin_user_id'] ?? 0)) {
        jsonError('Cannot delete your own account', 403);
    }

    $stmt = $db->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    if ($stmt->rowCount() === 0) jsonError('User not found', 404);

    logAdminAction('delete', 'admin_user', (string)$userId, 'Deleted user');
    jsonResponse(['success' => true]);
}

if ($method === 'PUT') {
    parse_str(file_get_contents('php://input'), $putData);
    $data = json_decode(file_get_contents('php://input'), true) ?: $putData;

    $userId = (int)($data['user_id'] ?? 0);
    $newEmail = trim($data['email'] ?? '');
    $newName = trim($data['name'] ?? '');
    $newPassword = $data['password'] ?? '';
    $newRole = $data['role'] ?? '';

    if (!$userId) jsonError('user_id is required');

    $fields = [];
    $params = [];

    if ($newEmail) {
        $stmt = $db->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ?');
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetchColumn()) jsonError('Another user already has this email', 409);
        $fields[] = 'email = ?';
        $params[] = $newEmail;
    }
    if ($newName) {
        $fields[] = 'name = ?';
        $params[] = $newName;
    }
    if ($newPassword) {
        if (strlen($newPassword) < 6) jsonError('Password must be at least 6 characters');
        $fields[] = 'password_hash = ?';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    if ($newRole) {
        $roleStmt = $db->prepare('SELECT id FROM admin_roles WHERE code = ?');
        $roleStmt->execute([$newRole]);
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) jsonError('Invalid role');
        $fields[] = 'role_id = ?';
        $params[] = $roleId;
    }

    if (empty($fields)) jsonError('No fields to update');

    $params[] = $userId;
    $db->prepare('UPDATE admin_users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    logAdminAction('update', 'admin_user', (string)$userId, 'Updated user');
    jsonResponse(['success' => true]);
}
