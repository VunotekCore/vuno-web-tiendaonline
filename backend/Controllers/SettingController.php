<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\CouponModel;
use App\Models\CurrencyModel;
use App\Models\SettingModel;
use App\Models\SizeGuideModel;
use App\Traits\ApiResponse;

final class SettingController
{
    use ApiResponse;

    private ?CurrencyModel $currencyModel = null;
    private ?CategoryModel $categoryModel = null;
    private ?CouponModel $couponModel = null;
    private ?SizeGuideModel $sizeGuideModel = null;

    public function __construct(
        private SettingModel $model
    ) {}

    private function getCurrencyModel(): CurrencyModel
    {
        if ($this->currencyModel === null) {
            $this->currencyModel = new CurrencyModel(\App\Config\Database::getConnection());
        }
        return $this->currencyModel;
    }

    private function getCategoryModel(): CategoryModel
    {
        if ($this->categoryModel === null) {
            $this->categoryModel = new CategoryModel(\App\Config\Database::getConnection());
        }
        return $this->categoryModel;
    }

    private function getCouponModel(): CouponModel
    {
        if ($this->couponModel === null) {
            $this->couponModel = new CouponModel(\App\Config\Database::getConnection());
        }
        return $this->couponModel;
    }

    private function getSizeGuideModel(): SizeGuideModel
    {
        if ($this->sizeGuideModel === null) {
            $this->sizeGuideModel = new SizeGuideModel(\App\Config\Database::getConnection());
        }
        return $this->sizeGuideModel;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    // ── GET ──────────────────────────────────────────────────────────────────

    /** GET: returns all settings plus extra data (admin full view) */
    public function get(): void
    {
        try {
            $settings = $this->model->getAll();
            $storeCurrency = $this->getCurrencyModel()->getStoreCurrency();
            $settings['categories'] = $this->getCategoryModel()->getAll(null, null, null);
            $settings['currencies'] = $this->getCurrencyModel()->getAll();
            $settings['storeCurrency'] = $storeCurrency['code'] ?? 'NIO';
            $settings['size_guide_rows'] = $this->getSizeGuideModel()->getAll();
            $settings['active_coupons'] = $this->getCouponModel()->getActiveCoupons();
            $this->jsonResponse($settings);
        } catch (\Throwable $e) {
            $this->jsonError('Error al obtener configuración: ' . $e->getMessage(), 500);
        }
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────

    /** POST: saves settings from request body */
    public function update(): void
    {
        $input = $this->input();
        if (empty($input)) {
            $this->jsonError('No data provided', 400);
        }

        try {
            $this->model->save($input);
            $this->jsonResponse(['success' => true, 'message' => 'Configuración guardada correctamente']);
        } catch (\Throwable $e) {
            $this->jsonError('Error al guardar configuración: ' . $e->getMessage(), 500);
        }
    }

    // ── PUBLIC ───────────────────────────────────────────────────────────────

    /** GET: returns public-facing settings (no auth required) */
    public function public(): void
    {
        try {
            $settings = $this->model->getBySections(['store', 'stripe', 'transfer', 'landing', 'policies', 'size_guide', 'whatsapp', 'tax']);
            $storeCurrency = $this->getCurrencyModel()->getStoreCurrency();
            if ($storeCurrency === null) {
                $storeCurrency = ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1.0, 'decimal_places' => 2];
            }

            $public = [
                'store' => [
                    'name'        => $settings['store']['name'] ?? 'Vunotek',
                    'slogan'      => $settings['store']['slogan'] ?? '',
                    'description' => $settings['store']['description'] ?? '',
                    'logo'        => $settings['store']['logo'] ?? '',
                    'email'       => $settings['store']['email'] ?? '',
                ],
                'stripe' => [
                    'enabled'        => $settings['stripe']['enabled'] ?? false,
                    'publishableKey' => $settings['stripe']['publishableKey'] ?? '',
                ],
                'transfer' => [
                    'enabled' => $settings['transfer']['enabled'] ?? true,
                    'banks'   => $settings['transfer']['banks'] ?? [],
                ],
                'currency' => [
                    'code'           => $storeCurrency['code'],
                    'symbol'         => $storeCurrency['symbol'],
                    'name'           => $storeCurrency['name'],
                    'exchange_rate'  => $storeCurrency['exchange_rate'],
                    'decimal_places' => $storeCurrency['decimal_places'],
                ],
                'landing' => $this->mergeReviewsIntoLanding($settings['landing'] ?? []),
                'policies' => [
                    'shipping_es' => $settings['policies']['shipping_es'] ?? '',
                    'shipping_en' => $settings['policies']['shipping_en'] ?? '',
                    'returns_es'  => $settings['policies']['returns_es'] ?? '',
                    'returns_en'  => $settings['policies']['returns_en'] ?? '',
                    'privacy_es'  => $settings['policies']['privacy_es'] ?? '',
                    'privacy_en'  => $settings['policies']['privacy_en'] ?? '',
                ],
                'size_guide' => [
                    'title_es' => $settings['size_guide']['title_es'] ?? 'Guía de Talles',
                    'title_en' => $settings['size_guide']['title_en'] ?? 'Size Guide',
                    'footer_es' => $settings['size_guide']['footer_es'] ?? '',
                    'footer_en' => $settings['size_guide']['footer_en'] ?? '',
                ],
                'whatsapp' => [
                    'enabled' => $settings['whatsapp']['enabled'] ?? false,
                    'number'  => $settings['whatsapp']['number'] ?? '',
                    'message' => $settings['whatsapp']['message'] ?? '',
                ],
                'tax' => [
                    'rate' => $settings['tax']['rate'] ?? '',
                ],
            ];

            $this->jsonResponse($public);
        } catch (\Throwable $e) {
            $this->jsonError('Error al obtener configuración pública: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Append approved product reviews as testimonial items.
     */
    private function mergeReviewsIntoLanding(array $landing): array
    {
        $db = \App\Config\Database::getConnection();

        $rows = $db->query(
            "SELECT reviewer_name, rating, comment
             FROM product_reviews
             WHERE is_approved = 1 AND comment IS NOT NULL AND comment != ''
             ORDER BY created_at DESC
             LIMIT 9"
        )->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === [] || $rows === false) {
            return $landing;
        }

        if (!isset($landing['testimonials']['items']) || !is_array($landing['testimonials']['items'])) {
            $landing['testimonials']['items'] = [];
        }

        foreach ($rows as $r) {
            $landing['testimonials']['items'][] = [
                'name'   => $r['reviewer_name'] ?: 'Cliente',
                'rating' => (int) $r['rating'],
                'text'   => $r['comment'],
            ];
        }

        return $landing;
    }
}
