<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Traits\ApiResponse;

final class ShippingController
{
    use ApiResponse;

    public function __construct(private SettingModel $settingModel) {}

    public function calculate(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $subtotal = isset($input['subtotal']) && is_numeric($input['subtotal'])
            ? max(0, (float) $input['subtotal']) : 0;

        $settings = $this->settingModel->getAll();
        $shippingConfig = $settings['shipping'] ?? [];

        $enabled = !empty($shippingConfig['enabled']);
        $baseRate = (float) ($shippingConfig['base_rate'] ?? 0);
        $freeAbove = (float) ($shippingConfig['free_above'] ?? 0);
        $estimatedDays = (string) ($shippingConfig['estimated_days'] ?? '');

        if (!$enabled || $baseRate <= 0) {
            $this->jsonResponse([
                'shipping' => 0,
                'label' => 'Gratis',
                'estimated_days' => $estimatedDays,
            ]);
        }

        if ($freeAbove > 0 && $subtotal >= $freeAbove) {
            $this->jsonResponse([
                'shipping' => 0,
                'label' => 'Gratis',
                'estimated_days' => $estimatedDays,
                'free_above' => true,
            ]);
        }

        $this->jsonResponse([
            'shipping' => $baseRate,
            'label' => 'S/' . number_format($baseRate, 2),
            'estimated_days' => $estimatedDays,
        ]);
    }
}
