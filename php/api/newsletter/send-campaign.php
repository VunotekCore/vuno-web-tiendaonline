<?php
declare(strict_types=1);

/**
 * Send Newsletter Campaign (batch)
 * POST /api/newsletter/send-campaign.php
 *
 * Request:
 *   template_id      (int)    — ID from email_templates
 *   subject_override (string?) — optional subject override
 *   test_email       (string?) — if set, sends only to this email (test mode)
 *   offset           (int)     — batch offset (default 0)
 *   limit            (int)     — batch size (default 20, max 50)
 *
 * Response (test):
 *   { success, sent (0|1), failed (0|1), error?, done: true }
 *
 * Response (campaign):
 *   { success, sent, failed, errors[], total, next_offset, done }
 *   Client should call repeatedly with offset = next_offset until done = true.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();
startAdminSession();
if (!isAdminLoggedIn()) jsonError('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$templateId       = (int)($input['template_id'] ?? 0);
$subjectOverride  = trim($input['subject_override'] ?? '');
$testEmail        = trim($input['test_email'] ?? '');
$offset           = max(0, (int)($input['offset'] ?? 0));
$limit            = min(50, max(1, (int)($input['limit'] ?? 20)));

if (!$templateId) jsonError('template_id is required');

// Load template
$template = getEmailTemplateById($templateId);
if (!$template || !$template['is_active']) {
    jsonError('Template not found or inactive', 404);
}

$appUrl = env('APP_URL', 'http://localhost:4321');

// ---- Test mode: send to single email ----
if ($testEmail) {
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid test email');
    }

    $vars = [
        'subscriber_name' => 'Test',
        'unsubscribe_url' => $appUrl . '/api/email/unsubscribe.php?email=' . urlencode($testEmail),
        'subject'         => $subjectOverride ?: $template['subject'],
        'preheader'       => 'Test preview — ' . ($subjectOverride ?: $template['subject']),
        'title'           => '¡Hola! This is a test',
        'message'         => 'This is a test send from the admin panel. If you are reading this, your email configuration is working correctly.',
        'content_block'   => '',
    ];

    $result = sendTemplatedEmail($template['code'], $testEmail, $vars, null,
        $subjectOverride ?: null);

    // Log test send
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO notification_log (type, recipient, subject, message, status, error, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        'newsletter_test',
        $testEmail,
        $result['success'] ? ($subjectOverride ?: $template['subject']) : 'FAILED',
        'Test send of template: ' . $template['name'],
        $result['success'] ? 'sent' : 'failed',
        $result['error'] ?? null,
    ]);

    jsonResponse([
        'success' => $result['success'],
        'sent'    => $result['success'] ? 1 : 0,
        'failed'  => $result['success'] ? 0 : 1,
        'error'   => $result['error'] ?? null,
        'done'    => true,
    ]);
}

// ---- Campaign mode: send batch to active subscribers ----
try {
    $db = getDb();

    // Total active subscribers
    $total = (int)$db->query(
        'SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1'
    )->fetchColumn();

    // Fetch batch
    $stmt = $db->prepare(
        'SELECT id, email FROM newsletter_subscribers
         WHERE is_active = 1
         ORDER BY id ASC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    $subscribers = $stmt->fetchAll();

    if (empty($subscribers)) {
        jsonResponse([
            'success'     => true,
            'sent'        => 0,
            'failed'      => 0,
            'errors'      => [],
            'total'       => $total,
            'next_offset' => $offset,
            'done'        => true,
        ]);
    }

    $sent   = 0;
    $failed = 0;
    $errors = [];

    foreach ($subscribers as $sub) {
        $name = explode('@', $sub['email'])[0];
        $unsubscribeUrl = $appUrl . '/api/email/unsubscribe.php?email=' . urlencode($sub['email']);

        $vars = [
            'subscriber_name' => $name,
            'unsubscribe_url' => $unsubscribeUrl,
            'title'           => $template['name'] ?? 'Novedades',
            'message'         => '',
            'content_block'   => '',
            'preheader'       => 'Campaña: ' . ($subjectOverride ?: $template['subject']),
        ];

        $result = sendTemplatedEmail($template['code'], $sub['email'], $vars, null,
            $subjectOverride ?: null);

        // Log to notification_log
        $logStmt = $db->prepare(
            'INSERT INTO notification_log (type, recipient, subject, message, status, reference_type, reference_id, error, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $logStmt->execute([
            'newsletter_campaign',
            $sub['email'],
            $result['success'] ? ($subjectOverride ?: $template['subject']) : 'FAILED',
            'Campaign: ' . $template['name'],
            $result['success'] ? 'sent' : 'failed',
            'subscriber',
            (string)$sub['id'],
            $result['error'] ?? null,
        ]);

        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = ['email' => $sub['email'], 'error' => $result['error'] ?? 'Unknown'];
        }
    }

    $nextOffset = $offset + count($subscribers);
    $done = $nextOffset >= $total;

    // Log admin action on first batch only
    if ($offset === 0) {
        logAdminAction('send_newsletter', 'campaign', (string)$templateId,
            "Sent newsletter campaign using template: {$template['name']} ($total subscribers)");
    }

    jsonResponse([
        'success'     => true,
        'sent'        => $sent,
        'failed'      => $failed,
        'errors'      => $errors,
        'total'       => $total,
        'next_offset' => $nextOffset,
        'done'        => $done,
    ]);

} catch (\PDOException $e) {
    error_log("[Send Campaign] DB error: " . $e->getMessage());
    jsonError('Database error', 500);
} catch (\Throwable $e) {
    error_log("[Send Campaign] Error: " . $e->getMessage());
    jsonError('Something went wrong', 500);
}
