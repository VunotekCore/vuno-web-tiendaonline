<?php
declare(strict_types=1);

/**
 * Seed/Reseed all email templates from file-based sources into DB.
 * Only superadmin can run this.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);
requireRole('superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$dir = __DIR__ . '/../../email-templates';
$files = glob($dir . '/*.html');
$seeded = 0;
$updated = 0;
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
            $updated++;
        } else {
            createEmailTemplate([
                'code'      => $fileTemplate['code'],
                'name'      => $fileTemplate['name'],
                'subject'   => $fileTemplate['subject'],
                'body_html' => $fileTemplate['body_html'],
                'is_active' => true,
            ]);
            $seeded++;
        }
    } catch (\Exception $e) {
        $errors[] = "{$code}: " . $e->getMessage();
    }
}

logAdminAction('seed', 'email_templates', 'bulk', "Seeded {$seeded} new, updated {$updated} templates from files");

jsonResponse([
    'success' => true,
    'seeded'  => $seeded,
    'updated' => $updated,
    'errors'  => $errors,
]);
