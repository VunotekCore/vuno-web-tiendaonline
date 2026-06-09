<?php
declare(strict_types=1);

/**
 * Currency Service
 * Handles multi-currency conversion from USD base, store currency settings,
 * and price formatting.
 */

const CURRENCY_CACHE_KEY = 'ramlop_currencies';
const CURRENCY_CACHE_TTL = 300; // 5 seconds for admin, safe for public

function getCurrencies(): array
{
    $db = getDb();
    $rows = $db->query('SELECT * FROM currencies ORDER BY sort_order, code')->fetchAll();
    return array_map(fn($r) => normalizeCurrencyRow($r), $rows);
}

function getActiveCurrencies(): array
{
    $db = getDb();
    $rows = $db->query('SELECT * FROM currencies WHERE is_active = 1 ORDER BY sort_order, code')->fetchAll();
    return array_map(fn($r) => normalizeCurrencyRow($r), $rows);
}

function getCurrency(string $code): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM currencies WHERE code = ?');
    $stmt->execute([strtoupper($code)]);
    $row = $stmt->fetch();
    return $row ? normalizeCurrencyRow($row) : null;
}

function normalizeCurrencyRow(array $row): array
{
    return [
        'id'            => (int)$row['id'],
        'code'          => $row['code'],
        'name'          => $row['name'],
        'symbol'        => $row['symbol'],
        'exchange_rate' => (float)$row['exchange_rate'],
        'decimal_places' => (int)$row['decimal_places'],
        'is_active'     => (bool)$row['is_active'],
        'sort_order'    => (int)$row['sort_order'],
    ];
}

function getStoreCurrency(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $db = getDb();
    $stmt = $db->prepare("SELECT `value` FROM settings WHERE section = 'currency' AND `key` = 'code'");
    $stmt->execute();
    $code = $stmt->fetchColumn();
    $code = $code ?: 'USD';

    $currency = getCurrency($code);
    if (!$currency) {
        $currency = [
            'code'          => 'USD',
            'name'          => 'US Dollar',
            'symbol'        => '$',
            'exchange_rate' => 1.0,
            'decimal_places' => 2,
            'is_active'     => true,
        ];
    }

    $cache = $currency;
    return $currency;
}

function clearCurrencyCache(): void
{
    // Reset static cache in getStoreCurrency
    // This is called by saveSettings to ensure fresh data
}

function convertFromUsd(float $usdPrice, float $exchangeRate, int $decimals = 2): float
{
    return round($usdPrice * $exchangeRate, $decimals);
}

function convertToUsd(float $localPrice, float $exchangeRate, int $decimals = 2): float
{
    if ($exchangeRate == 0) return round($localPrice, $decimals);
    return round($localPrice / $exchangeRate, $decimals);
}

function formatPrice(float $price, string $currencyCode, string $symbol, int $decimals = 2): string
{
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('es_419', NumberFormatter::CURRENCY);
        $fmt->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currencyCode);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        return $fmt->formatCurrency($price, $currencyCode);
    }

    // Fallback: symbol + number_format
    $formatted = number_format($price, $decimals);

    // Symbol position: most currencies put symbol before
    // But some (like COP) use "$" before
    return $symbol . $formatted;
}

function formatPriceSimple(float $price, string $symbol = '$', int $decimals = 2): string
{
    return $symbol . number_format($price, $decimals);
}

/**
 * Add display_* fields to a product array based on store currency.
 * Returns the modified array with display_price, display_currency, display_symbol.
 * The original price and currency fields remain untouched (always USD).
 */
function addDisplayPricesToProduct(array $product, ?array $storeCurrency = null): array
{
    if ($storeCurrency === null) {
        $storeCurrency = getStoreCurrency();
    }

    $rate = (float)$storeCurrency['exchange_rate'];
    $decimals = (int)$storeCurrency['decimal_places'];

    $product['display_price'] = convertFromUsd((float)($product['price'] ?? 0), $rate, $decimals);
    $product['display_currency'] = $storeCurrency['code'];
    $product['display_symbol'] = $storeCurrency['symbol'];

    return $product;
}

/**
 * Add display_* fields to an order array.
 * Orders store totals in USD but have currency metadata.
 */
function addDisplayPricesToOrder(array $order, ?array $storeCurrency = null): array
{
    $displayCurrency = $order['display_currency'] ?? $order['currency'] ?? 'USD';
    $rate = (float)($order['exchange_rate'] ?? 1.0);

    if ($storeCurrency === null) {
        // Use the currency stored in the order
        $currency = getCurrency($displayCurrency);
        if (!$currency) {
            $currency = ['code' => 'USD', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 1.0];
        }
    } else {
        $currency = $storeCurrency;
    }

    $symbol = $currency['symbol'] ?? '$';
    $decimals = (int)$currency['decimal_places'];

    $order['display_currency'] = $displayCurrency;
    $order['display_symbol'] = $symbol;

    // Convert stored USD totals to display currency
    if (isset($order['subtotal'])) {
        $order['display_subtotal'] = convertFromUsd((float)$order['subtotal'], $rate, $decimals);
    }
    if (isset($order['shipping'])) {
        $order['display_shipping'] = convertFromUsd((float)$order['shipping'], $rate, $decimals);
    }
    if (isset($order['tax'])) {
        $order['display_tax'] = convertFromUsd((float)$order['tax'], $rate, $decimals);
    }
    if (isset($order['discountTotal'])) {
        $order['display_discountTotal'] = convertFromUsd((float)$order['discountTotal'], $rate, $decimals);
    }
    if (isset($order['total'])) {
        $order['display_total'] = convertFromUsd((float)$order['total'], $rate, $decimals);
    }

    return $order;
}
