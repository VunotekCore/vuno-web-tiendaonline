<?php
declare(strict_types=1);

/**
 * List newsletter subscribers with pagination and search.
 * GET /api/suscriptores/list.php?page=1&limit=10&search=...
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$search = trim($_GET['search'] ?? '');
$isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;
$offset = ($page - 1) * $limit;

try {
    $db = getDb();

    $where = '1=1';
    $params = [];

    if ($search) {
        $where .= ' AND email LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if ($isActive !== null) {
        $where .= ' AND is_active = ?';
        $params[] = $isActive;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT id, email, is_active, subscribed_at, unsubscribed_at, created_at
         FROM newsletter_subscribers
         WHERE {$where}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $execParams = $params;
    $execParams[] = $limit;
    $execParams[] = $offset;
    $stmt->execute($execParams);

    $items = $stmt->fetchAll();

    jsonResponse([
        'items' => array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'email'          => $r['email'],
            'is_active'      => (bool)$r['is_active'],
            'subscribed_at'  => $r['subscribed_at'],
            'unsubscribed_at' => $r['unsubscribed_at'],
            'created_at'     => $r['created_at'],
        ], $items),
        'total' => $total,
        'pages' => (int)ceil($total / $limit),
        'page'  => $page,
    ]);

} catch (\PDOException $e) {
    error_log("[Suscriptores] list error: " . $e->getMessage());
    jsonError('Database error', 500);
}
