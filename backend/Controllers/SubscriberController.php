<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SubscriberModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;

final class SubscriberController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(private SubscriberModel $model) {}

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

    private function queryInt(string $key): ?int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : null;
    }

    private function str(string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    public function adminList(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
        $search = $this->str('search');
        /** @var mixed $rawActive */
        $rawActive = $_GET['is_active'] ?? null;
        $isActive = is_numeric($rawActive) ? (int) $rawActive : null;

        try {
            $result = $this->model->getAll($page, $limit, $search, $isActive);
            $this->jsonResponse($result);
        } catch (\PDOException $e) {
            \error_log("[Suscriptores] list error: " . $e->getMessage());
            $this->jsonError('Database error', 500);
        }
    }

    public function export(): never
    {
        try {
            $rows = $this->model->getAllForExport();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="suscriptores-' . date('Y-m-d') . '.csv"');

            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Email', 'Estado', 'Fecha Suscripcion', 'Fecha Desuscripcion']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['email'],
                    $r['is_active'] ? 'Activo' : 'Inactivo',
                    $r['subscribed_at'] ?? '',
                    $r['unsubscribed_at'] ?? '',
                ]);
            }

            fclose($out);
            exit;
        } catch (\PDOException $e) {
            \error_log("[Suscriptores] export error: " . $e->getMessage());
            http_response_code(500);
            echo 'Error exporting subscribers';
            exit;
        }
    }

    public function adminUnsubscribe(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->input();
        $id = (int) ($body['id'] ?? 0);

        if ($id === 0) {
            $this->jsonError('Subscriber ID is required');
        }

        try {
            $subscriber = $this->model->getById($id);
            if ($subscriber === null) {
                $this->jsonError('Subscriber not found', 404);
            }

            if (empty($subscriber['is_active'])) {
                $this->jsonResponse(['success' => true, 'message' => 'Subscriber was already inactive.']);
                return;
            }

            $this->model->setActive($id, false);
            $this->getAuth()->logAction('unsubscribe', 'newsletter_subscribers', (string) $id, "Unsubscribed: {$subscriber['email']}");
            $this->jsonResponse(['success' => true, 'message' => 'Subscriber unsubscribed successfully.']);
        } catch (\PDOException $e) {
            \error_log("[Suscriptores] unsubscribe error: " . $e->getMessage());
            $this->jsonError('Database error', 500);
        }
    }
}
