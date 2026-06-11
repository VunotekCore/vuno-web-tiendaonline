<?php
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
$offset = ($page - 1) * $limit;

try {
    $db = getDb();

    if ($search) {
        $like = '%' . $search . '%';
        $countStmt = $db->prepare('SELECT COUNT(*) FROM newsletter_subscribers WHERE email LIKE ?');
        $countStmt->execute([$like]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT id, email, is_active, subscribed_at, unsubscribed_at, created_at
             FROM newsletter_subscribers
             WHERE email LIKE ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$like, $limit, $offset]);
    } else {
        $total = (int)$db->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn();

        $stmt = $db->query(
            'SELECT id, email, is_active, subscribed_at, unsubscribed_at, created_at
             FROM newsletter_subscribers
             ORDER BY created_at DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
    }

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
