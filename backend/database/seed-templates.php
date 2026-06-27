<?php
declare(strict_types=1);

/**
 * Seed initial HTML email templates from file-based sources into the database.
 * Run: php backend/database/seed-templates.php
 *
 * Also available via admin: POST /api/email-templates/seed.php (superadmin only)
 */

require_once __DIR__ . '/../config.php';

use App\Models\EmailTemplateModel;

$db = \App\Config\Database::getConnection();
$model = new EmailTemplateModel($db);

$files = EmailTemplateModel::getTemplateFiles();

$count = 0;
$errors = [];

foreach ($files as $file) {
    $code = pathinfo($file, PATHINFO_FILENAME);

    try {
        $fileTemplate = EmailTemplateModel::loadFromFile($code);
        if (!$fileTemplate) {
            $errors[] = "Could not parse: {$code}";
            continue;
        }

        $existing = $model->getByCode($code);
        if ($existing) {
            $model->update((int) $existing['id'], [
                'subject'   => $fileTemplate['subject'],
                'body_html' => $fileTemplate['body_html'],
                'name'      => $fileTemplate['name'],
                'is_active' => true,
            ]);
            echo "Updated: {$code}\n";
        } else {
            $model->insert([
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
