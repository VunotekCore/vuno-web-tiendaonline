<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CartModel;
use App\Models\CustomerModel;
use App\Traits\ApiResponse;

final class CartController
{
    use ApiResponse;

    public function __construct(
        private CartModel $cartModel,
        private CustomerModel $customerModel,
    ) {}

    /** @return never */
    public function sync(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $idRaw */
        $idRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($idRaw) ? (int) $idRaw : 0;
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $input['items'] ?? $input['cart'] ?? [];
        if (!\is_array($items)) {
            $items = [];
        }

        $this->cartModel->replaceAll($customerId, $items);
        $cart = $this->cartModel->getByCustomer($customerId);
        $this->jsonResponse(['items' => $cart]);
    }

    /** @return never */
    public function add(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $idRaw */
        $idRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($idRaw) ? (int) $idRaw : 0;
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $productId = isset($input['product_id']) && \is_string($input['product_id'])
            ? $input['product_id']
            : (isset($input['productId']) && \is_string($input['productId']) ? $input['productId'] : '');
        $quantity = isset($input['quantity']) && \is_numeric($input['quantity'])
            ? (int) $input['quantity']
            : 1;
        $color = isset($input['selected_color']) && \is_string($input['selected_color'])
            ? $input['selected_color']
            : (isset($input['selectedColor']) && \is_string($input['selectedColor']) ? $input['selectedColor'] : '');
        $size = isset($input['selected_size']) && \is_string($input['selected_size'])
            ? $input['selected_size']
            : (isset($input['selectedSize']) && \is_string($input['selectedSize']) ? $input['selectedSize'] : '');

        if ($productId === '') {
            $this->jsonError('Product ID is required', 400);
        }

        $this->cartModel->addItem($customerId, $productId, $quantity, $color, $size);
        $cart = $this->cartModel->getByCustomer($customerId);
        $this->jsonResponse(['items' => $cart]);
    }

    /** @return never */
    public function remove(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $idRaw */
        $idRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($idRaw) ? (int) $idRaw : 0;
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $productId = isset($input['product_id']) && \is_string($input['product_id'])
            ? $input['product_id']
            : (isset($input['productId']) && \is_string($input['productId']) ? $input['productId'] : '');
        $color = isset($input['selected_color']) && \is_string($input['selected_color'])
            ? $input['selected_color']
            : (isset($input['selectedColor']) && \is_string($input['selectedColor']) ? $input['selectedColor'] : '');
        $size = isset($input['selected_size']) && \is_string($input['selected_size'])
            ? $input['selected_size']
            : (isset($input['selectedSize']) && \is_string($input['selectedSize']) ? $input['selectedSize'] : '');

        if ($productId === '') {
            $this->jsonError('Product ID is required', 400);
        }

        $this->cartModel->removeItem($customerId, $productId, $color, $size);
        $cart = $this->cartModel->getByCustomer($customerId);
        $this->jsonResponse(['items' => $cart]);
    }

    /** @return never */
    public function clear(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $idRaw */
        $idRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($idRaw) ? (int) $idRaw : 0;
        $this->cartModel->clear($customerId);
        $this->jsonResponse(['success' => true]);
    }

    /** @return array<string, mixed>|null */
    private function getAuthCustomer(): ?array
    {
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) && \is_string($_SERVER['HTTP_AUTHORIZATION'])
            ? $_SERVER['HTTP_AUTHORIZATION']
            : '';
        $token = '';
        if (\str_starts_with($header, 'Bearer ')) {
            $token = \substr($header, 7);
        }
        if ($token === '') {
            return null;
        }
        return $this->customerModel->getSessionByToken($token);
    }
}
