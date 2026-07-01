<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CurrencyModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\ImageKitService;
use App\Services\ProductAssembler;
use App\Traits\ApiResponse;
use App\Utils\Str;

final class ProductController
{
    use ApiResponse;

    private ?AuthService $auth = null;
    private ?CurrencyModel $currencyModel = null;
    private ?ProductAssembler $assembler = null;

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

    private function getAssembler(): ProductAssembler
    {
        if ($this->assembler === null) {
            $this->assembler = new ProductAssembler($this->model, $this->getCurrencyModel());
        }
        return $this->assembler;
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
                $product = $this->getAssembler()->buildFromBatches(
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

    public function listSummary(): void
    {
        $search = $this->queryString('search');
        $category = $this->queryString('category');
        $limit = $this->queryInt('limit');
        $offset = $this->queryInt('offset');

        $result = $this->model->findSummary(
            $limit,
            $offset,
            $search !== '' ? $search : null,
            $category !== '' ? $category : null,
        );

        foreach ($result['items'] as $k => $v) {
            $result['items'][$k] = $this->getCurrencyModel()->addDisplayPricesToProduct($v);
        }

        $this->jsonResponse($result);
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
        $this->jsonResponse($this->getAssembler()->buildSingle($productId, $langVal));
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
            'size_prefix' => $this->str($body, 'size_prefix', 'US'),
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
        $existingFull = $this->getAssembler()->buildSingle($existingId);

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

        if (!$this->productHasChanges($product, $existing, $existingFull)) {
            $this->jsonResponse(['noChanges' => true, 'product' => $product]);
        }

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
     * @param array<string, mixed> $product
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $existingFull
     */
    private function productHasChanges(array $product, array $existing, array $existingFull): bool
    {
        $scalar = [
            'name', 'slug', 'description', 'price', 'isFeatured', 'lowStockThreshold',
            'metaTitle', 'metaDescription', 'ogImageUrl',
        ];
        foreach ($scalar as $k) {
            $old = $existingFull[$k] ?? null;
            $new = $product[$k] ?? null;
            if ($old !== $new) return true;
        }

        if (json_encode($product['details'] ?? null) !== json_encode($existingFull['details'] ?? null)) return true;
        if (($product['category'] ?? '') !== ($existingFull['category'] ?? '')) return true;
        if (json_encode($product['colors'] ?? []) !== json_encode($existingFull['colors'] ?? [])) return true;
        if (json_encode($product['sizes'] ?? []) !== json_encode($existingFull['sizes'] ?? [])) return true;

        $newStocks = $product['stocks'] ?? [];
        $oldStocks = [];
        foreach ($existingFull['variants'] ?? [] as $v) {
            $oldStocks[($v['color_name'] ?? '') . '_' . ($v['size_value'] ?? '')] = (int) ($v['stock'] ?? 0);
        }
        if (json_encode($newStocks) !== json_encode($oldStocks)) return true;

        $existingImageDetails = $existingFull['imageDetails'] ?? [];
        $normalizedOld = array_map(fn(array $i): array => [
            'url' => $i['url'] ?? '',
            'fileId' => $i['fileId'] ?? '',
            'colorName' => $i['colorName'] ?? null,
        ], $existingImageDetails);
        if (json_encode($product['images'] ?? []) !== json_encode($normalizedOld)) return true;

        return false;
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
