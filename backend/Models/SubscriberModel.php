<?php
declare(strict_types=1);

namespace App\Models;

final class SubscriberModel
{
    public function __construct(private \PDO $db) {}

    /** @return array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int} */
    public function getAll(int $page = 1, int $limit = 10, string $search = '', ?int $isActive = null): array
    {
        $where = '1=1';
        $params = [];

        if ($search !== '') {
            $where .= ' AND email LIKE ?';
            $params[] = '%' . $search . '%';
        }

        if ($isActive !== null) {
            $where .= ' AND is_active = ?';
            $params[] = $isActive;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $execParams = $params;
        $execParams[] = $limit;
        $execParams[] = $offset;

        $stmt = $this->db->prepare(
            "SELECT id, email, is_active, subscribed_at, unsubscribed_at, created_at
             FROM newsletter_subscribers
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll();

        return [
            'items' => array_map(fn(array $r): array => [
                'id'              => (int) $r['id'],
                'email'           => $r['email'],
                'is_active'       => (bool) $r['is_active'],
                'subscribed_at'   => $r['subscribed_at'],
                'unsubscribed_at' => $r['unsubscribed_at'],
                'created_at'      => $r['created_at'],
            ], $rows),
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            'page'  => $page,
        ];
    }

    /** @return ?array<string, mixed> */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM newsletter_subscribers WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r !== false && $r !== null ? $r : null;
    }

    /** @return ?array<string, mixed> */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM newsletter_subscribers WHERE email = ?');
        $stmt->execute([$email]);
        $r = $stmt->fetch();
        return $r !== false && $r !== null ? $r : null;
    }

    public function insert(string $email): int
    {
        $this->db->prepare('INSERT INTO newsletter_subscribers (email) VALUES (?)')->execute([$email]);
        return (int) $this->db->lastInsertId();
    }

    public function setActive(int $id, bool $active): void
    {
        if ($active) {
            $this->db->prepare(
                'UPDATE newsletter_subscribers SET is_active = 1, unsubscribed_at = NULL, updated_at = NOW() WHERE id = ?'
            )->execute([$id]);
        } else {
            $this->db->prepare(
                'UPDATE newsletter_subscribers SET is_active = 0, unsubscribed_at = NOW(), updated_at = NOW() WHERE id = ?'
            )->execute([$id]);
        }
    }

    public function getActiveCount(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1');
        $cnt = $stmt->fetchColumn();
        return $cnt !== false ? (int) $cnt : 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function getActiveBatch(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email FROM newsletter_subscribers
             WHERE is_active = 1
             ORDER BY id ASC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array{email: string, is_active: bool, subscribed_at: ?string, unsubscribed_at: ?string}> */
    public function getAllForExport(): array
    {
        $rows = $this->db->query(
            'SELECT email, is_active, subscribed_at, unsubscribed_at
             FROM newsletter_subscribers
             ORDER BY created_at DESC'
        )->fetchAll();

        return array_map(fn(array $r): array => [
            'email'           => $r['email'],
            'is_active'       => (bool) $r['is_active'],
            'subscribed_at'   => $r['subscribed_at'],
            'unsubscribed_at' => $r['unsubscribed_at'],
        ], $rows);
    }

    public function logNotification(string $type, string $recipient, string $subject, string $message, string $status, ?string $refType = null, ?string $refId = null, ?string $error = null): void
    {
        $this->db->prepare(
            'INSERT INTO notification_log (type, recipient, subject, message, status, reference_type, reference_id, error, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$type, $recipient, $subject, $message, $status, $refType, $refId, $error]);
    }
}
