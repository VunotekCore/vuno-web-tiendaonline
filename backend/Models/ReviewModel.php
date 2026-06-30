<?php
declare(strict_types=1);

namespace App\Models;

final class ReviewModel
{
    public function __construct(private \PDO $db) {}

    /**
     * Public-facing: get approved reviews for a product.
     * @return array<int, array<string, mixed>>
     */
    public function getProductReviews(string $productId, bool $approvedOnly = true): array
    {
        $sql = 'SELECT * FROM product_reviews WHERE product_id = ?';
        $params = [$productId];
        if ($approvedOnly) {
            $sql .= ' AND is_approved = 1';
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn(array $r): array => [
            'id' => (int) $r['id'],
            'productId' => $r['product_id'],
            'reviewerName' => $r['reviewer_name'],
            'rating' => (int) $r['rating'],
            'title' => $r['title'],
            'comment' => $r['comment'],
            'isApproved' => (bool) $r['is_approved'],
            'createdAt' => date('c', strtotime((string) $r['created_at']) ?: null),
        ], $rows);
    }

    /**
     * Admin-facing: paginated list with product join.
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getAll(?int $limit, ?int $offset, ?string $status, ?string $search): array
    {
        $countSql = 'SELECT COUNT(*) FROM product_reviews pr JOIN products p ON p.id = pr.product_id';
        $sql = 'SELECT pr.*, p.name AS product_name, p.slug AS product_slug
                FROM product_reviews pr
                JOIN products p ON p.id = pr.product_id';
        $conditions = [];
        $params = [];

        if ($status === 'pending') {
            $conditions[] = 'pr.is_approved = 0';
        } elseif ($status === 'approved') {
            $conditions[] = 'pr.is_approved = 1';
        }

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = '(p.name LIKE ? OR pr.reviewer_name LIKE ? OR pr.comment LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($conditions !== []) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
            $countSql .= $where;
            $sql .= $where;
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($conditions !== [] ? $params : []);
        /** @var int $total */
        $total = (int) $countStmt->fetchColumn();

        $sql .= ' ORDER BY pr.created_at DESC';
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

        $items = array_map(fn(array $r): array => [
            'id' => (int) $r['id'],
            'productId' => $r['product_id'],
            'productName' => $r['product_name'],
            'productSlug' => $r['product_slug'],
            'reviewerName' => $r['reviewer_name'],
            'reviewerEmail' => $r['reviewer_email'],
            'rating' => (int) $r['rating'],
            'title' => $r['title'],
            'comment' => $r['comment'],
            'isApproved' => (bool) $r['is_approved'],
            'createdAt' => date('c', strtotime((string) $r['created_at']) ?: null),
        ], $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get aggregate stats (approved reviews only).
     * @return array{total: int, average: float, distribution: array<int, int>}
     */
    public function getStats(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating
             FROM product_reviews WHERE product_id = ? AND is_approved = 1'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch();

        $distStmt = $this->db->prepare(
            'SELECT rating, COUNT(*) AS cnt FROM product_reviews
             WHERE product_id = ? AND is_approved = 1
             GROUP BY rating ORDER BY rating DESC'
        );
        $distStmt->execute([$productId]);
        $dist = [];
        while ($d = $distStmt->fetch()) {
            $dist[(int) $d['rating']] = (int) $d['cnt'];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'average' => round((float) ($row['avg_rating'] ?? 0), 1),
            'distribution' => $dist,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReview(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO product_reviews (product_id, reviewer_name, reviewer_email, rating, title, comment, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['product_id'],
            $data['reviewer_name'] ?? '',
            $data['reviewer_email'] ?? '',
            (int) ($data['rating'] ?? 0),
            $data['title'] ?? '',
            $data['comment'] ?? '',
            isset($data['is_approved']) ? (($data['is_approved'] ? 1 : 0)) : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function approveReview(int $id): void
    {
        $this->db->prepare('UPDATE product_reviews SET is_approved = 1 WHERE id = ?')->execute([$id]);
    }

    public function deleteReview(int $id): void
    {
        $this->db->prepare('DELETE FROM product_reviews WHERE id = ?')->execute([$id]);
    }
}
