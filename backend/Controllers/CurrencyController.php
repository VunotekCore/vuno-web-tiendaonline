<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CurrencyModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;

final class CurrencyController
{
    use ApiResponse;

    public function __construct(
        private CurrencyModel $model,
        private ?AuthService $auth = null,
    ) {
        $this->auth ??= new AuthService(new \App\Models\UserModel(\App\Config\Database::getConnection()));
    }

    private function isPost(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    public function list(): void
    {
        $showAll = isset($_GET['all']) && $_GET['all'] === '1';

        if ($showAll) {
            $this->auth->startSession();
            if (!$this->auth->isLoggedIn()) {
                $this->jsonError('Unauthorized', 401);
            }
        }

        $currencies = $this->model->getAll(!$showAll);
        $storeCurrency = $this->model->getStoreCurrency();

        $this->jsonResponse([
            'currencies' => $currencies,
            'storeCurrency' => $storeCurrency['code'] ?? 'NIO',
        ]);
    }

    public function create(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $data = $this->input();
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $symbol = trim($data['symbol'] ?? '');
        $rate = (float) ($data['exchange_rate'] ?? 1.0);
        $decimals = (int) ($data['decimal_places'] ?? 2);
        $sortOrder = (int) ($data['sort_order'] ?? 0);

        if (strlen($code) !== 3) {
            $this->jsonError('Currency code must be 3 characters');
        }
        if ($name === '') {
            $this->jsonError('Currency name is required');
        }
        if ($symbol === '') {
            $this->jsonError('Currency symbol is required');
        }
        if ($rate <= 0) {
            $this->jsonError('Exchange rate must be greater than 0');
        }
        if ($this->model->exists($code)) {
            $this->jsonError('Currency code already exists', 409);
        }

        $this->model->create([
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'exchange_rate' => $rate,
            'decimal_places' => $decimals,
            'sort_order' => $sortOrder,
        ]);

        $this->auth->logAction('create', 'currency', $code, "Created currency: {$code} - {$name}");

        $this->jsonResponse([
            'success' => true,
            'currency' => [
                'code' => $code,
                'name' => $name,
                'symbol' => $symbol,
                'exchange_rate' => $rate,
                'decimal_places' => $decimals,
                'is_active' => true,
            ],
        ], 201);
    }

    public function delete(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $data = $this->input();
        $code = strtoupper(trim($data['code'] ?? ''));

        if ($code === '') {
            $this->jsonError('Currency code required');
        }
        if ($code === 'USD') {
            $this->jsonError('Cannot delete base currency (USD)', 400);
        }

        $storeCurrency = $this->model->getStoreCurrency();
        if ($storeCurrency !== null && ($storeCurrency['code'] ?? '') === $code) {
            $this->jsonError('Cannot delete the active store currency. Change store currency first.', 400);
        }

        $this->model->deactivate($code);
        $this->auth->logAction('deactivate', 'currency', $code, "Deactivated currency: {$code}");

        $this->jsonResponse(['success' => true, 'code' => $code]);
    }

    public function updateRate(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $data = $this->input();

        if (isset($data['rates']) && is_array($data['rates'])) {
            $this->model->bulkUpdate($data['rates']);
            $this->auth->logAction('update', 'currency_rates', 'bulk', 'Updated currency exchange rates');
            $this->jsonResponse(['success' => true]);
        }

        $code = strtoupper(trim($data['code'] ?? ''));
        $rate = (float) ($data['exchange_rate'] ?? 0);

        if (strlen($code) !== 3) {
            $this->jsonError('Invalid currency code');
        }
        if ($rate <= 0) {
            $this->jsonError('Exchange rate must be greater than 0');
        }

        $this->model->updateRate($code, $rate);

        if (!empty($data['set_as_store'])) {
            $this->model->setAsStoreCurrency($code);
        }

        $this->auth->logAction('update', 'currency_rate', $code, "Updated {$code} exchange rate to {$rate}");

        $this->jsonResponse(['success' => true, 'code' => $code, 'exchange_rate' => $rate]);
    }
}
