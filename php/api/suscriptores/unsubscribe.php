<?php
declare(strict_types=1);

/**
 * Manually unsubscribe a newsletter subscriber.
 * POST /api/suscriptores/unsubscribe.php
 * Body: { "id": 123 }
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonError('Subscriber ID is required');
}

try {
    $db = getDb();

    $stmt = $db->prepare('SELECT id, email, is_active FROM newsletter_subscribers WHERE id = ?');
    $stmt->execute([$id]);
    $subscriber = $stmt->fetch();

    if (!$subscriber) {
        jsonError('Subscriber not found', 404);
    }

    if (!$subscriber['is_active']) {
        jsonResponse(['success' => true, 'message' => 'Subscriber was already inactive.']);
    }

    $stmt = $db->prepare('UPDATE newsletter_subscribers SET is_active = 0, unsubscribed_at = NOW(), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);

    logAdminAction('unsubscribe', 'newsletter_subscribers', (string)$id, "Unsubscribed: {$subscriber['email']}");

    jsonResponse(['success' => true, 'message' => 'Subscriber unsubscribed successfully.']);

} catch (\PDOException $e) {
    error_log("[Suscriptores] unsubscribe error: " . $e->getMessage());
    jsonError('Database error', 500);
}
