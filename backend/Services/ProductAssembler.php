<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CurrencyModel;
use App\Models\ProductModel;

final class ProductAssembler
{
    public function __construct(
        private ProductModel $model,
        private CurrencyModel $currencyModel,
    ) {}

    /**
     * Build full product response from raw DB row (individual queries).
     * @return array<string, mixed>
     */
    public function buildSingle(string $id, ?string $lang = null): array
    {
        $row = $this->model->findRawById($id);
        if (!$row) {
            return [];
        }

        $description = is_string($row['description'] ?? null) ? $row['description'] : '';
        $detailsArr = array_column($this->model->getDetails($id), 'detail_text');

        if ($lang !== null && $lang !== 'es') {
            $trans = $this->model->getTranslation($id, $lang);
            if ($trans !== null) {
                if ($trans['name'] !== '') {
                    $row['name'] = $trans['name'];
                }
                if ($trans['description'] !== null && $trans['description'] !== '') {
                    $description = $trans['description'];
                }
                if ($trans['details'] !== null && $trans['details'] !== '') {
                    $parsed = json_decode($trans['details'], true);
                    if (is_array($parsed)) {
                        $detailsArr = $parsed;
                    }
                }
            }
        }

        $imageRows = $this->model->getImages($id);
        $colorsList = $this->model->getColors($id);
        $colorNameMap = [];
        foreach ($colorsList as $c) {
            $colorNameMap[$c['id']] = $c['name'];
        }
        $images = [];
        $imageDetails = [];
        $imagesByColor = [];
        foreach ($imageRows as $r) {
            $colorName = $r['color_id'] !== null ? ($colorNameMap[$r['color_id']] ?? null) : null;
            $url = $r['url'];
            if ($colorName !== null) {
                $imagesByColor[$colorName][] = $url;
            } else {
                $images[] = $url;
            }
            $imageDetails[] = [
                'id' => $r['id'],
                'url' => $url,
                'fileId' => $r['file_id'] ?? '',
                'colorName' => $colorName,
            ];
        }

        $catIds = $this->model->getCategoryIds($id);
        $category = '';
        $categorySlug = '';
        if (isset($catIds[0])) {
            $catId = $catIds[0]['category_id'];
            if ($lang !== null && $lang !== 'es') {
                $catTrans = $this->model->getCategoryTranslation($catId, $lang);
                $category = $catTrans !== null ? $catTrans['name'] : '';
            }
            if ($category === '') {
                $catRow = $this->model->getCategoryById($catId);
                $category = $catRow !== null ? $catRow['name'] : '';
                $categorySlug = $catRow !== null ? $catRow['slug'] : '';
            }
        }

        $colorRows = $this->model->getColors($id);
        $colorsArr = array_map(fn(array $c): array => [
            'name' => $c['name'],
            'hex' => $c['hex'],
        ], $colorRows);

        $sizeRows = $this->model->getSizes($id);
        $variantRows = $this->model->getVariants($id);

        $sizeStockMap = [];
        $totalStock = 0;
        foreach ($variantRows as $v) {
            $sizeId = $v['size_id'];
            $stock = $v['stock'];
            $sizeStockMap[$sizeId] = ($sizeStockMap[$sizeId] ?? 0) + $stock;
            $totalStock += $stock;
        }

        $sizesArr = array_map(function (array $s) use ($sizeStockMap): array {
            $stock = $sizeStockMap[$s['id']] ?? 0;
            return [
                'label' => $s['label'],
                'value' => $s['value'],
                'inStock' => $stock > 0,
                'stock' => $stock,
            ];
        }, $sizeRows);

        $variantMatrix = $this->model->getVariantMatrix($id);

        return $this->buildResult($row, $description, $detailsArr, $images, $imageDetails, $imagesByColor, $category, $categorySlug, $colorsArr, $sizesArr, $variantMatrix, $totalStock);
    }

