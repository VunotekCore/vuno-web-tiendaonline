<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AddressModel;
use App\Models\CustomerModel;
use App\Traits\ApiResponse;

final class AddressController
{
    use ApiResponse;

    public function __construct(
        private AddressModel $addressModel,
        private CustomerModel $customerModel,
    ) {}

    /** @return never */
    public function handle(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $idRaw */
        $idRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($idRaw) ? (int) $idRaw : 0;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        match ($method) {
            'GET'    => $this->listAddresses($customerId),
            'POST'   => $this->createAddress($customerId),
            'PUT'    => $this->updateAddress($customerId),
            'DELETE' => $this->deleteAddress($customerId),
            default  => $this->jsonError('Method not allowed', 405),
        };
    }

    /** @return never */
    private function listAddresses(int $customerId): void
    {
        $addresses = $this->addressModel->getByCustomer($customerId);
        $this->jsonResponse(['addresses' => $addresses]);
    }

    /** @return never */
    private function createAddress(int $customerId): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        try {
            $addressId = $this->addressModel->create($customerId, $input);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 409);
        }
        $this->jsonResponse([
            'success'   => true,
            'addressId' => $addressId,
        ], 201);
    }

    /** @return never */
    private function updateAddress(int $customerId): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $addressId = isset($input['id']) && \is_numeric($input['id']) ? (int) $input['id'] : 0;
        if ($addressId <= 0) {
            $this->jsonError('Address ID is required', 400);
        }

        $existing = $this->addressModel->getById($addressId, $customerId);
        if ($existing === null) {
            $this->jsonError('Address not found', 404);
        }

        $this->addressModel->update($addressId, $customerId, $input);
        $this->jsonResponse(['success' => true]);
    }

    /** @return never */
    private function deleteAddress(int $customerId): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $addressId = isset($input['id']) && \is_numeric($input['id']) ? (int) $input['id'] : 0;
        if ($addressId <= 0) {
            $this->jsonError('Address ID is required', 400);
        }

        $existing = $this->addressModel->getById($addressId, $customerId);
        if ($existing === null) {
            $this->jsonError('Address not found', 404);
        }

        $this->addressModel->delete($addressId, $customerId);
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
