<?php
declare(strict_types=1);

namespace App\Models;

final class CategoryModel
{
    public function __construct(private \PDO $db) {}

    /**
     * @return array{items: array<int, array{id: int, name: string, slug: string}>, total: int}
     */
    public function getAll(?int $limit, ?int $offset, ?string $lang, ?string $search = null): array
    {
        $countSql = 'SELECT COUNT(*) FROM categories WHERE is_active = 1';
        $sql = 'SELECT * FROM categories WHERE is_active = 1';
        $params = [];
        $searchParam = null;

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $countSql .= ' AND name LIKE ?';
            $sql .= ' AND name LIKE ?';
            $searchParam = $like;
            $params[] = $like;
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($searchParam !== null ? [$searchParam] : []);
        /** @var int $total */
        $total = (int) $countStmt->fetchColumn();

        $sql .= ' ORDER BY sort_order, name';
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
        if ($lang !== null && $lang !== 'es') {
            try {
                $tStmt = $this->db->prepare('SELECT category_id, name FROM category_translations WHERE lang = ?');
                $tStmt->execute([$lang]);
                /** @var array<array{category_id: int, name: string}> $transRows */
                $transRows = $tStmt->fetchAll();
                foreach ($transRows as $t) {
                    $transMap[$t['category_id']] = $t['name'];
                }
            } catch (\PDOException) {
            }
        }

        $items = array_map(function (array $r) use ($transMap): array {
            /** @var int $id */
            $id = $r['id'];
            return [
                'id' => $id,
                'name' => $transMap[$id] ?? $r['name'],
                'slug' => $r['slug'],
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
