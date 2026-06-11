<?php
/**
 * Export newsletter subscribers as CSV.
 * GET /api/suscriptores/export.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

try {
    $db = getDb();
    $rows = $db->query(
        'SELECT email, is_active, subscribed_at, unsubscribed_at
         FROM newsletter_subscribers
         ORDER BY created_at DESC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="suscriptores-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
    fputcsv($out, ['Email', 'Estado', 'Fecha Suscripcion', 'Fecha Desuscripcion']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['email'],
            $r['is_active'] ? 'Activo' : 'Inactivo',
            $r['subscribed_at'] ?: '',
            $r['unsubscribed_at'] ?: '',
        ]);
    }

    fclose($out);
    exit;

} catch (\PDOException $e) {
    error_log("[Suscriptores] export error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error exporting subscribers';
}
