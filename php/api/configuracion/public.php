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
    'landing' => [
        'hero' => [
            'label_es' => $settings['landing']['hero']['label_es'] ?? '',
            'label_en' => $settings['landing']['hero']['label_en'] ?? '',
            'title_es' => $settings['landing']['hero']['title_es'] ?? '',
            'title_en' => $settings['landing']['hero']['title_en'] ?? '',
            'subtitle_es' => $settings['landing']['hero']['subtitle_es'] ?? '',
            'subtitle_en' => $settings['landing']['hero']['subtitle_en'] ?? '',
            'cta_es' => $settings['landing']['hero']['cta_es'] ?? '',
            'cta_en' => $settings['landing']['hero']['cta_en'] ?? '',
            'cta_link' => $settings['landing']['hero']['cta_link'] ?? '',
            'cta_category_slug' => $settings['landing']['hero']['cta_category_slug'] ?? '',
        ],
        'new_arrivals' => [
            'label_es' => $settings['landing']['new_arrivals']['label_es'] ?? '',
            'label_en' => $settings['landing']['new_arrivals']['label_en'] ?? '',
            'title_es' => $settings['landing']['new_arrivals']['title_es'] ?? '',
            'title_en' => $settings['landing']['new_arrivals']['title_en'] ?? '',
            'subtitle_es' => $settings['landing']['new_arrivals']['subtitle_es'] ?? '',
            'subtitle_en' => $settings['landing']['new_arrivals']['subtitle_en'] ?? '',
            'cta_es' => $settings['landing']['new_arrivals']['cta_es'] ?? '',
            'cta_en' => $settings['landing']['new_arrivals']['cta_en'] ?? '',
            'cta_link' => $settings['landing']['new_arrivals']['cta_link'] ?? '',
            'cta_category_slug' => $settings['landing']['new_arrivals']['cta_category_slug'] ?? '',
        ],
        'categories' => [
            'label_es' => $settings['landing']['categories']['label_es'] ?? '',
            'label_en' => $settings['landing']['categories']['label_en'] ?? '',
            'title_es' => $settings['landing']['categories']['title_es'] ?? '',
            'title_en' => $settings['landing']['categories']['title_en'] ?? '',
            'cta_es' => $settings['landing']['categories']['cta_es'] ?? '',
            'cta_en' => $settings['landing']['categories']['cta_en'] ?? '',
            'cta_link' => $settings['landing']['categories']['cta_link'] ?? '',
            'cta_category_slug' => $settings['landing']['categories']['cta_category_slug'] ?? '',
        ],
        'brand_values' => [
            'enabled' => $settings['landing']['brand_values']['enabled'] ?? false,
            'image_url' => $settings['landing']['brand_values']['image_url'] ?? '',
            'label_es' => $settings['landing']['brand_values']['label_es'] ?? '',
            'label_en' => $settings['landing']['brand_values']['label_en'] ?? '',
            'title_es' => $settings['landing']['brand_values']['title_es'] ?? '',
            'title_en' => $settings['landing']['brand_values']['title_en'] ?? '',
            'paragraph_es' => $settings['landing']['brand_values']['paragraph_es'] ?? '',
            'paragraph_en' => $settings['landing']['brand_values']['paragraph_en'] ?? '',
            'cta_es' => $settings['landing']['brand_values']['cta_es'] ?? '',
            'cta_en' => $settings['landing']['brand_values']['cta_en'] ?? '',
            'cta_link' => $settings['landing']['brand_values']['cta_link'] ?? '',
            'cta_category_slug' => $settings['landing']['brand_values']['cta_category_slug'] ?? '',
        ],
        'closing_cta' => [
            'label_es' => $settings['landing']['closing_cta']['label_es'] ?? '',
            'label_en' => $settings['landing']['closing_cta']['label_en'] ?? '',
            'title_es' => $settings['landing']['closing_cta']['title_es'] ?? '',
            'title_en' => $settings['landing']['closing_cta']['title_en'] ?? '',
            'subtitle_es' => $settings['landing']['closing_cta']['subtitle_es'] ?? '',
            'subtitle_en' => $settings['landing']['closing_cta']['subtitle_en'] ?? '',
            'cta_es' => $settings['landing']['closing_cta']['cta_es'] ?? '',
            'cta_en' => $settings['landing']['closing_cta']['cta_en'] ?? '',
            'cta_link' => $settings['landing']['closing_cta']['cta_link'] ?? '',
            'cta_category_slug' => $settings['landing']['closing_cta']['cta_category_slug'] ?? '',
        ],
        'social' => [
            'enabled' => $settings['landing']['social']['enabled'] ?? false,
            'title_es' => $settings['landing']['social']['title_es'] ?? '',
            'title_en' => $settings['landing']['social']['title_en'] ?? '',
            'facebook_url' => $settings['landing']['social']['facebook_url'] ?? '',
            'instagram_url' => $settings['landing']['social']['instagram_url'] ?? '',
            'tiktok_url' => $settings['landing']['social']['tiktok_url'] ?? '',
        ],
        'newsletter' => [
            'enabled' => $settings['landing']['newsletter']['enabled'] ?? false,
            'title_es' => $settings['landing']['newsletter']['title_es'] ?? '',
            'title_en' => $settings['landing']['newsletter']['title_en'] ?? '',
            'subtitle_es' => $settings['landing']['newsletter']['subtitle_es'] ?? '',
            'subtitle_en' => $settings['landing']['newsletter']['subtitle_en'] ?? '',
            'placeholder_es' => $settings['landing']['newsletter']['placeholder_es'] ?? '',
            'placeholder_en' => $settings['landing']['newsletter']['placeholder_en'] ?? '',
            'cta_es' => $settings['landing']['newsletter']['cta_es'] ?? '',
            'cta_en' => $settings['landing']['newsletter']['cta_en'] ?? '',
        ],
        'testimonials' => [
            'enabled' => $settings['landing']['testimonials']['enabled'] ?? false,
            'title_es' => $settings['landing']['testimonials']['title_es'] ?? '',
            'title_en' => $settings['landing']['testimonials']['title_en'] ?? '',
            'subtitle_es' => $settings['landing']['testimonials']['subtitle_es'] ?? '',
            'subtitle_en' => $settings['landing']['testimonials']['subtitle_en'] ?? '',
            'items' => $settings['landing']['testimonials']['items'] ?? [],
        ],
    ],
    'policies' => [
        'shipping_es' => $settings['policies']['shipping_es'] ?? '',
        'shipping_en' => $settings['policies']['shipping_en'] ?? '',
        'returns_es' => $settings['policies']['returns_es'] ?? '',
        'returns_en' => $settings['policies']['returns_en'] ?? '',
    ],
    'size_guide' => [
        'title_es' => $settings['size_guide']['title_es'] ?? 'Guía de Talles',
        'title_en' => $settings['size_guide']['title_en'] ?? 'Size Guide',
        'footer_es' => $settings['size_guide']['footer_es'] ?? '',
        'footer_en' => $settings['size_guide']['footer_en'] ?? '',
    ],
];

jsonResponse($public);
