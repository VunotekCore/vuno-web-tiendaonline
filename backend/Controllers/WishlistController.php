<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\WishlistModel;
use App\Models\CustomerModel;
use App\Traits\ApiResponse;

final class WishlistController
{
    use ApiResponse;

    public function __construct(
        private WishlistModel $wishlistModel,
        private CustomerModel $customerModel,
    ) {}

    /** @return never */
    public function list(): void
    {
        $customerId = $this->resolveCustomerId();
        if ($customerId === null) {
            $this->jsonError('Customer not found', 404);
        }

        $items = $this->wishlistModel->getByCustomer($customerId);
        $this->jsonResponse(['items' => $items]);
    }

    /** @return never */
    public function check(): void
    {
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
    }

    /** @return never */
    public function add(): void
    {
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
    }

    /** @return never */
    public function remove(): void
    {
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
    }

    /** @return int|null */
    private function resolveCustomerId(): ?int
    {
        // Try Bearer token first
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

        // Fallback: email in query param or POST body
        $email = '';
        if (isset($_GET['email']) && \is_string($_GET['email'])) {
            $email = \strtolower(\trim($_GET['email']));
        } elseif (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw = \file_get_contents('php://input');
            /** @var mixed $body */
            $body = $raw !== false ? \json_decode($raw, true) : null;
            if (\is_array($body) && isset($body['email']) && \is_string($body['email'])) {
                $email = \strtolower(\trim($body['email']));
            }
        }

        if ($email !== '') {
            $customer = $this->customerModel->findByEmail($email);
            if ($customer !== null) {
                /** @var mixed $customerId */
                $customerId = $customer['id'] ?? null;
                if (\is_numeric($customerId)) {
                    return (int) $customerId;
                }
            }
        }

        return null;
    }
}
