<?php
declare(strict_types=1);

namespace App\Models;

final class CouponModel
{
    public function __construct(private \PDO $db) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getAll(?int $limit, ?int $offset, ?string $search): array
    {
        $conditions = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = '(code LIKE ? OR description LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM coupons{$where}");
        $countStmt->execute($conditions !== [] ? $params : []);
        /** @var int $total */
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM coupons{$where} ORDER BY created_at DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Fetch use counts
        $couponIds = array_map(fn(array $r): int => (int) $r['id'], $rows);
        $useCounts = $this->getUseCounts($couponIds);

        $items = array_map(fn(array $r): array => [
            'id' => (int) $r['id'],
            'code' => $r['code'],
            'description' => $r['description'],
            'discount_type' => $r['discount_type'],
            'discount_value' => (float) $r['discount_value'],
            'min_order_amount' => $r['min_order_amount'] !== null ? (float) $r['min_order_amount'] : null,
            'max_uses' => $r['max_uses'] !== null ? (int) $r['max_uses'] : null,
            'max_uses_per_customer' => $r['max_uses_per_customer'] !== null ? (int) $r['max_uses_per_customer'] : null,
            'is_active' => (bool) $r['is_active'],
            'starts_at' => $r['starts_at'],
            'expires_at' => $r['expires_at'],
            'created_at' => $r['created_at'],
            'use_count' => $useCounts[(int) $r['id']] ?? 0,
        ], $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if ($r === false || $r === null) {
            return null;
        }
        return $this->mapRow($r);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        if ($r === false || $r === null) {
            return null;
        }
        return $this->mapRow($r);
    }

    /**
     * @return array<int, array{id: int, code: string, discount_type: string, discount_value: float, description: ?string}>
     */
    public function getActiveCoupons(): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'SELECT id, code, discount_type, discount_value, description
             FROM coupons
             WHERE is_active = 1
               AND (starts_at IS NULL OR starts_at <= ?)
               AND (expires_at IS NULL OR expires_at >= ?)
             ORDER BY code ASC'
        );
        $stmt->execute([$now, $now]);
        /** @var array<int, array{id: int, code: string, discount_type: string, discount_value: float, description: ?string}> $rows */
        $rows = $stmt->fetchAll();
        return array_map(fn(array $r): array => [
            'id' => (int) $r['id'],
            'code' => $r['code'],
            'discount_type' => $r['discount_type'],
            'discount_value' => (float) $r['discount_value'],
            'description' => $r['description'],
        ], $rows);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCoupon(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_uses, max_uses_per_customer, is_active, starts_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['code'],
            $data['description'] ?? '',
            $data['discount_type'],
            $data['discount_value'],
            $data['min_order_amount'] ?? null,
            $data['max_uses'] ?? null,
            $data['max_uses_per_customer'] ?? null,
            ($data['is_active'] ?? true) ? 1 : 0,
            $data['starts_at'] ?? null,
            $data['expires_at'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCoupon(array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE coupons SET
                code = ?, description = ?, discount_type = ?, discount_value = ?,
                min_order_amount = ?, max_uses = ?, max_uses_per_customer = ?,
                is_active = ?, starts_at = ?, expires_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['code'],
            $data['description'] ?? '',
            $data['discount_type'],
            $data['discount_value'],
            $data['min_order_amount'] ?? null,
            $data['max_uses'] ?? null,
            $data['max_uses_per_customer'] ?? null,
            ($data['is_active'] ?? true) ? 1 : 0,
            $data['starts_at'] ?? null,
            $data['expires_at'] ?? null,
            $data['id'],
        ]);
    }

    public function deleteCoupon(int $id): void
    {
        $this->db->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
    }

    public function getUsageCount(int $couponId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?');
        $stmt->execute([$couponId]);
        return (int) $stmt->fetchColumn();
    }

    public function getCustomerUsageCount(int $couponId, string $customerEmail): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND customer_email = ?');
        $stmt->execute([$couponId, $customerEmail]);
        return (int) $stmt->fetchColumn();
    }

    public function findOrderDbIdByOrderNumber(string $orderNumber): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM orders WHERE order_number = ?');
        $stmt->execute([$orderNumber]);
        /** @var int|false $id */
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public function recordUsage(int $couponId, int $orderDbId, string $customerEmail, float $discountAmount): void
    {
        $this->db->prepare(
            'INSERT INTO coupon_usage (coupon_id, order_id, customer_email, discount_amount) VALUES (?, ?, ?, ?)'
        )->execute([$couponId, $orderDbId, $customerEmail, $discountAmount]);
    }

    /**
     * @param array<int, int> $couponIds
     * @return array<int, int>
     */
    private function getUseCounts(array $couponIds): array
    {
        $useCounts = [];
        if ($couponIds === []) {
            return $useCounts;
        }
        $placeholders = implode(',', array_fill(0, count($couponIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT coupon_id, COUNT(*) AS cnt FROM coupon_usage WHERE coupon_id IN ({$placeholders}) GROUP BY coupon_id"
        );
        $stmt->execute($couponIds);
        while ($row = $stmt->fetch()) {
            $useCounts[(int) $row['coupon_id']] = (int) $row['cnt'];
        }
        return $useCounts;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function mapRow(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'code' => $r['code'],
            'description' => $r['description'],
            'discount_type' => $r['discount_type'],
            'discount_value' => (float) $r['discount_value'],
            'min_order_amount' => $r['min_order_amount'] !== null ? (float) $r['min_order_amount'] : null,
            'max_uses' => $r['max_uses'] !== null ? (int) $r['max_uses'] : null,
            'max_uses_per_customer' => $r['max_uses_per_customer'] !== null ? (int) $r['max_uses_per_customer'] : null,
            'is_active' => (bool) $r['is_active'],
            'starts_at' => $r['starts_at'],
            'expires_at' => $r['expires_at'],
        ];
    }
}
