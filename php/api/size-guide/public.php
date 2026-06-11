<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$settings = getSettings();
$db = getDb();

$rows = [];
$stmt = $db->query('SELECT us_size, eu_size, uk_size, cm_size FROM size_guide_rows ORDER BY sort_order ASC');
while ($row = $stmt->fetch()) {
    $rows[] = [
        'us' => $row['us_size'],
        'eu' => $row['eu_size'],
        'uk' => $row['uk_size'],
        'cm' => $row['cm_size'],
    ];
}

jsonResponse([
    'title_es' => $settings['size_guide']['title_es'] ?? 'Guía de Talles',
    'title_en' => $settings['size_guide']['title_en'] ?? 'Size Guide',
    'footer_es' => $settings['size_guide']['footer_es'] ?? '',
    'footer_en' => $settings['size_guide']['footer_en'] ?? '',
    'rows' => $rows,
]);
