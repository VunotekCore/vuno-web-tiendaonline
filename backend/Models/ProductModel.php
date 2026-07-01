<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Str;

final class ProductModel
{
    public function __construct(private \PDO $db) {}

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        $this->db->rollBack();
    }

    /** @return array<int, array{id: string}> */
    public function findAllIds(?int $limit, ?int $offset, ?string $search, ?string $category): array
    {
        $join = '';
        $where = 'p.deleted_at IS NULL';
        $params = [];

        if ($category) {
            $join = ' JOIN product_categories pc ON pc.product_id = p.id JOIN categories c ON c.id = pc.category_id';
            $where .= ' AND (c.name = ? OR c.slug = ?)';
            $params[] = $category;
            $params[] = $category;
        }
        if ($search) {
            $where .= ' AND p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $sql = "SELECT p.id FROM products p{$join} WHERE {$where} ORDER BY p.created_at DESC";
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
        /** @var array<array{id: string}> */
        return $stmt->fetchAll();
    }

    public function countAll(?string $search, ?string $category): int
    {
        $join = '';
        $where = 'p.deleted_at IS NULL';
        $params = [];

        if ($category) {
            $join = ' JOIN product_categories pc ON pc.product_id = p.id JOIN categories c ON c.id = pc.category_id';
            $where .= ' AND (c.name = ? OR c.slug = ?)';
            $params[] = $category;
            $params[] = $category;
        }
        if ($search) {
            $where .= ' AND p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT p.id) FROM products p{$join} WHERE {$where}");
        $stmt->execute($params);
        /** @var int */
        return $stmt->fetchColumn();
    }

    /** @return ?array<string, mixed> */
    public function findRawById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        /** @var ?array<string, mixed> */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<string, array<string, mixed>> */
    public function findRawByIds(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['id']] = $r;
        }
        return $grouped;
    }

    /** @return ?array<string, mixed> */
    public function findRawBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute([$slug]);
        /** @var ?array<string, mixed> */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<array{detail_text: string}> */
    public function getDetails(string $productId): array
    {
        $stmt = $this->db->prepare('SELECT detail_text FROM product_details WHERE product_id = ? ORDER BY sort_order');
        $stmt->execute([$productId]);
        /** @var array<array{detail_text: string}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<string>> */
    public function getDetailsBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT product_id, detail_text FROM product_details WHERE product_id IN ({$placeholders}) ORDER BY sort_order"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r['detail_text'];
        }
        return $grouped;
    }

    /** @return array<array{id: int, url: string, file_id: ?string, color_id: ?int, sort_order: int, is_primary: int}> */
    public function getImages(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT pi.id, pi.url, pi.file_id, pi.color_id, pi.sort_order, pi.is_primary
             FROM product_images pi WHERE pi.product_id = ? ORDER BY pi.sort_order'
        );
        $stmt->execute([$productId]);
        /** @var array<array{id: int, url: string, file_id: ?string, color_id: ?int, sort_order: int, is_primary: int}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<array{id: int, url: string, file_id: ?string, color_id: ?int, sort_order: int, is_primary: int}>> */
    public function getImagesBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT pi.product_id, pi.id, pi.url, pi.file_id, pi.color_id, pi.sort_order, pi.is_primary
             FROM product_images pi WHERE pi.product_id IN ({$placeholders}) ORDER BY pi.sort_order"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r;
        }
        return $grouped;
    }

    /** @return array<array{category_id: string}> */
    public function getCategoryIds(string $productId): array
    {
        $stmt = $this->db->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
        $stmt->execute([$productId]);
        /** @var array<array{category_id: string}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<int>> */
    public function getCategoryIdsBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT product_id, category_id FROM product_categories WHERE product_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r['category_id'];
        }
        return $grouped;
    }

    /** @return ?array{id: string, name: string, slug: string} */
    public function getCategoryById(string $catId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, slug FROM categories WHERE id = ?');
        $stmt->execute([$catId]);
        /** @var ?array{id: int, name: string, slug: string} */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<array{id: int, name: string, slug: string}> */
    public function getCategoriesByIds(array $catIds): array
    {
        if ($catIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, name, slug FROM categories WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_values($catIds));
        /** @var array<array{id: int, name: string, slug: string}> */
        return $stmt->fetchAll();
    }

    /** @return array<array{id: int, name: string, hex: string, sort_order: int}> */
    public function getColors(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, hex, sort_order FROM product_colors WHERE product_id = ? ORDER BY sort_order'
        );
        $stmt->execute([$productId]);
        /** @var array<array{id: int, name: string, hex: string, sort_order: int}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<array{id: int, name: string, hex: string, sort_order: int}>> */
    public function getColorsBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT product_id, id, name, hex, sort_order FROM product_colors WHERE product_id IN ({$placeholders}) ORDER BY sort_order"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r;
        }
        return $grouped;
    }

    /** @return array<array{id: int, label: string, value: string, sort_order: int}> */
    public function getSizes(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, label, value, sort_order FROM product_sizes WHERE product_id = ? ORDER BY sort_order'
        );
        $stmt->execute([$productId]);
        /** @var array<array{id: int, label: string, value: string, sort_order: int}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<array{id: int, label: string, value: string, sort_order: int}>> */
    public function getSizesBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT product_id, id, label, value, sort_order FROM product_sizes WHERE product_id IN ({$placeholders}) ORDER BY sort_order"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r;
        }
        return $grouped;
    }

    /** @return array<array{color_id: int, size_id: int, stock: int}> */
    public function getVariants(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT color_id, size_id, stock FROM product_variants WHERE product_id = ? AND is_active = 1'
        );
        $stmt->execute([$productId]);
        /** @var array<array{color_id: int, size_id: int, stock: int}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<array{color_id: int, size_id: int, stock: int}>> */
    public function getVariantsBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT product_id, color_id, size_id, stock FROM product_variants WHERE product_id IN ({$placeholders}) AND is_active = 1"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r;
        }
        return $grouped;
    }

    /** @return array<array{id: int, color_name: string, size_value: string, stock: int, price_override: ?float}> */
    public function getVariantMatrix(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT pv.id, pc.name AS color_name, pc.hex AS color_hex, ps.value AS size_value, pv.stock, pv.price_override
             FROM product_variants pv
             JOIN product_colors pc ON pc.id = pv.color_id
             JOIN product_sizes ps ON ps.id = pv.size_id
             WHERE pv.product_id = ? AND pv.is_active = 1
             ORDER BY ps.sort_order, pc.sort_order'
        );
        $stmt->execute([$productId]);
        /** @var array<array{id: int, color_name: string, size_value: string, stock: int, price_override: ?float}> */
        return $stmt->fetchAll();
    }

    /** @return array<string, array<array{id: int, color_name: string, size_value: string, stock: int, price_override: ?float}>> */
    public function getVariantMatrixBatch(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT pv.product_id, pv.id, pc.name AS color_name, pc.hex AS color_hex, ps.value AS size_value, pv.stock, pv.price_override
             FROM product_variants pv
             JOIN product_colors pc ON pc.id = pv.color_id
             JOIN product_sizes ps ON ps.id = pv.size_id
             WHERE pv.product_id IN ({$placeholders}) AND pv.is_active = 1
             ORDER BY ps.sort_order, pc.sort_order"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['product_id']][] = $r;
        }
        return $grouped;
    }

    /** @return ?array{id: int, name: string, description: ?string, details: ?string} */
    public function getTranslation(string $productId, string $lang): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, name, description, details FROM product_translations WHERE product_id = ? AND lang = ?'
            );
            $stmt->execute([$productId, $lang]);
            /** @var ?array{id: int, name: string, description: ?string, details: ?string} */
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\PDOException) {
            return null;
        }
    }

    /** @return ?int */
    public function findCategoryIdByName(string $name): ?string
    {
        $stmt = $this->db->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        $stmt->execute([$name, Str::slugify($name)]);
        /** @var string|false $id */
        $id = $stmt->fetchColumn();
        return $id !== false ? $id : null;
    }

    /** @return array{id: int, name: string} */
    public function getCategoryTranslation(string $catId, string $lang): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT name FROM category_translations WHERE category_id = ? AND lang = ?'
            );
            $stmt->execute([$catId, $lang]);
            $name = $stmt->fetchColumn();
            if ($name !== false && $name !== null) {
                return ['id' => $catId, 'name' => (string) $name];
            }
        } catch (\PDOException) {
            // Translation table not available
        }
        return null;
    }

    /**
     * Optimized summary query for admin list — 2 SQL queries instead of 13.
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function findSummary(?int $limit, ?int $offset, ?string $search, ?string $category): array
    {
        $where = 'p.deleted_at IS NULL';
        $params = [];

        if ($category) {
            $where .= ' AND (c.name = ? OR c.slug = ?)';
            $params[] = $category;
            $params[] = $category;
        }
        if ($search) {
            $where .= ' AND p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT p.id) FROM products p
             LEFT JOIN product_categories pc ON pc.product_id = p.id
             LEFT JOIN categories c ON c.id = pc.category_id
             WHERE {$where}"
        );
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $itemSql = "SELECT p.id, p.name, p.price, p.is_featured, p.created_at,
                           COALESCE(v.totalStock, 0) AS totalStock,
                           c.name AS category,
                           c.slug AS category_slug
                    FROM products p
                    LEFT JOIN product_categories pc ON pc.product_id = p.id
                    LEFT JOIN categories c ON c.id = pc.category_id
                    LEFT JOIN (
                        SELECT product_id, SUM(stock) AS totalStock
                        FROM product_variants WHERE is_active = 1
                        GROUP BY product_id
                    ) v ON v.product_id = p.id
                    WHERE {$where}
                    ORDER BY p.created_at DESC";

        $itemParams = $params;
        if ($limit !== null) {
            $itemSql .= ' LIMIT ?';
            $itemParams[] = $limit;
        }
        if ($offset !== null) {
            $itemSql .= ' OFFSET ?';
            $itemParams[] = $offset;
        }

        $stmt = $this->db->prepare($itemSql);
        $stmt->execute($itemParams);
        /** @var array<int, array<string, mixed>> $items */
        $items = $stmt->fetchAll();

        return ['items' => $items, 'total' => $total];
    }

    /** @return array<array{id: int, file_id: string}> */
    public function getImageFileIds(string $productId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, file_id FROM product_images WHERE product_id = ? AND file_id IS NOT NULL'
        );
        $stmt->execute([$productId]);
        /** @var array<array{id: int, file_id: string}> */
        return $stmt->fetchAll();
    }

    public function deleteImageById(int $imageId): void
    {
        $this->db->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
    }

    // =========================================================================
    //  Write operations
    // =========================================================================

    /** @param array<string, mixed> $data */
    public function insertProduct(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (id, name, slug, description, price, currency, size_prefix, low_stock_threshold, is_featured, meta_title, meta_description, og_image_url, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['id'],
            $data['name'],
            $data['slug'],
            $data['description'] ?? '',
            $data['price'],
            $data['currency'] ?? 'USD',
            $data['size_prefix'] ?? 'US',
            $data['lowStockThreshold'] ?? 5,
            $data['isFeatured'] ? 1 : 0,
            $data['metaTitle'] ?? '',
            $data['metaDescription'] ?? '',
            $data['ogImageUrl'] ?? '',
            $data['createdAt'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateProduct(array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE products SET name = ?, slug = ?, description = ?, price = ?, currency = ?, size_prefix = ?,
             low_stock_threshold = ?, is_featured = ?,
             meta_title = ?, meta_description = ?, og_image_url = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? '',
            $data['price'],
            $data['currency'] ?? 'USD',
            $data['size_prefix'] ?? 'US',
            $data['lowStockThreshold'] ?? 5,
            $data['isFeatured'] ? 1 : 0,
            $data['metaTitle'] ?? '',
            $data['metaDescription'] ?? '',
            $data['ogImageUrl'] ?? '',
            $data['id'],
        ]);
    }

    public function getVariantStock(string $productId, string $color, string $size): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT pv.stock FROM product_variants pv
             JOIN product_colors pc ON pc.id = pv.color_id
             JOIN product_sizes ps ON ps.id = pv.size_id
             WHERE pv.product_id = ? AND pc.name = ? AND ps.value = ? AND pv.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$productId, $color, $size]);
        $stock = $stmt->fetchColumn();
        return $stock !== false ? (int) $stock : null;
    }

    public function softDelete(string $id): void
    {
        $this->db->prepare('UPDATE products SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /** @param list<string> $details */
    public function replaceDetails(string $productId, array $details): void
    {
        $this->db->prepare('DELETE FROM product_details WHERE product_id = ?')->execute([$productId]);
        if (!empty($details)) {
            $stmt = $this->db->prepare('INSERT INTO product_details (product_id, detail_text, sort_order) VALUES (?, ?, ?)');
            foreach (array_values($details) as $i => $d) {
                $stmt->execute([$productId, $d, $i]);
            }
        }
    }

    public function replaceCategories(string $productId, ?string $categoryName): void
    {
        $this->db->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$productId]);
        if ($categoryName) {
            $catId = $this->findCategoryIdByName($categoryName);
            if ($catId) {
                $this->db->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)')
                    ->execute([$productId, $catId]);
            }
        }
    }

    /**
     * @param array<array{name: string, hex: string}> $colors
     * @return array<string, int> colorName => colorDbId
     */
    public function replaceColors(string $productId, array $colors): array
    {
        $this->db->prepare('DELETE FROM product_colors WHERE product_id = ?')->execute([$productId]);
        $colorIdMap = [];
        if (!empty($colors)) {
            $stmt = $this->db->prepare('INSERT INTO product_colors (product_id, name, hex, sort_order) VALUES (?, ?, ?, ?)');
            $deduped = [];
            foreach ($colors as $c) {
                $deduped[$c['name']] = $c;
            }
            foreach (array_values($deduped) as $i => $c) {
                $hex = $c['hex'] ?? '#1A1A1A';
                $stmt->execute([$productId, $c['name'], $hex, $i]);
                $colorIdMap[$c['name']] = (int) $this->db->lastInsertId();
            }
        }
        return $colorIdMap;
    }

    /**
     * @param array<array{label: string, value: string}> $sizes
     * @return array<string, int> sizeValue => sizeDbId
     */
    public function replaceSizes(string $productId, array $sizes): array
    {
        $this->db->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$productId]);
        $sizeIdMap = [];
        if (!empty($sizes)) {
            $stmt = $this->db->prepare('INSERT INTO product_sizes (product_id, label, value, sort_order) VALUES (?, ?, ?, ?)');
            $deduped = [];
            foreach ($sizes as $s) {
                $deduped[$s['value']] = $s;
            }
            foreach (array_values($deduped) as $i => $s) {
                $stmt->execute([$productId, $s['label'], $s['value'], $i]);
                $sizeIdMap[$s['value']] = (int) $this->db->lastInsertId();
            }
        }
        return $sizeIdMap;
    }

    /**
     * @param array<string, int> $colorIdMap
     * @param array<string, int> $sizeIdMap
     * @param array<string, int> $stocks colorName_sizeValue => stock
     */
    public function replaceVariants(string $productId, array $colorIdMap, array $sizeIdMap, array $stocks): void
    {
        $this->db->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$productId]);
        if ($colorIdMap === [] || $sizeIdMap === []) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO product_variants (product_id, color_id, size_id, stock, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        foreach ($colorIdMap as $colorName => $colorDbId) {
            foreach ($sizeIdMap as $sizeVal => $sizeDbId) {
                $stockKey = $colorName . '_' . $sizeVal;
                $stock = isset($stocks[$stockKey]) ? max(0, (int) $stocks[$stockKey]) : 0;
                $stmt->execute([$productId, $colorDbId, $sizeDbId, $stock]);
            }
        }
    }

    /**
     * @param array<int, string|array{url: string, fileId?: ?string, colorName?: ?string}> $images
     * @param array<string, int> $colorIdMap
     */
    public function replaceImages(string $productId, array $images, array $colorIdMap): void
    {
        $this->db->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
        if (empty($images)) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO product_images (product_id, url, file_id, sort_order, is_primary, color_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $globalPrimarySet = false;
        foreach (array_values($images) as $i => $img) {
            $url = is_string($img) ? $img : ($img['url'] ?? '');
            $fileId = is_string($img) ? null : ($img['fileId'] ?? null);
            $colorName = is_string($img) ? null : ($img['colorName'] ?? null);
            $colorId = $colorName ? ($colorIdMap[$colorName] ?? null) : null;
            $isPrimary = 0;
            if ($colorId === null && !$globalPrimarySet) {
                $isPrimary = 1;
                $globalPrimarySet = true;
            }
            $stmt->execute([$productId, $url, $fileId, $i, $isPrimary, $colorId]);
        }
    }
}
