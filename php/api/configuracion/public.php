<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/currency.php';

setCorsHeaders();

$settings = getSettings();
$storeCurrency = getStoreCurrency();

$public = [
    'store' => [
        'name' => $settings['store']['name'] ?? 'Ram;Lop',
        'slogan' => $settings['store']['slogan'] ?? '',
        'logo' => $settings['store']['logo'] ?? '',
    ],
    'stripe' => [
        'enabled' => $settings['stripe']['enabled'] ?? false,
        'publishableKey' => $settings['stripe']['publishableKey'] ?? '',
    ],
    'transfer' => [
        'enabled' => $settings['transfer']['enabled'] ?? true,
        'banks' => $settings['transfer']['banks'] ?? [],
    ],
    'currency' => [
        'code' => $storeCurrency['code'],
        'symbol' => $storeCurrency['symbol'],
        'name' => $storeCurrency['name'],
        'exchange_rate' => $storeCurrency['exchange_rate'],
        'decimal_places' => $storeCurrency['decimal_places'],
    ],
];

jsonResponse($public);
