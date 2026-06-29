<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CurrencyModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\ImageKitService;
use App\Traits\ApiResponse;
use App\Utils\Str;

final class ProductController
{
    use ApiResponse;

    private ?AuthService $auth = null;
    private ?CurrencyModel $currencyModel = null;

    public function __construct(
        private ProductModel $model,
        private ?ImageKitService $imageKit = null,
    ) {
        $this->imageKit ??= new ImageKitService();
    }

    private function getAuth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService(new UserModel(\App\Config\Database::getConnection()));
        }
        return $this->auth;
    }

    private function getCurrencyModel(): CurrencyModel
    {
        if ($this->currencyModel === null) {
            $this->currencyModel = new CurrencyModel(\App\Config\Database::getConnection());
        }
        return $this->currencyModel;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    private function queryString(string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    private function queryInt(string $key): ?int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : null;
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    /**
     * @param array<string, mixed> $data
     * @return mixed
     */
    private function val(array $data, string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /** @param array<string, mixed> $data */
    private function str(array $data, string $key, string $default = ''): string
    {
        $v = $this->val($data, $key);
        if (is_string($v)) return $v;
        if (is_scalar($v)) return (string) $v;
        return $default;
    }

    /** @param array<string, mixed> $data */
    private function flt(array $data, string $key, float $default = 0.0): float
    {
        $v = $this->val($data, $key);
        return is_numeric($v) ? (float) $v : $default;
    }

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $default = 0): int
    {
        $v = $this->val($data, $key);
        return is_numeric($v) ? (int) $v : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    private function arr(array $data, string $key): array
    {
        $v = $this->val($data, $key);
        return is_array($v) ? $v : [];
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $input = $this->input();
        return $input;
    }

    // =========================================================================
    //  API methods
    // =========================================================================

    public function list(): void
    {
        $search = $this->queryString('search');
        $category = $this->queryString('category');
        $total = $this->model->countAll($search !== '' ? $search : null, $category !== '' ? $category : null);
        $ids = $this->model->findAllIds(
            $this->queryInt('limit'),
            $this->queryInt('offset'),
            $search !== '' ? $search : null,
            $category !== '' ? $category : null,
        );

        $lang = $this->queryString('lang');
        $langVal = $lang !== '' ? $lang : null;

        $items = [];
        if ($ids !== []) {
            $productIds = array_column($ids, 'id');

            // Batch-fetch all relations in ~8 queries total
            $rawRows = $this->model->findRawByIds($productIds);
            $detailsBatch = $this->model->getDetailsBatch($productIds);
            $imagesBatch = $this->model->getImagesBatch($productIds);
            $colorsBatch = $this->model->getColorsBatch($productIds);
            $sizesBatch = $this->model->getSizesBatch($productIds);
            $variantsBatch = $this->model->getVariantsBatch($productIds);
            $matrixBatch = $this->model->getVariantMatrixBatch($productIds);
            $catIdBatch = $this->model->getCategoryIdsBatch($productIds);

            // Collect unique category IDs
            $allCatIds = [];
            foreach ($catIdBatch as $pCatIds) {
                foreach ($pCatIds as $catId) {
                    $allCatIds[$catId] = true;
                }
            }
            $allCatIds = array_keys($allCatIds);

            // Fetch category names & slugs in one query
            $categoryMap = [];
            $categorySlugMap = [];
            if ($allCatIds !== []) {
                $catRows = $this->model->getCategoriesByIds($allCatIds);
                foreach ($catRows as $cat) {
                    $categoryMap[$cat['id']] = $cat['name'];
                    $categorySlugMap[$cat['id']] = $cat['slug'];
                }
            }

            // Pre-fetch translations if needed
            $productTranslations = [];
            $catTranslationMap = [];
            if ($langVal !== null && $langVal !== 'es') {
                foreach ($productIds as $pid) {
                    $trans = $this->model->getTranslation($pid, $langVal);
                    if ($trans !== null) {
                        $productTranslations[$pid] = $trans;
                    }
                }
                foreach ($allCatIds as $catId) {
                    $trans = $this->model->getCategoryTranslation((string) $catId, $langVal);
                    if ($trans !== null && $trans['name'] !== '') {
                        $catTranslationMap[$catId] = $trans['name'];
                    }
                }
            }

            foreach ($productIds as $pid) {
                $product = $this->buildProductFromBatches(
                    $pid, $rawRows, $detailsBatch, $imagesBatch, $colorsBatch,
                    $sizesBatch, $variantsBatch, $matrixBatch, $catIdBatch,
                    $categoryMap, $categorySlugMap, $catTranslationMap, $productTranslations, $langVal,
                );
                if ($product !== null) {
                    $items[] = $product;
                }
            }
        }

        $this->jsonResponse(['items' => $items, 'total' => $total]);
    }

    public function get(): void
    {
        $slug = $this->queryString('slug');
        $id = $this->queryString('id');
        $lang = $this->queryString('lang');
        $langVal = $lang !== '' ? $lang : null;

        if ($slug !== '') {
            $row = $this->model->findRawBySlug($slug);
        } elseif ($id !== '') {
            $row = $this->model->findRawById($id);
        } else {
            $this->jsonError('Product slug or ID required');
        }

        if (!$row) {
            $this->jsonError('Product not found', 404);
        }

        /** @var string $productId */
        $productId = is_string($row['id'] ?? null) ? $row['id'] : '';
        $this->jsonResponse($this->buildProduct($productId, $langVal));
    }

    public function create(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->body();
        $name = $this->str($body, 'name');
        $price = $this->flt($body, 'price');

        if ($name === '' || $price <= 0) {
            $this->jsonError('Name and price required');
        }

        $images = $this->arr($body, 'images');
        if (count($images) > 20) {
            $this->jsonError('Maximum 20 images allowed');
        }

        $id = $this->str($body, 'id');
        if ($id === '') {
            $id = 'prod-' . bin2hex(random_bytes(4));
        }
        $slug = $this->str($body, 'slug');
        if ($slug === '') {
            $slug = Str::slugify($name);
        }

        $product = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'description' => $this->str($body, 'description'),
            'details' => $this->val($body, 'details'),
            'price' => $price,
            'currency' => $this->str($body, 'currency', 'USD'),
            'size_prefix' => $this->str($body, 'size_prefix', 'EU'),
            'images' => $images,
            'category' => $this->str($body, 'category', 'Heels'),
            'colors' => $this->arr($body, 'colors'),
            'sizes' => $this->arr($body, 'sizes'),
            'stocks' => $this->arr($body, 'stocks'),
            'isFeatured' => (bool) $this->val($body, 'isFeatured', false),
            'metaTitle' => $this->str($body, 'metaTitle'),
            'metaDescription' => $this->str($body, 'metaDescription'),
            'ogImageUrl' => $this->str($body, 'ogImageUrl'),
            'lowStockThreshold' => max(0, min(99, $this->int($body, 'lowStockThreshold', 5))),
            'createdAt' => date('Y-m-d H:i:s'),
        ];

        $this->saveProduct($product);
        $this->getAuth()->logAction('create', 'product', $id, 'Created product: ' . $name);
        $this->jsonResponse($product, 201);
    }

    public function update(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->body();
        $inputId = $this->str($body, 'id');
        if ($inputId === '') {
            $this->jsonError('Product ID required');
        }

        $existing = $this->model->findRawById($inputId);
        if (!$existing) {
            $this->jsonError('Product not found', 404);
        }

        /** @var string $existingId */
        $existingId = is_string($existing['id'] ?? null) ? $existing['id'] : '';
        $existingFull = $this->buildProduct($existingId);

        /** @var string $existingName */
        $existingName = is_string($existing['name'] ?? null) ? $existing['name'] : '';
        /** @var string $existingSlug */
        $existingSlug = is_string($existing['slug'] ?? null) ? $existing['slug'] : '';
        /** @var string $existingDesc */
        $existingDesc = is_string($existing['description'] ?? null) ? $existing['description'] : '';
        /** @var string $existingCurrency */
        $existingCurrency = is_string($existing['currency'] ?? null) ? $existing['currency'] : 'USD';
        /** @var string $existingSizePrefix */
        $existingSizePrefix = is_string($existing['size_prefix'] ?? null) ? $existing['size_prefix'] : 'EU';
        /** @var string $existingMetaTitle */
        $existingMetaTitle = is_string($existing['meta_title'] ?? null) ? $existing['meta_title'] : '';
        /** @var string $existingMetaDesc */
        $existingMetaDesc = is_string($existing['meta_description'] ?? null) ? $existing['meta_description'] : '';
        /** @var string $existingOgImage */
        $existingOgImage = is_string($existing['og_image_url'] ?? null) ? $existing['og_image_url'] : '';
        /** @var string $existingCreatedAt */
        $existingCreatedAt = is_string($existing['created_at'] ?? null) ? $existing['created_at'] : date('Y-m-d H:i:s');
        $existingLowStock = is_numeric($existing['low_stock_threshold'] ?? null) ? (int) $existing['low_stock_threshold'] : 5;
        $existingPrice = is_numeric($existing['price'] ?? null) ? (float) $existing['price'] : 0.0;

        $name = $this->str($body, 'name');
        if ($name === '') {
            $name = $existingName;
        }
        $images = $this->arr($body, 'images');
        if ($images === []) {
            $images = is_array($existingFull['images'] ?? null) ? $existingFull['images'] : [];
        }
        if (count($images) > 20) {
            $this->jsonError('Maximum 20 images allowed');
        }

        $price = $existingPrice;
        if ($this->val($body, 'price') !== null) {
            $price = $this->flt($body, 'price');
        }

        $product = [
            'id' => $existingId,
            'name' => $name,
            'slug' => $this->str($body, 'slug') !== ''
                ? $this->str($body, 'slug')
                : ($name !== $existingName ? Str::slugify($name) : $existingSlug),
            'description' => $this->str($body, 'description') !== ''
                ? $this->str($body, 'description')
                : $existingDesc,
            'details' => array_key_exists('details', $body) ? $this->val($body, 'details') : ($existingFull['details'] ?? null),
            'price' => $price,
            'currency' => $this->str($body, 'currency') !== '' ? $this->str($body, 'currency') : $existingCurrency,
            'size_prefix' => $this->str($body, 'size_prefix') !== '' ? $this->str($body, 'size_prefix') : $existingSizePrefix,
            'images' => $images,
            'category' => $this->str($body, 'category') !== '' ? $this->str($body, 'category') : ($existingFull['category'] ?? ''),
            'colors' => $this->arr($body, 'colors') !== [] ? $this->arr($body, 'colors') : ($existingFull['colors'] ?? []),
            'sizes' => $this->arr($body, 'sizes') !== [] ? $this->arr($body, 'sizes') : ($existingFull['sizes'] ?? []),
            'stocks' => $this->arr($body, 'stocks'),
            'isFeatured' => (bool) ($this->val($body, 'isFeatured') ?? ($existing['is_featured'] ?? false)),
            'metaTitle' => $this->str($body, 'metaTitle') !== '' ? $this->str($body, 'metaTitle') : $existingMetaTitle,
            'metaDescription' => $this->str($body, 'metaDescription') !== '' ? $this->str($body, 'metaDescription') : $existingMetaDesc,
            'ogImageUrl' => $this->str($body, 'ogImageUrl') !== '' ? $this->str($body, 'ogImageUrl') : $existingOgImage,
            'lowStockThreshold' => max(0, min(99, $this->int($body, 'lowStockThreshold', $existingLowStock))),
            'createdAt' => $existingCreatedAt,
        ];

        $this->saveProduct($product);

        $changes = [];
        foreach (['name', 'price', 'description', 'category'] as $field) {
            $oldVal = $existingFull[$field] ?? '';
            $newVal = $this->val($body, $field);
            if ($newVal !== null && (is_string($newVal) ? $newVal : '') !== (is_string($oldVal) ? $oldVal : '')) {
                $changes[$field] = ['from' => $oldVal, 'to' => $newVal];
            }
        }
        $this->getAuth()->logAction('update', 'product', $existingId, 'Updated product: ' . $name, $changes);
        $this->jsonResponse($product);
    }

    public function delete(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $body = $this->body();
        $inputId = $this->str($body, 'id');
        if ($inputId === '') {
            $this->jsonError('Product ID required');
        }

        $images = $this->model->getImageFileIds($inputId);
        foreach ($images as $img) {
            try {
                $this->imageKit->delete($img['file_id']);
            } catch (\Exception $e) {
                error_log('ImageKit cleanup failed for product ' . $inputId . ', file ' . $img['file_id'] . ': ' . $e->getMessage());
            }
            $this->model->deleteImageById($img['id']);
        }

        $this->model->softDelete($inputId);
        $this->getAuth()->logAction('delete', 'product', $inputId, 'Deleted product: ' . $inputId);
        $this->jsonResponse(['success' => true]);
    }

    // =========================================================================
    //  Private helpers
    // =========================================================================

    /**
     * Build full product response from raw DB row.
     * @return array<string, mixed>
     */
    private function buildProduct(string $id, ?string $lang = null): array
    {
        $row = $this->model->findRawById($id);
        if (!$row) {
            return [];
        }

        $description = is_string($row['description'] ?? null) ? $row['description'] : '';
        $detailsArr = array_column($this->model->getDetails($id), 'detail_text');

        // Apply translations if lang is provided and not Spanish
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

        // Images
        $imageRows = $this->model->getImages($id);
        $images = [];
        $imageDetails = [];
        $imagesByColor = [];
        foreach ($imageRows as $r) {
            $colorName = $r['color_id'] !== null ? $this->getColorNameFromId($id, $r['color_id']) : null;
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

        // Category
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

        // Colors
        $colorRows = $this->model->getColors($id);
        $colorsArr = array_map(fn(array $c): array => [
            'name' => $c['name'],
            'hex' => $c['hex'],
        ], $colorRows);

        // Sizes + variants
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

        $result = $this->getCurrencyModel()->addDisplayPricesToProduct($result);
        return $result;
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
    private function buildProductFromBatches(
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

        // Apply translations if lang is provided and not Spanish
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

        // Images — use pre-fetched colors to resolve color_id → name
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

        // Category
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

        // Colors
        $colorsArr = array_map(fn(array $c): array => [
            'name' => $c['name'],
            'hex' => $c['hex'],
        ], $colorRows);

        // Sizes + variants
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

        return $this->getCurrencyModel()->addDisplayPricesToProduct($result);
    }

    private function getColorNameFromId(string $productId, int $colorId): ?string
    {
        $colors = $this->model->getColors($productId);
        foreach ($colors as $c) {
            if ($c['id'] === $colorId) {
                return $c['name'];
            }
        }
        return null;
    }

    /** @param array<string, mixed> $product */
    private function saveProduct(array $product): void
    {
        /** @var string $productId */
        $productId = is_string($product['id'] ?? null) ? $product['id'] : '';
        $this->model->beginTransaction();
        try {
            $exists = $this->model->findRawById($productId);
            if ($exists !== null) {
                $this->model->updateProduct($product);
            } else {
                $this->model->insertProduct($product);
            }

            /** @var list<string> $details */
            $details = is_array($product['details'] ?? null) ? $product['details'] : [];
            $this->model->replaceDetails($productId, $details);
            $this->model->replaceCategories($productId, is_string($product['category'] ?? null) ? $product['category'] : null);

            /** @var array<array{name: string, hex: string}> $colors */
            $colors = is_array($product['colors'] ?? null) ? $product['colors'] : [];
            $colorIdMap = $this->model->replaceColors($productId, $colors);
            /** @var array<array{label: string, value: string}> $sizes */
            $sizes = is_array($product['sizes'] ?? null) ? $product['sizes'] : [];
            $sizeIdMap = $this->model->replaceSizes($productId, $sizes);
            /** @var array<string, int> $stocks */
            $stocks = is_array($product['stocks'] ?? null) ? $product['stocks'] : [];
            $this->model->replaceVariants($productId, $colorIdMap, $sizeIdMap, $stocks);
            /** @var array<int, string|array{url: string, fileId?: ?string, colorName?: ?string}> $images */
            $images = is_array($product['images'] ?? null) ? $product['images'] : [];
            $this->model->replaceImages($productId, $images, $colorIdMap);

            $this->model->commit();
        } catch (\Exception $e) {
            $this->model->rollback();
            throw $e;
        }
    }
}
