<?php
declare(strict_types=1);

namespace App\Models;

final class CartModel
{
    public function __construct(private \PDO $db) {}

    /** @return array<int, array<string, mixed>> */
    public function getByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ci.product_id, ci.quantity, ci.selected_color, ci.selected_size,
                    p.name, p.slug, p.price, p.currency
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.customer_id = ? AND p.deleted_at IS NULL
             ORDER BY ci.created_at ASC'
        );
        $stmt->execute([$customerId]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** @param array<int, array<string, mixed>> $items */
    public function replaceAll(int $customerId, array $items): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM cart_items WHERE customer_id = ?')->execute([$customerId]);
            if ($items !== []) {
                $stmt = $this->db->prepare(
                    'INSERT INTO cart_items (customer_id, product_id, quantity, selected_color, selected_size)
                     VALUES (?, ?, ?, ?, ?)'
                );
                foreach ($items as $item) {
                    $product = $item['product'] ?? null;
                    $productId = $item['product_id'] ?? (is_array($product) ? ($product['id'] ?? '') : '');
                    $quantity = is_numeric($item['quantity'] ?? null) ? (int) $item['quantity'] : 1;
                    $color = $item['selected_color'] ?? $item['selectedColor'] ?? '';
                    $size = $item['selected_size'] ?? $item['selectedSize'] ?? '';
                    $stmt->execute([$customerId, $productId, $quantity, $color, $size]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addItem(int $customerId, string $productId, int $quantity, string $color, string $size): void
    {
        $this->db->prepare(
            'INSERT INTO cart_items (customer_id, product_id, quantity, selected_color, selected_size)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        )->execute([$customerId, $productId, $quantity, $color, $size]);
    }

    public function removeItem(int $customerId, string $productId, string $color, string $size): void
    {
        $this->db->prepare(
            'DELETE FROM cart_items WHERE customer_id = ? AND product_id = ? AND selected_color = ? AND selected_size = ?'
        )->execute([$customerId, $productId, $color, $size]);
    }

    public function clear(int $customerId): void
    {
        $this->db->prepare('DELETE FROM cart_items WHERE customer_id = ?')->execute([$customerId]);
    }
}
