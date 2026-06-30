<?php
declare(strict_types=1);

namespace App\Models;

final class BlogCategoryModel
{
    public function __construct(private \PDO $db) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $lang = null): array
    {
        $rows = $this->db->query('SELECT * FROM blog_categories ORDER BY name')->fetchAll();

        if ($lang !== null && $lang !== 'es') {
            try {
                $tStmt = $this->db->prepare('SELECT category_id, name FROM blog_category_translations WHERE lang = ?');
                $tStmt->execute([$lang]);
                $transMap = [];
                while ($t = $tStmt->fetch()) {
                    $transMap[(int) $t['category_id']] = $t['name'];
                }
                foreach ($rows as &$r) {
                    if (isset($transMap[(int) $r['id']])) {
                        $r['name'] = $transMap[(int) $r['id']];
                    }
                }
                unset($r);
            } catch (\PDOException) {
            }
        }

        return $rows;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return ($row !== false && $row !== null) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO blog_categories (name, slug, description) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? '',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (['name', 'slug', 'description'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = ?";
                $params[] = $data[$key];
            }
        }

        if ($fields === []) {
            return;
        }

        $params[] = $id;
        $this->db->prepare('UPDATE blog_categories SET ' . implode(', ', $fields) . ' WHERE id = ?')
            ->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('UPDATE blog_posts SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM blog_categories WHERE id = ?')->execute([$id]);
    }
}
