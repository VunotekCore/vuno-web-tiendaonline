<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CurrencyModel;
use App\Models\CustomerModel;
use App\Models\WishlistModel;
use App\Traits\ApiResponse;

final class WishlistController
{
    use ApiResponse;

    private ?CurrencyModel $currencyModel = null;

    public function __construct(
        private WishlistModel $wishlistModel,
        private CustomerModel $customerModel,
    ) {}

    private function getCurrencyModel(): CurrencyModel
    {
        if ($this->currencyModel === null) {
            $this->currencyModel = new CurrencyModel(\App\Config\Database::getConnection());
        }
        return $this->currencyModel;
    }

    /** @return never */
    public function list(): void
    {
        try {
            $customerId = $this->resolveCustomerId();
            if ($customerId === null) {
                $this->jsonError('Customer not found', 404);
            }

            $items = $this->wishlistModel->getByCustomer($customerId);
            $this->jsonResponse(['items' => $items]);
        } catch (\Throwable $e) {
            $this->jsonError('Error loading wishlist: ' . $e->getMessage(), 500);
        }
    }

    /** @return never */
    public function check(): void
    {
        try {
            $customerId = $this->resolveCustomerId();
            if ($customerId === null) {
                $this->jsonError('Customer not found', 404);
            }

            $productId = isset($_GET['product_id']) && \is_string($_GET['product_id'])
                ? $_GET['product_id']
                : (isset($_GET['productId']) && \is_string($_GET['productId']) ? $_GET['productId'] : '');
            if ($productId === '') {
                $this->jsonError('Product ID is required', 400);
            }

            $inWishlist = $this->wishlistModel->isInWishlist($customerId, $productId);
            $this->jsonResponse(['in_wishlist' => $inWishlist]);
        } catch (\Throwable $e) {
            $this->jsonError('Error checking wishlist: ' . $e->getMessage(), 500);
        }
    }

    /** @return never */
    public function add(): void
    {
        try {
            $customerId = $this->resolveCustomerId();
            if ($customerId === null) {
                $this->jsonError('Customer not found', 404);
            }

            $raw = \file_get_contents('php://input');
            /** @var array<string, mixed>|null $input */
            $input = $raw !== false ? \json_decode($raw, true) : null;
            if (!\is_array($input)) {
                $this->jsonError('Invalid request body', 400);
            }

            $productId = isset($input['product_id']) && \is_string($input['product_id'])
                ? $input['product_id']
                : (isset($input['productId']) && \is_string($input['productId']) ? $input['productId'] : '');
            $variantId = isset($input['variant_id']) && \is_numeric($input['variant_id'])
                ? (int) $input['variant_id']
                : (isset($input['variantId']) && \is_numeric($input['variantId']) ? (int) $input['variantId'] : null);

            if ($productId === '') {
                $this->jsonError('Product ID is required', 400);
            }

            $added = $this->wishlistModel->add($customerId, $productId, $variantId);
            $this->jsonResponse(['success' => $added]);
        } catch (\Throwable $e) {
            $this->jsonError('Error adding to wishlist: ' . $e->getMessage(), 500);
        }
    }

    /** @return never */
    public function remove(): void
    {
        try {
            $customerId = $this->resolveCustomerId();
            if ($customerId === null) {
                $this->jsonError('Customer not found', 404);
            }

            $raw = \file_get_contents('php://input');
            /** @var array<string, mixed>|null $input */
            $input = $raw !== false ? \json_decode($raw, true) : null;
            if (!\is_array($input)) {
                $this->jsonError('Invalid request body', 400);
            }

            $productId = isset($input['product_id']) && \is_string($input['product_id'])
                ? $input['product_id']
                : (isset($input['productId']) && \is_string($input['productId']) ? $input['productId'] : '');
            $variantId = isset($input['variant_id']) && \is_numeric($input['variant_id'])
                ? (int) $input['variant_id']
                : (isset($input['variantId']) && \is_numeric($input['variantId']) ? (int) $input['variantId'] : null);

            if ($productId === '') {
                $this->jsonError('Product ID is required', 400);
            }

            $this->wishlistModel->remove($customerId, $productId, $variantId);
            $this->jsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError('Error removing from wishlist: ' . $e->getMessage(), 500);
        }
    }

    /** @return int|null */
    private function resolveCustomerId(): ?int
    {
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) && \is_string($_SERVER['HTTP_AUTHORIZATION'])
            ? $_SERVER['HTTP_AUTHORIZATION']
            : '';
        if (\str_starts_with($header, 'Bearer ')) {
            $token = \substr($header, 7);
            if ($token !== '') {
                $id = $this->customerModel->getCustomerIdFromToken($token);
                if ($id !== null) {
                    return $id;
                }
            }
        }

        return null;
    }
}
