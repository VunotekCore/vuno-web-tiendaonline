<?php
declare(strict_types=1);

/**
 * Seed initial HTML email templates from file-based sources into the database.
 * Run: php php/database/seed-templates.php
 *
 * Also available via admin: POST /api/email-templates/seed.php (superadmin only)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/storage.php';

$dir = __DIR__ . '/../email-templates';
$files = glob($dir . '/*.html');

$count = 0;
$errors = [];

foreach ($files as $file) {
    $code = pathinfo($file, PATHINFO_FILENAME);

    try {
        $fileTemplate = loadEmailTemplateFromFile($code);
        if (!$fileTemplate) {
            $errors[] = "Could not parse: {$code}";
            continue;
        }

        $existing = getEmailTemplateByCode($code);
        if ($existing) {
            updateEmailTemplate((int)$existing['id'], [
                'subject'   => $fileTemplate['subject'],
                'body_html' => $fileTemplate['body_html'],
                'name'      => $fileTemplate['name'],
                'is_active' => true,
            ]);
            echo "Updated: {$code}\n";
        } else {
            createEmailTemplate([
                'code'      => $fileTemplate['code'],
                'name'      => $fileTemplate['name'],
                'subject'   => $fileTemplate['subject'],
                'body_html' => $fileTemplate['body_html'],
                'is_active' => true,
            ]);
            echo "Seeded: {$code}\n";
        }
        $count++;
    } catch (\Exception $e) {
        $errors[] = "{$code}: " . $e->getMessage();
    }
}

echo "Done. {$count} templates processed.\n";
if ($errors) {
    echo "Errors:\n";
    foreach ($errors as $e) echo "  - {$e}\n";
}
