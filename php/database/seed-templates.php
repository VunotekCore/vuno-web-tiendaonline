<?php
/**
 * Seed initial HTML email templates into the database.
 * Run once: php php/database/seed-templates.php
 */

require_once __DIR__ . '/../../config.php';

$db = getDb();
$dir = __DIR__ . '/../../email-templates';
$files = glob($dir . '/*.html');

$count = 0;
foreach ($files as $file) {
    $code = pathinfo($file, PATHINFO_FILENAME);
    $raw = file_get_contents($file);

    // Extract subject from header
    $subject = '';
    if (preg_match('/^Subject:\s*(.+)$/m', $raw, $m)) $subject = trim($m[1]);

    // Remove header lines for body_html
    $bodyHtml = preg_replace('/^(Subject|Preheader):\s*.+\n+/m', '', $raw);
    $bodyHtml = trim($bodyHtml);

    $existing = $db->prepare('SELECT id FROM email_templates WHERE code = ?');
    $existing->execute([$code]);
    if ($existing->fetch()) {
        $db->prepare('UPDATE email_templates SET subject = ?, body_html = ?, updated_at = NOW() WHERE code = ?')
           ->execute([$subject, $bodyHtml, $code]);
        echo "Updated: {$code}\n";
    } else {
        $db->prepare('INSERT INTO email_templates (code, name, subject, body_html) VALUES (?, ?, ?, ?)')
           ->execute([$code, str_replace('_', ' ', ucfirst($code)), $subject, $bodyHtml]);
        echo "Seeded: {$code}\n";
    }
    $count++;
}

echo "Done. {$count} templates seeded.\n";
