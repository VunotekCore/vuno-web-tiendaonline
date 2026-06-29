<?php
declare(strict_types=1);

namespace App\Models;

final class BlogPostModel
{
    public function __construct(private \PDO $db) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function getPosts(int $page = 1, int $limit = 10, string $status = '', int $categoryId = 0, ?string $lang = null): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        $countParams = $params;

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
                FROM blog_posts p
                LEFT JOIN blog_categories c ON c.id = p.category_id
                WHERE {$whereClause}
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $items = $this->applyPostTranslations($items, $lang);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM blog_posts p WHERE {$whereClause}");
        $countStmt->execute($countParams);
        /** @var int $total */
        $total = (int) $countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getPostById(int $id, ?string $lang = null): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM blog_posts p
             LEFT JOIN blog_categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false || $row === null) {
            return null;
        }
        return $this->applyPostTranslation($row, $id, $lang);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getPostBySlug(string $slug, ?string $lang = null): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM blog_posts p
             LEFT JOIN blog_categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.deleted_at IS NULL'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row === false || $row === null) {
            return null;
        }
        return $this->applyPostTranslation($row, (int) $row['id'], $lang);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPost(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO blog_posts (title, slug, excerpt, thumbnail_image, content, featured_image, author, status, category_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['excerpt'] ?? '',
            $data['thumbnail_image'] ?? null,
            $data['content'],
            $data['featured_image'] ?? null,
            $data['author'] ?? 'Vunotek',
            $data['status'] ?? 'draft',
            $data['category_id'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();

        if (($data['status'] ?? '') === 'published') {
            $this->db->prepare('UPDATE blog_posts SET published_at = COALESCE(published_at, NOW()) WHERE id = ?')
                ->execute([$id]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updatePost(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (['title', 'slug', 'excerpt', 'thumbnail_image', 'content', 'featured_image', 'author', 'status', 'category_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = ?";
                $params[] = $data[$key];
            }
        }

        if ($fields === []) {
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        $this->db->prepare('UPDATE blog_posts SET ' . implode(', ', $fields) . ' WHERE id = ?')
            ->execute($params);

        if (($data['status'] ?? '') === 'published') {
            $this->db->prepare('UPDATE blog_posts SET published_at = COALESCE(published_at, NOW()) WHERE id = ?')
                ->execute([$id]);
        }
    }

    public function deletePost(int $id): void
    {
        $this->db->prepare('UPDATE blog_posts SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function applyPostTranslations(array $items, ?string $lang): array
    {
        if ($lang === null || $lang === 'es' || $items === []) {
            return $items;
        }

        try {
            $postIds = array_map(fn(array $i): int => (int) $i['id'], $items);
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $tStmt = $this->db->prepare(
                "SELECT blog_post_id, title, excerpt, content FROM blog_post_translations WHERE blog_post_id IN ({$placeholders}) AND lang = ?"
            );
            $tParams = $postIds;
            $tParams[] = $lang;
            $tStmt->execute($tParams);
            $transMap = [];
            while ($t = $tStmt->fetch()) {
                $transMap[(int) $t['blog_post_id']] = $t;
            }
            foreach ($items as &$item) {
                $tid = (int) $item['id'];
                if (isset($transMap[$tid])) {
                    if ($transMap[$tid]['title'] !== '' && $transMap[$tid]['title'] !== null) {
                        $item['title'] = $transMap[$tid]['title'];
                    }
                    if ($transMap[$tid]['excerpt'] !== '' && $transMap[$tid]['excerpt'] !== null) {
                        $item['excerpt'] = $transMap[$tid]['excerpt'];
                    }
                    if ($transMap[$tid]['content'] !== '' && $transMap[$tid]['content'] !== null) {
                        $item['content'] = $transMap[$tid]['content'];
                    }
                }
            }
            unset($item);
        } catch (\PDOException) {
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function applyPostTranslation(array $row, int $postId, ?string $lang): array
    {
        if ($lang === null || $lang === 'es') {
            return $row;
        }

        try {
            $tStmt = $this->db->prepare(
                'SELECT title, excerpt, content FROM blog_post_translations WHERE blog_post_id = ? AND lang = ?'
            );
            $tStmt->execute([$postId, $lang]);
            $trans = $tStmt->fetch();
            if ($trans !== false && $trans !== null) {
                if ($trans['title'] !== '' && $trans['title'] !== null) {
                    $row['title'] = $trans['title'];
                }
                if ($trans['excerpt'] !== '' && $trans['excerpt'] !== null) {
                    $row['excerpt'] = $trans['excerpt'];
                }
                if ($trans['content'] !== '' && $trans['content'] !== null) {
                    $row['content'] = $trans['content'];
                }
            }

            if (isset($row['category_id']) && $row['category_id'] !== null) {
                $ctStmt = $this->db->prepare(
                    'SELECT name FROM blog_category_translations WHERE category_id = ? AND lang = ?'
                );
                $ctStmt->execute([(int) $row['category_id'], $lang]);
                $ctName = $ctStmt->fetchColumn();
                if ($ctName !== false && $ctName !== null && $ctName !== '') {
                    $row['category_name'] = $ctName;
                }
            }
        } catch (\PDOException) {
        }

        return $row;
    }
}
