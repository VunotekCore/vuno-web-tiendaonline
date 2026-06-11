<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) jsonError('Invalid JSON', 400);

$rows = $input['rows'] ?? [];
if (!is_array($rows)) jsonError('rows must be an array', 400);

$db = getDb();
$db->beginTransaction();
try {
    $db->exec('DELETE FROM size_guide_rows');
    $stmt = $db->prepare('INSERT INTO size_guide_rows (us_size, eu_size, uk_size, cm_size, sort_order) VALUES (?, ?, ?, ?, ?)');
    $order = 1;
    foreach ($rows as $r) {
        $stmt->execute([
            $r['us'] ?? '',
            $r['eu'] ?? '',
            $r['uk'] ?? '',
            $r['cm'] ?? '',
            $order++,
        ]);
    }
    $db->commit();
    jsonResponse(['ok' => true, 'count' => count($rows)]);
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Database error: ' . $e->getMessage(), 500);
}
