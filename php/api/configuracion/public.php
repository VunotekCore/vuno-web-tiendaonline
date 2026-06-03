<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$settings = getSettings();

$public = [
    'stripe' => [
        'enabled' => $settings['stripe']['enabled'] ?? false,
        'publishableKey' => $settings['stripe']['publishableKey'] ?? '',
    ],
    'transfer' => [
        'enabled' => $settings['transfer']['enabled'] ?? true,
        'banks' => $settings['transfer']['banks'] ?? [],
    ],
];

jsonResponse($public);
