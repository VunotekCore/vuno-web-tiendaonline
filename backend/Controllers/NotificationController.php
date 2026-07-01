<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Traits\ApiResponse;

final class NotificationController
{
    use ApiResponse;

    public function __construct(private NotificationModel $model) {}

    private function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function list(): void
    {
        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $notifications = $this->model->getUnread($limit);
        $count = $this->model->getUnreadCount();
        $this->jsonResponse([
            'items' => $notifications,
            'count' => $count,
        ]);
    }

    public function count(): void
    {
        $count = $this->model->getUnreadCount();
        $this->jsonResponse(['count' => $count]);
    }

    public function read(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        $body = $this->input();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        if ($id <= 0) {
            $this->jsonError('Invalid notification ID');
        }
        $this->model->markAsRead($id);
        $this->jsonResponse(['success' => true]);
    }

    public function readAll(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        $affected = $this->model->markAllAsRead();
        $this->jsonResponse(['success' => true, 'affected' => $affected]);
    }
}
