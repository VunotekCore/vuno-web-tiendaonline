<?php
declare(strict_types=1);

namespace App\Models;

final class WishlistModel
{
    public function __construct(private \PDO $db) {}

    /** @return array<int, array<string, mixed>> */
    public function getByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT wi.product_id, wi.variant_id, wi.created_at,
                    p.name, p.slug, p.price, p.currency
             FROM wishlist_items wi
             JOIN products p ON p.id = wi.product_id
             WHERE wi.customer_id = ? AND p.deleted_at IS NULL
             ORDER BY wi.created_at DESC'
        );
        $stmt->execute([$customerId]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return \array_map(function (array $r): array {
            $price = is_numeric($r['price'] ?? null) ? (float) $r['price'] : 0.0;
            $currency = isset($r['currency']) && \is_string($r['currency']) ? $r['currency'] : '';
            $createdRaw = isset($r['created_at']) && \is_string($r['created_at']) ? $r['created_at'] : '';
            /** @var int|false $createdTs */
            $createdTs = $createdRaw !== '' ? \strtotime($createdRaw) : false;
            return [
                'productId' => $r['product_id'] ?? '',
                'variantId' => $r['variant_id'] ?? null,
                'name'      => $r['name'] ?? '',
                'slug'      => $r['slug'] ?? '',
                'price'     => $price,
                'currency'  => $currency !== '' ? $currency : 'USD',
                'createdAt' => $createdTs !== false ? \date('c', $createdTs) : \date('c'),
            ];
        }, $rows);
    }

    public function add(int $customerId, string $productId, ?int $variantId = null): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO wishlist_items (customer_id, product_id, variant_id) VALUES (?, ?, ?)'
            );
            $stmt->execute([$customerId, $productId, $variantId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function remove(int $customerId, string $productId, ?int $variantId = null): void
    {
        $sql = 'DELETE FROM wishlist_items WHERE customer_id = ? AND product_id = ?';
        $params = [$customerId, $productId];
        if ($variantId !== null) {
            $sql .= ' AND variant_id = ?';
            $params[] = $variantId;
        }
        $this->db->prepare($sql)->execute($params);
    }

    public function isInWishlist(int $customerId, string $productId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ? AND product_id = ?'
        );
        $stmt->execute([$customerId, $productId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
