<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailTemplateModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Traits\ApiResponse;

final class EmailTemplateController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(
        private EmailTemplateModel $model,
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

    private function queryString(string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    private function queryInt(string $key): ?int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : null;
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

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $default = 0): int
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    // =========================================================================
    //  API methods
    // =========================================================================

    public function list(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
        $search = $this->queryString('search');

        $result = $this->model->getAll($page, $limit, $search);
        $this->jsonResponse($result);
    }

    public function get(): void
    {
        $id = $this->queryInt('id');
        $code = $this->queryString('code');

        if (($id === null || $id === 0) && $code === '') {
            $this->jsonError('Provide id or code parameter');
        }

        if ($id !== null && $id !== 0) {
            $template = $this->model->getById($id);
        } else {
            $template = $this->model->getByCode($code);
            if ($template === null) {
                $fileTemplate = EmailTemplateModel::loadFromFile($code);
                if ($fileTemplate !== null) {
                    $this->jsonResponse($fileTemplate);
                }
            }
        }

        if ($template === null) {
            $this->jsonError('Template not found', 404);
        }

        $this->jsonResponse($template);
    }

    public function create(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->getAuth()->requireRole('superadmin', 'admin');

        $body = $this->input();
        $code = $this->str($body, 'code');
        $name = $this->str($body, 'name');
        $subject = $this->str($body, 'subject');
        $bodyHtml = $this->str($body, 'body_html');

        if ($code === '' || $name === '' || $subject === '' || $bodyHtml === '') {
            $this->jsonError('Missing required fields: code, name, subject, body_html');
        }

        if (!preg_match('/^[a-z0-9_]+$/', $code)) {
            $this->jsonError('Code must be lowercase alphanumeric with underscores only');
        }

        try {
            $existing = $this->model->getByCode($code);
            if ($existing !== null) {
                $this->jsonError('A template with this code already exists');
            }

            $id = $this->model->insert([
                'code'      => $code,
                'name'      => $name,
                'subject'   => $subject,
                'body_html' => $bodyHtml,
                'body_text' => $this->str($body, 'body_text') ?: null,
                'is_active' => !empty($body['is_active']),
            ]);

            $this->getAuth()->logAction('create', 'email_template', (string) $id, "Email template created: {$code} - {$name}");
            $this->jsonResponse(['success' => true, 'id' => $id], 201);
        } catch (\Exception $e) {
            $this->jsonError('Failed to create template: ' . $e->getMessage(), 500);
        }
    }

    public function update(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->getAuth()->requireRole('superadmin', 'admin');

        $body = $this->input();
        $id = $this->int($body, 'id');

        if ($id === 0) {
            $this->jsonError('Template ID is required');
        }

        $existing = $this->model->getById($id);
        if ($existing === null) {
            $this->jsonError('Template not found', 404);
        }

        $data = [];
        foreach (['code', 'name', 'subject', 'body_html', 'body_text', 'is_active'] as $key) {
            if (array_key_exists($key, $body)) {
                $data[$key] = $body[$key];
            }
        }

        if ($data === []) {
            $this->jsonError('No fields to update');
        }

        try {
            $this->model->update($id, $data);
            $code = $data['code'] ?? $existing['code'];
            $this->getAuth()->logAction('update', 'email_template', (string) $id, "Email template updated: {$code}");
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to update template: ' . $e->getMessage(), 500);
        }
    }

    public function delete(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->getAuth()->requireRole('superadmin', 'admin');

        $body = $this->input();
        $id = $this->int($body, 'id');

        if ($id === 0) {
            $this->jsonError('Template ID is required');
        }

        try {
            $template = $this->model->getById($id);
            if ($template === null) {
                $this->jsonError('Template not found', 404);
            }

            $this->model->delete($id);
            $this->getAuth()->logAction('delete', 'email_template', (string) $id, 'Email template deleted: ' . ($template['code'] ?? (string) $id));
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonError('Failed to delete template: ' . $e->getMessage(), 500);
        }
    }

    public function preview(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $code = trim($this->str($body, 'code'));

        if ($code === '') {
            $this->jsonError('Template code is required');
        }

        $subjectOverride = $this->str($body, 'subject');
        $bodyOverride = $this->str($body, 'body_html');

        try {
            if ($bodyOverride !== '') {
                $subject = $subjectOverride ?: 'Test Subject';
                $storeVars = $this->emailService->getStoreTemplateVars();
                $testVars = $this->emailService->getPreviewTestVars();
                $allVars = array_merge($storeVars, $testVars);
                $replacements = [];
                foreach ($allVars as $key => $value) {
                    $replacements['{{' . $key . '}}'] = $value;
                }
                $bodyRendered = strtr($bodyOverride, $replacements);
                $subjectRendered = strtr($subject, $replacements);
            } else {
                $testVars = $this->emailService->getPreviewTestVars();
                $rendered = $this->emailService->renderTemplate($code, $testVars);
                $bodyRendered = $rendered['body_html'];
                $subjectRendered = $rendered['subject'];
            }

            $this->jsonResponse([
                'success'  => true,
                'subject'  => $subjectRendered,
                'body_html' => $bodyRendered,
            ]);
        } catch (\Exception $e) {
            $this->jsonError('Preview failed: ' . $e->getMessage(), 500);
        }
    }

    public function restore(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->getAuth()->requireRole('superadmin');

        $body = $this->input();
        $code = $this->str($body, 'code');

        if ($code === '') {
            $this->jsonError('Template code is required');
        }

        try {
            $fileTemplate = EmailTemplateModel::loadFromFile($code);
            if ($fileTemplate === null) {
                $this->jsonError("No file template found for code: {$code}", 404);
            }

            $existing = $this->model->getByCode($code);
            if ($existing !== null) {
                $this->model->update((int) $existing['id'], [
                    'subject'   => $fileTemplate['subject'],
                    'body_html' => $fileTemplate['body_html'],
                    'name'      => $fileTemplate['name'],
                    'is_active' => true,
                ]);
                $this->getAuth()->logAction('restore', 'email_template', (string) $existing['id'], 'Email template restored from file: ' . $code);
                $this->jsonResponse(['success' => true, 'action' => 'restored', 'id' => (int) $existing['id']]);
            } else {
                $id = $this->model->insert([
                    'code'      => $fileTemplate['code'],
                    'name'      => $fileTemplate['name'],
                    'subject'   => $fileTemplate['subject'],
                    'body_html' => $fileTemplate['body_html'],
                    'is_active' => true,
                ]);
                $this->getAuth()->logAction('restore', 'email_template', (string) $id, 'Email template created from file: ' . $code);
                $this->jsonResponse(['success' => true, 'action' => 'created', 'id' => $id]);
            }
        } catch (\Exception $e) {
            $this->jsonError('Failed to restore template: ' . $e->getMessage(), 500);
        }
    }

    public function seed(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->getAuth()->requireRole('superadmin');

        $files = EmailTemplateModel::getTemplateFiles();
        $seeded = 0;
        $updated = 0;
        $errors = [];

        foreach ($files as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);

            try {
                $fileTemplate = EmailTemplateModel::loadFromFile($code);
                if ($fileTemplate === null) {
                    $errors[] = "Could not parse: {$code}";
                    continue;
                }

                $existing = $this->model->getByCode($code);
                if ($existing !== null) {
                    $this->model->update((int) $existing['id'], [
                        'subject'   => $fileTemplate['subject'],
                        'body_html' => $fileTemplate['body_html'],
                        'name'      => $fileTemplate['name'],
                        'is_active' => true,
                    ]);
                    $updated++;
                } else {
                    $this->model->insert([
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

        $this->getAuth()->logAction('seed', 'email_templates', 'bulk', "Seeded {$seeded} new, updated {$updated} templates from files");

        $this->jsonResponse([
            'success' => true,
            'seeded'  => $seeded,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }
}
