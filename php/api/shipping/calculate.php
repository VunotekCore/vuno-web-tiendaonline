<?php
declare(strict_types=1);

/**
 * POST /api/shipping/calculate.php
 * Calculates shipping cost based on subtotal and configured rates.
 * Public endpoint — used by checkout frontend.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$subtotal = isset($input['subtotal']) ? (float)$input['subtotal'] : 0;

if ($subtotal < 0) {
    jsonError('Invalid subtotal');
}

try {
    $settings = getSettings();
    $shippingConfig = $settings['shipping'] ?? [];

    $enabled = $shippingConfig['enabled'] ?? false;
    $baseRate = (float)($shippingConfig['base_rate'] ?? 0);
    $freeAbove = (float)($shippingConfig['free_above'] ?? 0);
    $estimatedDays = $shippingConfig['estimated_days'] ?? '';

    if (!$enabled || $baseRate <= 0) {
        jsonResponse([
            'shipping' => 0,
            'label' => 'Gratis',
            'estimated_days' => $estimatedDays,
        ]);
    }

    if ($freeAbove > 0 && $subtotal >= $freeAbove) {
        jsonResponse([
            'shipping' => 0,
            'label' => 'Gratis',
            'estimated_days' => $estimatedDays,
            'free_above' => true,
        ]);
    }

    jsonResponse([
        'shipping' => $baseRate,
        'label' => 'S/' . number_format($baseRate, 2),
        'estimated_days' => $estimatedDays,
    ]);
} catch (\Exception $e) {
    jsonError('Failed to calculate shipping', 500);
}
