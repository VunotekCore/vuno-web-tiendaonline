<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Models\SizeGuideModel;
use App\Traits\ApiResponse;

final class SizeGuideController
{
    use ApiResponse;

    public function __construct(
        private SizeGuideModel $model,
        private SettingModel $settingModel,
    ) {}

    public function public(): void
    {
        $settings = $this->settingModel->getAll();
        $sg = $settings['size_guide'] ?? [];

        $this->jsonResponse([
            'title_es' => $sg['title_es'] ?? 'Guía de Talles',
            'title_en' => $sg['title_en'] ?? 'Size Guide',
            'footer_es' => $sg['footer_es'] ?? '',
            'footer_en' => $sg['footer_en'] ?? '',
            'rows' => $this->model->getAll(),
        ]);
    }

    public function saveAll(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->jsonError('Invalid JSON', 400);
        }

        $rows = $input['rows'] ?? [];
        if (!is_array($rows)) {
            $this->jsonError('rows must be an array', 400);
        }

        $this->model->replaceAll($rows);
        $this->jsonResponse(['ok' => true, 'count' => count($rows)]);
    }
}
