<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CouponModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;

final class CouponController
{
    use ApiResponse;

    private ?AuthService $auth = null;

    public function __construct(private CouponModel $model) {}

    private function getAuth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService(new UserModel(\App\Config\Database::getConnection()));
        }
        return $this->auth;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    private function queryString(string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_string($val) ? $val : $default;
    }

    private function queryInt(string $key): ?int
    {
        /** @var mixed $val */
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : null;
    }

    /** @param array<string, mixed> $data */
    private function str(array $data, string $key, string $default = ''): string
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        if (is_string($val)) return $val;
        if (is_scalar($val)) return (string) $val;
        return $default;
    }

    /** @param array<string, mixed> $data */
    private function flt(array $data, string $key, float $default = 0.0): float
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (float) $val : $default;
    }

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $default = 0): int
    {
        /** @var mixed $val */
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
    }

    private function isPost(): bool
    {
        /** @var mixed $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    // =========================================================================
    //  API methods
    // =========================================================================

    public function list(): void
    {
        try {
            $result = $this->model->getAll(
                $this->queryInt('limit'),
                $this->queryInt('offset'),
                $this->queryString('search') ?: null,
            );
            $this->jsonResponse($result);
        } catch (\Throwable $e) {
            $this->jsonError('Error listing coupons: ' . $e->getMessage(), 500);
        }
    }

    public function listActive(): void
    {
        try {
            if (!$this->isPost() && $_SERVER['REQUEST_METHOD'] !== 'GET') {
                $this->jsonError('Method not allowed', 405);
            }
            $coupons = $this->model->getActiveCoupons();
            $this->jsonResponse($coupons);
        } catch (\Throwable $e) {
            $this->jsonError('Error listing active coupons: ' . $e->getMessage(), 500);
        }
    }

    public function get(): void
    {
        try {
            $id = $this->queryInt('id');
            if ($id === null || $id === 0) {
                $this->jsonError('Coupon ID is required');
            }
            $coupon = $this->model->getById($id);
            if ($coupon === null) {
                $this->jsonError('Coupon not found', 404);
            }
            $this->jsonResponse($coupon);
        } catch (\Throwable $e) {
            $this->jsonError('Error getting coupon: ' . $e->getMessage(), 500);
        }
    }

    public function create(): void
    {
        try {
            if (!$this->isPost()) {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $rawCode = $this->str($body, 'code');
            $discountType = $this->str($body, 'discount_type');

            if ($rawCode === '' || $discountType === '') {
                $this->jsonError('Code and discount_type are required');
            }

            $code = strtoupper(trim($rawCode));
            if (strlen($code) > 50) {
                $this->jsonError('Code must be 50 characters or less');
            }
            if (!in_array($discountType, ['percentage', 'fixed'], true)) {
                $this->jsonError('Invalid discount type');
            }

            $description = $this->str($body, 'description');
            if (strlen($description) > 255) {
                $this->jsonError('Description must be 255 characters or less');
            }

            $discountValue = $this->flt($body, 'discount_value');
            if ($discountType === 'percentage' && $discountValue > 100) {
                $this->jsonError('Percentage discount cannot exceed 100%');
            }

            $maxUses = $this->int($body, 'max_uses');
            if ($maxUses < 0) {
                $this->jsonError('Max uses must be a positive number');
            }
            $maxUsesPerCustomer = $this->int($body, 'max_uses_per_customer');
            if ($maxUsesPerCustomer < 0) {
                $this->jsonError('Max uses per customer must be a positive number');
            }

            $startsAt = $this->str($body, 'starts_at');
            $expiresAt = $this->str($body, 'expires_at');
            if ($startsAt !== '' && $expiresAt !== '' && $expiresAt <= $startsAt) {
                $this->jsonError('Expiry date must be after the start date');
            }

            $existing = $this->model->getByCode($code);
            if ($existing !== null) {
                $this->jsonError('A coupon with this code already exists');
            }

            $coupon = [
                'code' => $code,
                'description' => $description,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'min_order_amount' => $this->valOrNull($body, 'min_order_amount'),
                'max_uses' => $maxUses > 0 ? $maxUses : null,
                'max_uses_per_customer' => $maxUsesPerCustomer > 0 ? $maxUsesPerCustomer : null,
                'is_active' => (bool) ($body['is_active'] ?? true),
                'starts_at' => $startsAt !== '' ? $startsAt : null,
                'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            ];

            $id = $this->model->insertCoupon($coupon);
            $coupon['id'] = $id;
            $this->getAuth()->logAction('create', 'coupon', (string) $id, 'Created coupon: ' . $code);
            $this->jsonResponse($coupon, 201);
        } catch (\Throwable $e) {
            $this->jsonError('Error creating coupon: ' . $e->getMessage(), 500);
        }
    }

    public function update(): void
    {
        try {
            if (!$this->isPost()) {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $id = $this->int($body, 'id');
            if ($id === 0) {
                $this->jsonError('Coupon ID is required');
            }

            $existing = $this->model->getById($id);
            if ($existing === null) {
                $this->jsonError('Coupon not found', 404);
            }

            $rawCode = $this->str($body, 'code');
            $code = $rawCode !== '' ? strtoupper(trim($rawCode)) : (string) ($existing['code'] ?? '');
            if (strlen($code) > 50) {
                $this->jsonError('Code must be 50 characters or less');
            }

            $discountType = $this->str($body, 'discount_type') ?: (string) ($existing['discount_type'] ?? 'percentage');
            if (!in_array($discountType, ['percentage', 'fixed'], true)) {
                $this->jsonError('Invalid discount type');
            }

            $description = array_key_exists('description', $body)
                ? $this->str($body, 'description')
                : (string) ($existing['description'] ?? '');
            if (strlen($description) > 255) {
                $this->jsonError('Description must be 255 characters or less');
            }

            $discountValue = array_key_exists('discount_value', $body)
                ? $this->flt($body, 'discount_value')
                : (float) ($existing['discount_value'] ?? 0);
            if ($discountType === 'percentage' && $discountValue > 100) {
                $this->jsonError('Percentage discount cannot exceed 100%');
            }

            $maxUses = array_key_exists('max_uses', $body)
                ? $this->int($body, 'max_uses')
                : (int) ($existing['max_uses'] ?? 0);
            if ($maxUses < 0) {
                $this->jsonError('Max uses must be a positive number');
            }

            $maxUsesPerCustomer = array_key_exists('max_uses_per_customer', $body)
                ? $this->int($body, 'max_uses_per_customer')
                : (int) ($existing['max_uses_per_customer'] ?? 0);
            if ($maxUsesPerCustomer < 0) {
                $this->jsonError('Max uses per customer must be a positive number');
            }

            $minOrderAmount = array_key_exists('min_order_amount', $body)
                ? $this->valOrNull($body, 'min_order_amount')
                : ($existing['min_order_amount'] ?? null);

            $startsAt = array_key_exists('starts_at', $body)
                ? $this->str($body, 'starts_at')
                : (string) ($existing['starts_at'] ?? '');
            $expiresAt = array_key_exists('expires_at', $body)
                ? $this->str($body, 'expires_at')
                : (string) ($existing['expires_at'] ?? '');
            if ($startsAt !== '' && $expiresAt !== '' && $expiresAt <= $startsAt) {
                $this->jsonError('Expiry date must be after the start date');
            }

            $data = [
                'id' => $id,
                'code' => $code,
                'description' => $description,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'min_order_amount' => $minOrderAmount,
                'max_uses' => $maxUses > 0 ? $maxUses : null,
                'max_uses_per_customer' => $maxUsesPerCustomer > 0 ? $maxUsesPerCustomer : null,
                'is_active' => array_key_exists('is_active', $body)
                    ? (bool) $body['is_active']
                    : (bool) ($existing['is_active'] ?? true),
                'starts_at' => $startsAt !== '' ? $startsAt : null,
                'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            ];

            $this->model->updateCoupon($data);
            $this->getAuth()->logAction('update', 'coupon', (string) $id, 'Updated coupon: ' . $code);
            $this->jsonResponse($data);
        } catch (\Throwable $e) {
            $this->jsonError('Error updating coupon: ' . $e->getMessage(), 500);
        }
    }

    public function delete(): void
    {
        try {
            if (!$this->isPost()) {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $id = $this->int($body, 'id');
            if ($id === 0) {
                $this->jsonError('Coupon ID is required');
            }

            $code = $this->str($body, 'code', (string) $id);
            $this->model->deleteCoupon($id);
            $this->getAuth()->logAction('delete', 'coupon', (string) $id, 'Deleted coupon: ' . $code);
            $this->jsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError('Error deleting coupon: ' . $e->getMessage(), 500);
        }
    }

    public function validate(): void
    {
        try {
            /** @var mixed $method */
            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            if (!is_string($method) || $method !== 'POST') {
                $this->jsonError('Method not allowed', 405);
            }

            $body = $this->input();
            $rawCode = $this->str($body, 'code');
            if ($rawCode === '') {
                $this->jsonError('Coupon code is required');
            }

            $code = strtoupper(trim($rawCode));
            $subtotal = $this->flt($body, 'subtotal');
            $customerEmail = $this->str($body, 'email') ?: null;

            $coupon = $this->model->getByCode($code);
            if ($coupon === null) {
                $this->jsonResponse(['valid' => false, 'error' => 'Coupon not found']);
                return;
            }

        $now = date('Y-m-d H:i:s');

        /** @var mixed $rawIsActive */
        $rawIsActive = $coupon['is_active'] ?? false;
        if (!$rawIsActive) {
            $this->jsonResponse(['valid' => false, 'error' => 'Coupon is inactive']);
            return;
        }

        /** @var mixed $rawStartsAt */
        $rawStartsAt = $coupon['starts_at'] ?? null;
        if (is_string($rawStartsAt) && $rawStartsAt !== '' && $now < $rawStartsAt) {
            $this->jsonResponse(['valid' => false, 'error' => 'Coupon is not yet valid']);
            return;
        }

        /** @var mixed $rawExpiresAt */
        $rawExpiresAt = $coupon['expires_at'] ?? null;
        if (is_string($rawExpiresAt) && $rawExpiresAt !== '' && $now > $rawExpiresAt) {
            $this->jsonResponse(['valid' => false, 'error' => 'Coupon has expired']);
            return;
        }

        /** @var mixed $rawMinAmount */
        $rawMinAmount = $coupon['min_order_amount'] ?? null;
        if (is_numeric($rawMinAmount) && (float) $rawMinAmount > 0 && $subtotal < (float) $rawMinAmount) {
            $fmt = number_format((float) $rawMinAmount, 2);
            $this->jsonResponse(['valid' => false, 'error' => "Minimum order amount of \${$fmt} required"]);
            return;
        }

        /** @var mixed $rawMaxUses */
        $rawMaxUses = $coupon['max_uses'] ?? null;
        if (is_numeric($rawMaxUses) && (int) $rawMaxUses > 0) {
            $uses = $this->model->getUsageCount((int) $coupon['id']);
            if ($uses >= (int) $rawMaxUses) {
                $this->jsonResponse(['valid' => false, 'error' => 'Coupon usage limit reached']);
                return;
            }
        }

        /** @var mixed $rawMaxPerCustomer */
        $rawMaxPerCustomer = $coupon['max_uses_per_customer'] ?? null;
        if (is_numeric($rawMaxPerCustomer) && (int) $rawMaxPerCustomer > 0 && $customerEmail !== null && $customerEmail !== '') {
            $customerUses = $this->model->getCustomerUsageCount((int) $coupon['id'], $customerEmail);
            if ($customerUses >= (int) $rawMaxPerCustomer) {
                $this->jsonResponse(['valid' => false, 'error' => 'You have already used this coupon']);
                return;
            }
        }

        /** @var mixed $rawDiscountValue */
        $rawDiscountValue = $coupon['discount_value'] ?? 0;
        /** @var mixed $rawDiscountType */
        $rawDiscountType = $coupon['discount_type'] ?? 'percentage';

        if ((string) $rawDiscountType === 'percentage') {
            $discount = round($subtotal * ((float) $rawDiscountValue / 100), 2);
        } else {
            $discount = min((float) $rawDiscountValue, $subtotal);
        }

        $this->jsonResponse([
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
        ]);
        } catch (\Throwable $e) {
            $this->jsonError('Error validating coupon: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function valOrNull(array $data, string $key): ?float
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }
        /** @var mixed $val */
        $val = $data[$key];
        if ($val === null || $val === '' || $val === false) {
            return null;
        }
        return is_numeric($val) ? (float) $val : null;
    }
}