    /**
     * Build a single product response from pre-fetched batch data.
     * @param array<string, array<string, mixed>> $rawRows
     * @param array<string, array<string>> $detailsBatch
     * @param array<string, array<array{id: int, url: string, file_id: ?string, color_id: ?int, sort_order: int, is_primary: int}>> $imagesBatch
     * @param array<string, array<array{id: int, name: string, hex: string, sort_order: int}>> $colorsBatch
     * @param array<string, array<array{id: int, label: string, value: string, sort_order: int}>> $sizesBatch
     * @param array<string, array<array{color_id: int, size_id: int, stock: int}>> $variantsBatch
     * @param array<string, array<array{id: int, color_name: string, size_value: string, stock: int, price_override: ?float}>> $matrixBatch
     * @param array<string, array<int>> $catIdBatch
     * @param array<string, string> $categoryMap
     * @param array<string, string> $categorySlugMap
     * @param array<string, string> $catTranslationMap
     * @param array<string, array{name: string, description: ?string, details: ?string}> $translations
     * @return ?array<string, mixed>
     */
    public function buildFromBatches(
        string $pid,
        array &$rawRows,
        array &$detailsBatch,
        array &$imagesBatch,
        array &$colorsBatch,
        array &$sizesBatch,
        array &$variantsBatch,
        array &$matrixBatch,
        array &$catIdBatch,
        array &$categoryMap,
        array &$categorySlugMap,
        array &$catTranslationMap,
        array &$translations,
        ?string $lang,
    ): ?array {
        $row = $rawRows[$pid] ?? null;
        if (!$row) {
            return null;
        }

        $description = is_string($row['description'] ?? null) ? $row['description'] : '';
        $detailsArr = $detailsBatch[$pid] ?? [];

        if ($lang !== null && $lang !== 'es') {
            $trans = $translations[$pid] ?? null;
            if ($trans !== null) {
                if ($trans['name'] !== '') {
                    $row['name'] = $trans['name'];
                }
                if ($trans['description'] !== null && $trans['description'] !== '') {
                    $description = $trans['description'];
                }
                if ($trans['details'] !== null && $trans['details'] !== '') {
                    $parsed = json_decode($trans['details'], true);
                    if (is_array($parsed)) {
                        $detailsArr = $parsed;
                    }
                }
            }
        }

        $imageRows = $imagesBatch[$pid] ?? [];
        $colorRows = $colorsBatch[$pid] ?? [];
        $colorNameMap = [];
        foreach ($colorRows as $c) {
            $colorNameMap[$c['id']] = $c['name'];
        }
        $images = [];
        $imageDetails = [];
        $imagesByColor = [];
        foreach ($imageRows as $r) {
            $colorName = $r['color_id'] !== null ? ($colorNameMap[$r['color_id']] ?? null) : null;
            $url = $r['url'];
            if ($colorName !== null) {
                $imagesByColor[$colorName][] = $url;
            } else {
                $images[] = $url;
            }
            $imageDetails[] = [
                'id' => $r['id'],
                'url' => $url,
                'fileId' => $r['file_id'] ?? '',
                'colorName' => $colorName,
            ];
        }

        $prodCatIds = $catIdBatch[$pid] ?? [];
        $category = '';
        $categorySlug = '';
        if (isset($prodCatIds[0])) {
            $catId = $prodCatIds[0];
            if ($lang !== null && $lang !== 'es' && isset($catTranslationMap[$catId])) {
                $category = $catTranslationMap[$catId];
            }
            if ($category === '' && isset($categoryMap[$catId])) {
                $category = $categoryMap[$catId];
            }
            if (isset($categorySlugMap[$catId])) {
                $categorySlug = $categorySlugMap[$catId];
            }
        }

        $colorsArr = array_map(fn(array $c): array => [
            'name' => $c['name'],
            'hex' => $c['hex'],
        ], $colorRows);

        $sizeRows = $sizesBatch[$pid] ?? [];
        $variantRows = $variantsBatch[$pid] ?? [];

        $sizeStockMap = [];
        $totalStock = 0;
        foreach ($variantRows as $v) {
            $sizeId = $v['size_id'];
            $stock = $v['stock'];
            $sizeStockMap[$sizeId] = ($sizeStockMap[$sizeId] ?? 0) + $stock;
            $totalStock += $stock;
        }

        $sizesArr = array_map(function (array $s) use ($sizeStockMap): array {
            $stock = $sizeStockMap[$s['id']] ?? 0;
            return [
                'label' => $s['label'],
                'value' => $s['value'],
                'inStock' => $stock > 0,
                'stock' => $stock,
            ];
        }, $sizeRows);

        $variantMatrix = $matrixBatch[$pid] ?? [];

        return $this->buildResult($row, $description, $detailsArr, $images, $imageDetails, $imagesByColor, $category, $categorySlug, $colorsArr, $sizesArr, $variantMatrix, $totalStock);
    }

    /**
     * Build final product result array from assembled data.
     * @param array<string, mixed> $row
     * @param array<string> $detailsArr
     * @param array<string> $images
     * @param array<array{id: int, url: string, fileId: string, colorName: ?string}> $imageDetails
     * @param array<string, array<string>> $imagesByColor
     * @param array<array{name: string, hex: string}> $colorsArr
     * @param array<array{label: string, value: string, inStock: bool, stock: int}> $sizesArr
     * @param array<array{id: int, color_name: string, size_value: string, stock: int, price_override: ?float}> $variantMatrix
     * @return array<string, mixed>
     */
    private function buildResult(
        array $row,
        string $description,
        array $detailsArr,
        array $images,
        array $imageDetails,
        array $imagesByColor,
        string $category,
        string $categorySlug,
        array $colorsArr,
        array $sizesArr,
        array $variantMatrix,
        int $totalStock,
    ): array {
        $result = [
            'id' => $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $description,
            'details' => $detailsArr !== [] ? $detailsArr : null,
            'price' => is_numeric($row['price'] ?? null) ? (float) $row['price'] : 0,
            'currency' => is_string($row['currency'] ?? null) && $row['currency'] !== '' ? $row['currency'] : 'USD',
            'size_prefix' => $row['size_prefix'] ?? 'EU',
            'images' => $images,
            'imageDetails' => $imageDetails,
            'imagesByColor' => $imagesByColor,
            'category' => $category,
            'category_slug' => $categorySlug,
            'colors' => $colorsArr,
            'sizes' => $sizesArr,
            'variants' => $variantMatrix,
            'totalStock' => $totalStock,
            'lowStockThreshold' => is_numeric($row['low_stock_threshold'] ?? null) ? (int) $row['low_stock_threshold'] : 5,
            'createdAt' => date('c', strtotime(is_string($row['created_at'] ?? null) ? $row['created_at'] : 'now') ?: null),
            'isFeatured' => (bool) ($row['is_featured'] ?? false),
            'metaTitle' => $row['meta_title'] ?? '',
            'metaDescription' => $row['meta_description'] ?? '',
            'ogImageUrl' => $row['og_image_url'] ?? '',
        ];

        return $this->currencyModel->addDisplayPricesToProduct($result);
    }
}
