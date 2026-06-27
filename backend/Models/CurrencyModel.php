<?php
declare(strict_types=1);

namespace App\Models;

final class CurrencyModel
{
    public function __construct(private \PDO $db) {}

    /** @return array<int, array<string, mixed>> */
    public function getAll(bool $activeOnly = false): array
    {
        $where = $activeOnly ? ' WHERE is_active = 1' : '';
        $stmt = $this->db->query("SELECT * FROM currencies{$where} ORDER BY sort_order, code");
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return array_map(fn(array $r): array => $this->normalize($r), $rows);
    }

    /** @return ?array<string, mixed> */
    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM currencies WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row !== false ? $this->normalize($row) : null;
    }

    /** @return ?array<string, mixed> */
    public function getStoreCurrency(): ?array
    {
        try {
            $stmt = $this->db->query("SELECT `value` FROM settings WHERE section = 'currency' AND `key` = 'code' LIMIT 1");
            $code = $stmt !== false ? (string) $stmt->fetchColumn() : '';
            if ($code === '') {
                $code = 'NIO';
            }
            return $this->getByCode($code);
        } catch (\Throwable) {
            return null;
        }
    }

    public function exists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM currencies WHERE code = ?');
        $stmt->execute([$code]);
        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO currencies (code, name, symbol, exchange_rate, decimal_places, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['symbol'],
            $data['exchange_rate'],
            $data['decimal_places'],
            $data['sort_order'],
        ]);
    }

    public function deactivate(string $code): void
    {
        $this->db->prepare('UPDATE currencies SET is_active = 0 WHERE code = ?')->execute([$code]);
    }

    /** @param array<int, array{code: string, exchange_rate: float, is_active: bool, sort_order: int}> $rates */
    public function bulkUpdate(array $rates): void
    {
        $stmt = $this->db->prepare('UPDATE currencies SET exchange_rate = ?, is_active = ?, sort_order = ? WHERE code = ?');
        foreach ($rates as $r) {
            $stmt->execute([
                (float) ($r['exchange_rate'] ?? 1.0),
                !empty($r['is_active']) ? 1 : 0,
                (int) ($r['sort_order'] ?? 0),
                strtoupper(trim($r['code'] ?? '')),
            ]);
        }
    }

    public function updateRate(string $code, float $rate): void
    {
        $this->db->prepare('UPDATE currencies SET exchange_rate = ? WHERE code = ?')->execute([$rate, $code]);
    }

    public function setAsStoreCurrency(string $code): void
    {
        $this->db->prepare(
            "INSERT INTO settings (section, `key`, `value`) VALUES ('currency', 'code', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        )->execute([$code]);
    }

    public function convertFromUsd(float $usdPrice, ?float $exchangeRate = null, ?int $decimals = null): float
    {
        if ($exchangeRate === null) {
            $store = $this->getStoreCurrency();
            $exchangeRate = $store['exchange_rate'] ?? 1.0;
        }
        if ($decimals === null) {
            $store = $this->getStoreCurrency();
            $decimals = $store['decimal_places'] ?? 2;
        }
        return round($usdPrice * $exchangeRate, $decimals);
    }

    /**
     * Add display_* fields to a product array based on store currency.
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function addDisplayPricesToProduct(array $product): array
    {
        $storeCurrency = $this->getStoreCurrency();
        if ($storeCurrency === null) {
            $product['display_price'] = (float) ($product['price'] ?? 0);
            $product['display_currency'] = 'USD';
            $product['display_symbol'] = '$';
            return $product;
        }

        $rate = (float) $storeCurrency['exchange_rate'];
        $decimals = (int) $storeCurrency['decimal_places'];

        $product['display_price'] = $this->convertFromUsd((float) ($product['price'] ?? 0), $rate, $decimals);
        $product['display_currency'] = $storeCurrency['code'];
        $product['display_symbol'] = $storeCurrency['symbol'];

        return $product;
    }

    /**
     * Add display_* fields to an order array.
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function addDisplayPricesToOrder(array $order): array
    {
        $displayCurrency = $order['display_currency'] ?? $order['currency'] ?? 'USD';
        $rate = (float) ($order['exchange_rate'] ?? 1.0);

        $currency = $this->getByCode($displayCurrency);
        if ($currency === null) {
            $currency = ['code' => 'USD', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 1.0];
        }

        $symbol = $currency['symbol'] ?? '$';
        $decimals = (int) ($currency['decimal_places'] ?? 2);

        $order['display_currency'] = $displayCurrency;
        $order['display_symbol'] = $symbol;

        if (isset($order['subtotal'])) {
            $order['display_subtotal'] = $this->convertFromUsd((float) $order['subtotal'], $rate, $decimals);
        }
        if (isset($order['shipping'])) {
            $order['display_shipping'] = $this->convertFromUsd((float) $order['shipping'], $rate, $decimals);
        }
        if (isset($order['tax'])) {
            $order['display_tax'] = $this->convertFromUsd((float) $order['tax'], $rate, $decimals);
        }
        if (isset($order['total'])) {
            $order['display_total'] = $this->convertFromUsd((float) $order['total'], $rate, $decimals);
        }
        if (isset($order['discountTotal'])) {
            $order['display_discount'] = $this->convertFromUsd((float) $order['discountTotal'], $rate, $decimals);
        }

        return $order;
    }

    /** @param array<string, mixed> $row */
    private function normalize(array $row): array
    {
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'symbol' => $row['symbol'],
            'exchange_rate' => (float) ($row['exchange_rate'] ?? 1.0),
            'decimal_places' => (int) ($row['decimal_places'] ?? 2),
            'is_active' => (bool) ($row['is_active'] ?? false),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
}
