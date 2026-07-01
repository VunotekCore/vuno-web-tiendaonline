<?php
declare(strict_types=1);

namespace App\Models;

final class CategoryModel
{
    public function __construct(private \PDO $db) {}

    /**
     * @return array{items: array<int, array{id: int|string, name: string, slug: string, productCount: int}>, total: int}
     */
    public function getAll(?int $limit, ?int $offset, ?string $search = null): array
    {
        $where = 'is_active = 1';
        $params = [];
        if ($search !== null && $search !== '') {
            $where .= ' AND name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM categories WHERE ' . $where);
        $countStmt->execute($params);
        /** @var int $total */
        $total = ($countStmt !== false) ? (int) $countStmt->fetchColumn() : 0;

        $sql = 'SELECT c.*, COUNT(pc.product_id) AS productCount'
            . ' FROM categories c'
            . ' LEFT JOIN product_categories pc ON pc.category_id = c.id'
            . ' WHERE ' . $where
            . ' GROUP BY c.id'
            . ' ORDER BY c.sort_order, c.name';
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

        /** @var array<int, string> $transMap */
        $transMap = [];

        $items = array_map(function (array $r) use ($transMap): array {
            /** @var int|string $id */
            $id = $r['id'];
            return [
                'id' => $id,
                'name' => $transMap[$id] ?? $r['name'],
                'slug' => $r['slug'],
                'productCount' => (int) ($r['productCount'] ?? 0),
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /** @return ?array{id: string, name: string, slug: string} */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        /** @var ?array<string, mixed> $row */
        $row = $stmt->fetch();
        if ($row === false || $row === null) {
            return null;
        }
        return [
            'id' => is_string($row['id'] ?? null) ? $row['id'] : '',
            'name' => is_string($row['name'] ?? null) ? $row['name'] : '',
            'slug' => is_string($row['slug'] ?? null) ? $row['slug'] : '',
        ];
    }

    /** @param array{id: string, name: string, slug: string} $category */
    public function save(array $category): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (id, name, slug) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)'
        );
        $stmt->execute([$category['id'], $category['name'], $category['slug']]);
    }

    public function delete(string $id): void
    {
        $this->db->prepare('UPDATE categories SET is_active = 0 WHERE id = ?')->execute([$id]);
    }
}
