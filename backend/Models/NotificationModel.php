<?php
declare(strict_types=1);

namespace App\Models;

final class NotificationModel
{
    public function __construct(private \PDO $db) {}

    /** @return array<int, array<string, mixed>> */
    public function getUnread(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, type, title, message, reference_type, reference_id, is_read, created_at
             FROM admin_notifications
             WHERE is_read = 0
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public function getUnreadCount(): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0'
        );
        if ($stmt === false) {
            return 0;
        }
        /** @var int|string|false $count */
        $count = $stmt->fetchColumn();
        return $count !== false ? (int) $count : 0;
    }

    public function markAsRead(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_notifications SET is_read = 1 WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function markAllAsRead(): int
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function create(
        string $type,
        string $title,
        ?string $message = null,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): int {
        $this->db->prepare(
            'INSERT INTO admin_notifications (type, title, message, reference_type, reference_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$type, $title, $message, $referenceType, $referenceId]);
        return (int) $this->db->lastInsertId();
    }
}
