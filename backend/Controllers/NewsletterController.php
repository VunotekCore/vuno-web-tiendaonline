<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\SubscriberModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Traits\ApiResponse;

final class NewsletterController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(
        private SubscriberModel $subscriberModel,
        private EmailTemplateModel $templateModel,
        private EmailService $emailService,
    ) {}

    private function getAuth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService(new UserModel(\App\Config\Database::getConnection()));
        }
        return $this->auth;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    public function sendCampaign(): void
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!is_string($method) || $method !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $templateId = (int) ($body['template_id'] ?? 0);
        $subjectOverride = trim($this->str($body, 'subject_override'));
        $testEmail = trim($this->str($body, 'test_email'));
        $offset = max(0, (int) ($body['offset'] ?? 0));
        $limit = min(50, max(1, (int) ($body['limit'] ?? 20)));

        if ($templateId === 0) {
            $this->jsonError('template_id is required');
        }

        // Load template
        $template = $this->templateModel->getById($templateId);
        if ($template === null || empty($template['is_active'])) {
            $this->jsonError('Template not found or inactive', 404);
        }

        $appUrl = \env('APP_URL', 'http://localhost:4321');

        // ---- Test mode: send to single email ----
        if ($testEmail !== '') {
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $this->jsonError('Invalid test email');
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

            $result = $this->emailService->sendTemplatedEmail(
                $template['code'],
                $testEmail,
                $vars,
                null,
                $subjectOverride ?: null
            );

            $this->subscriberModel->logNotification(
                'newsletter_test',
                $testEmail,
                $result['success'] ? ($subjectOverride ?: $template['subject']) : 'FAILED',
                'Test send of template: ' . $template['name'],
                $result['success'] ? 'sent' : 'failed',
                error: $result['error'] ?? null,
            );

            $this->jsonResponse([
                'success' => $result['success'],
                'sent'    => $result['success'] ? 1 : 0,
                'failed'  => $result['success'] ? 0 : 1,
                'error'   => $result['error'] ?? null,
                'done'    => true,
            ]);
            return;
        }

        // ---- Campaign mode: send batch to active subscribers ----
        try {
            $total = $this->subscriberModel->getActiveCount();
            $subscribers = $this->subscriberModel->getActiveBatch($limit, $offset);

            if ($subscribers === []) {
                $this->jsonResponse([
                    'success'     => true,
                    'sent'        => 0,
                    'failed'      => 0,
                    'errors'      => [],
                    'total'       => $total,
                    'next_offset' => $offset,
                    'done'        => true,
                ]);
                return;
            }

            $sent = 0;
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

                $result = $this->emailService->sendTemplatedEmail(
                    $template['code'],
                    $sub['email'],
                    $vars,
                    null,
                    $subjectOverride ?: null
                );

                $this->subscriberModel->logNotification(
                    'newsletter_campaign',
                    $sub['email'],
                    $result['success'] ? ($subjectOverride ?: $template['subject']) : 'FAILED',
                    'Campaign: ' . $template['name'],
                    $result['success'] ? 'sent' : 'failed',
                    'subscriber',
                    (string) $sub['id'],
                    $result['error'] ?? null,
                );

                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = ['email' => $sub['email'], 'error' => $result['error'] ?? 'Unknown'];
                }
            }

            $nextOffset = $offset + count($subscribers);
            $done = $nextOffset >= $total;

            if ($offset === 0) {
                $this->getAuth()->logAction('send_newsletter', 'campaign', (string) $templateId,
                    "Sent newsletter campaign using template: {$template['name']} ({$total} subscribers)");
            }

            $this->jsonResponse([
                'success'     => true,
                'sent'        => $sent,
                'failed'      => $failed,
                'errors'      => $errors,
                'total'       => $total,
                'next_offset' => $nextOffset,
                'done'        => $done,
            ]);
        } catch (\PDOException $e) {
            \error_log("[Send Campaign] DB error: " . $e->getMessage());
            $this->jsonError('Database error', 500);
        } catch (\Throwable $e) {
            \error_log("[Send Campaign] Error: " . $e->getMessage());
            $this->jsonError('Something went wrong', 500);
        }
    }

    /** @param array<string, mixed> $data */
    private function str(array $data, string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        if (is_string($val)) return $val;
        if (is_scalar($val)) return (string) $val;
        return $default;
    }
}
