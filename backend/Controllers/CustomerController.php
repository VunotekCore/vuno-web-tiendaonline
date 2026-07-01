<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AddressModel;
use App\Models\CustomerModel;
use App\Models\EmailTemplateModel;
use App\Models\SubscriberModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Traits\ApiResponse;

final class CustomerController
{
    use ApiResponse;

    private ?AuthService $auth = null;
    private ?EmailService $emailService = null;
    private ?AddressModel $addressModel = null;

    public function __construct(
        private CustomerModel $customerModel,
    ) {}

    private function getAuth(): AuthService
    {
        if ($this->auth === null) {
            $this->auth = new AuthService(new UserModel(\App\Config\Database::getConnection()));
        }
        return $this->auth;
    }

    private function getEmailService(): EmailService
    {
        if ($this->emailService === null) {
            $db = \App\Config\Database::getConnection();
            $this->emailService = new EmailService(new EmailTemplateModel($db), new SubscriberModel($db));
        }
        return $this->emailService;
    }

    private function getAddressModel(): AddressModel
    {
        if ($this->addressModel === null) {
            $this->addressModel = new AddressModel(\App\Config\Database::getConnection());
        }
        return $this->addressModel;
    }

    // =========================================================================
    //  Admin — List Customers
    // =========================================================================

    /** @return never */
    public function adminList(): void
    {
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : null;
        $offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int) $_GET['offset'] : null;
        $search = isset($_GET['search']) && is_string($_GET['search']) && $_GET['search'] !== ''
            ? $_GET['search']
            : null;

        $result = $this->customerModel->getAll($limit, $offset, $search);
        $this->jsonResponse($result);
    }

    // =========================================================================
    //  Admin — Get Customer Detail
    // =========================================================================

    /** @return never */
    public function adminGet(): void
    {
        $id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            $this->jsonError('Invalid customer ID', 400);
        }

        $customer = $this->customerModel->getById($id);
        if ($customer === null) {
            $this->jsonError('Customer not found', 404);
        }

        $this->jsonResponse($customer);
    }

    // =========================================================================
    //  Admin — Delete Customer
    // =========================================================================

    /** @return never */
    public function adminDelete(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }
        $id = isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0;
        if ($id <= 0) {
            $this->jsonError('Invalid customer ID', 400);
        }

        $customer = $this->customerModel->getById($id);
        if ($customer === null) {
            $this->jsonError('Customer not found', 404);
        }

        $this->customerModel->delete($id);
        $email = isset($customer['email']) && \is_string($customer['email']) ? $customer['email'] : '';
        $this->getAuth()->logAction('Deleted customer', 'customer', (string) $id, $email);
        $this->jsonResponse(['success' => true]);
    }

    // =========================================================================
    //  Admin — Update Customer
    // =========================================================================

    /** @return never */
    public function adminUpdate(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($body)) {
            $this->jsonError('Invalid request body', 400);
        }

        $id = isset($body['id']) && is_numeric($body['id']) ? (int) $body['id'] : 0;
        if ($id <= 0) {
            $this->jsonError('Invalid customer ID', 400);
        }

        $existing = $this->customerModel->getById($id);
        if ($existing === null) {
            $this->jsonError('Customer not found', 404);
        }

        $fields = [];

        if (isset($body['name']) && \is_string($body['name']) && \trim($body['name']) !== '') {
            $fields['name'] = \trim($body['name']);
        }
        if (isset($body['email']) && \is_string($body['email'])) {
            $newEmail = \strtolower(\trim($body['email']));
            if (!\filter_var($newEmail, \FILTER_VALIDATE_EMAIL)) {
                $this->jsonError('Email inválido', 400);
            }
            if ($this->customerModel->existsByEmail($newEmail, $id)) {
                $this->jsonError('El email ya está en uso por otro cliente', 409);
            }
            $fields['email'] = $newEmail;
        }
        if (isset($body['phone']) && \is_string($body['phone'])) {
            $fields['phone'] = \trim($body['phone']);
        }
        if (isset($body['notes']) && \is_string($body['notes'])) {
            $fields['notes'] = \trim($body['notes']);
        }
        if (isset($body['is_verified']) && \is_bool($body['is_verified'])) {
            $fields['is_verified'] = $body['is_verified'] ? 1 : 0;
        }

        if ($fields === []) {
            $this->jsonError('No hay campos para actualizar', 400);
        }

        $this->customerModel->update($id, $fields);
        $this->getAuth()->logAction('update', 'customer', (string) $id, print_r($fields, true));

        $updated = $this->customerModel->getById($id);
        $this->jsonResponse($updated);
    }

    // =========================================================================
    //  Admin — Address CRUD
    // =========================================================================

    /** @return never */
    public function adminCreateAddress(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($body)) {
            $this->jsonError('Invalid request body', 400);
        }

        $customerId = isset($body['customer_id']) && is_numeric($body['customer_id']) ? (int) $body['customer_id'] : 0;
        if ($customerId <= 0) {
            $this->jsonError('Invalid customer ID', 400);
        }

        $customer = $this->customerModel->getById($customerId);
        if ($customer === null) {
            $this->jsonError('Customer not found', 404);
        }

        if (!isset($body['address_line1']) || !\is_string($body['address_line1']) || trim($body['address_line1']) === '') {
            $this->jsonError('La dirección es requerida', 400);
        }

        $addressId = $this->getAddressModel()->create($customerId, $body);
        $this->getAuth()->logAction('create', 'customer_address', (string) $addressId, "Created address for customer {$customerId}");

        $created = $this->getAddressModel()->getById($addressId, $customerId);
        $this->jsonResponse($created);
    }

    /** @return never */
    public function adminUpdateAddress(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($body)) {
            $this->jsonError('Invalid request body', 400);
        }

        $addressId = isset($body['id']) && is_numeric($body['id']) ? (int) $body['id'] : 0;
        $customerId = isset($body['customer_id']) && is_numeric($body['customer_id']) ? (int) $body['customer_id'] : 0;
        if ($addressId <= 0 || $customerId <= 0) {
            $this->jsonError('Invalid address or customer ID', 400);
        }

        $existing = $this->getAddressModel()->getById($addressId, $customerId);
        if ($existing === null) {
            $this->jsonError('Address not found', 404);
        }

        $this->getAddressModel()->update($addressId, $customerId, $body);
        $this->getAuth()->logAction('update', 'customer_address', (string) $addressId, "Updated address {$addressId} for customer {$customerId}");

        $updated = $this->getAddressModel()->getById($addressId, $customerId);
        $this->jsonResponse($updated);
    }

    /** @return never */
    public function adminDeleteAddress(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($body)) {
            $this->jsonError('Invalid request body', 400);
        }

        $addressId = isset($body['id']) && is_numeric($body['id']) ? (int) $body['id'] : 0;
        $customerId = isset($body['customer_id']) && is_numeric($body['customer_id']) ? (int) $body['customer_id'] : 0;
        if ($addressId <= 0 || $customerId <= 0) {
            $this->jsonError('Invalid address or customer ID', 400);
        }

        $this->getAddressModel()->delete($addressId, $customerId);
        $this->getAuth()->logAction('delete', 'customer_address', (string) $addressId, "Deleted address {$addressId} for customer {$customerId}");

        $this->jsonResponse(['success' => true]);
    }

    // =========================================================================
    //  Admin — Quick Create Customer (POS)
    // =========================================================================

    /** @return never */
    public function quickCreate(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($body)) {
            $this->jsonError('Invalid request body', 400);
        }
        $name = isset($body['name']) && is_string($body['name']) ? trim($body['name']) : '';
        $email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';
        $phone = isset($body['phone']) && is_string($body['phone']) ? trim($body['phone']) : '';

        if ($name === '') {
            $this->jsonError('El nombre del cliente es requerido', 400);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Email válido es requerido', 400);
        }
        if ($this->customerModel->existsByEmail($email)) {
            $this->jsonError('Ya existe un cliente con ese email', 400);
        }

        $id = $this->customerModel->create($name, $email, '', true);
        if ($id <= 0) {
            $this->jsonError('Error al crear el cliente', 500);
        }

        $this->getAuth()->logAction('create', 'customer', (string) $id, "Quick-create POS: {$name} <{$email}>");

        $this->jsonResponse([
            'success' => true,
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }

    // =========================================================================
    //  Customer — Register
    // =========================================================================

    /** @return never */
    public function register(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $name = isset($input['name']) && \is_string($input['name']) ? \trim($input['name']) : '';
        $email = isset($input['email']) && \is_string($input['email']) ? \strtolower(\trim($input['email'])) : '';
        $password = isset($input['password']) && \is_string($input['password']) ? $input['password'] : '';

        if ($name === '' || $email === '' || $password === '') {
            $this->jsonError('Name, email, and password are required', 400);
        }
        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Invalid email address', 400);
        }
        if (\strlen($password) < 6) {
            $this->jsonError('Password must be at least 6 characters', 400);
        }

        if ($this->customerModel->existsByEmail($email)) {
            $this->jsonError('An account with this email already exists', 409);
        }

        $passwordHash = \password_hash($password, \PASSWORD_BCRYPT);
        $customerId = $this->customerModel->create($name, $email, $passwordHash, true);
        $this->customerModel->linkGuestOrders($customerId, $email);
        $this->customerModel->updateLastOrderAt($customerId);

        $token = $this->createCustomerToken($customerId, $email);

        /** @var string $host */
        $host = isset($_SERVER['HTTP_HOST']) && \is_string($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:4321';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $storeUrl = $scheme . '://' . $host;

        try {
            $this->getEmailService()->sendTemplatedEmail('welcome', $email, [
                'customer_name' => $name,
                'store_url'     => $storeUrl,
            ]);
        } catch (\Throwable $e) {
            // Silently ignore email errors
        }

        $this->jsonResponse([
            'success'  => true,
            'token'    => $token,
            'customer' => [
                'id'    => $customerId,
                'name'  => $name,
                'email' => $email,
            ],
        ], 201);
    }

    // =========================================================================
    //  Customer — Login
    // =========================================================================

    /** @return never */
    public function login(): void
    {
        try {
            $ip = isset($_SERVER['REMOTE_ADDR']) && \is_string($_SERVER['REMOTE_ADDR'])
                ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

            // Rate limiting
            $this->customerModel->ensureLoginAttemptsTable();
            $failedAttempts = $this->customerModel->getFailedLoginAttemptCount($ip, 900);
            if ($failedAttempts >= 5) {
                \http_response_code(429);
                \header('Retry-After: 900');
                $this->jsonError('Too many login attempts. Try again in 15 minutes.', 429);
            }

            $raw = \file_get_contents('php://input');
            /** @var array<string, mixed>|null $input */
            $input = $raw !== false ? \json_decode($raw, true) : null;
            if (!\is_array($input)) {
                $this->jsonError('Invalid request body', 400);
            }

            $email = isset($input['email']) && \is_string($input['email']) ? \strtolower(\trim($input['email'])) : '';
            $password = isset($input['password']) && \is_string($input['password']) ? $input['password'] : '';

            if ($email === '' || $password === '') {
                $this->jsonError('Email and password are required', 400);
            }

            $customer = $this->customerModel->findByEmail($email);
            if ($customer === null) {
                $this->customerModel->recordLoginAttempt($ip, false);
                $this->jsonError('Invalid email or password', 401);
            }

            $hash = isset($customer['password_hash']) && \is_string($customer['password_hash']) ? $customer['password_hash'] : '';
            if (!\password_verify($password, $hash)) {
                $this->customerModel->recordLoginAttempt($ip, false);
                $this->jsonError('Invalid email or password', 401);
            }

            $this->customerModel->recordLoginAttempt($ip, true);
            $this->customerModel->clearLoginAttempts($ip);

            /** @var mixed $customerIdRaw */
            $customerIdRaw = $customer['id'] ?? null;
            $customerId = \is_numeric($customerIdRaw) ? (int) $customerIdRaw : 0;
            /** @var mixed $customerName */
            $customerName = $customer['name'] ?? null;
            $name = \is_string($customerName) ? $customerName : '';
            $token = $this->createCustomerToken($customerId, $email);

            $this->jsonResponse([
                'success'  => true,
                'token'    => $token,
                'customer' => [
                    'id'    => $customerId,
                    'name'  => $name,
                    'email' => $email,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->jsonError('Login failed: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    //  Customer — Verify Token
    // =========================================================================

    /** @return never */
    public function verify(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $customerIdRaw */
        $customerIdRaw = $customer['customer_id'] ?? null;
        $this->jsonResponse([
            'success'  => true,
            'customer' => [
                'id'    => \is_numeric($customerIdRaw) ? (int) $customerIdRaw : 0,
                'name'  => $customer['name'] ?? '',
                'email' => $customer['email'] ?? '',
            ],
        ]);
    }

    // =========================================================================
    //  Customer — Logout
    // =========================================================================

    /** @return never */
    public function logout(): void
    {
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) && \is_string($_SERVER['HTTP_AUTHORIZATION'])
            ? $_SERVER['HTTP_AUTHORIZATION']
            : '';
        $token = '';
        if (\str_starts_with($header, 'Bearer ')) {
            $token = \substr($header, 7);
        }
        if ($token === '') {
            $this->jsonResponse(['success' => true]);
        }

        $this->customerModel->deleteSession($token);
        $this->jsonResponse(['success' => true]);
    }

    // =========================================================================
    //  Customer — Update Profile
    // =========================================================================

    /** @return never */
    public function update(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $customerIdRaw */
        $customerIdRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($customerIdRaw) ? (int) $customerIdRaw : 0;
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $fields = [];

        if (isset($input['name']) && \is_string($input['name']) && \trim($input['name']) !== '') {
            $fields['name'] = \trim($input['name']);
        }
        if (isset($input['phone']) && \is_string($input['phone'])) {
            $fields['phone'] = \trim($input['phone']);
        }
        if (isset($input['email']) && \is_string($input['email'])) {
            $newEmail = \strtolower(\trim($input['email']));
            if (!\filter_var($newEmail, \FILTER_VALIDATE_EMAIL)) {
                $this->jsonError('Invalid email address', 400);
            }
            if ($this->customerModel->existsByEmail($newEmail, $customerId)) {
                $this->jsonError('Email already in use', 409);
            }
            $fields['email'] = $newEmail;
        }
        if (isset($input['password']) && \is_string($input['password']) && $input['password'] !== '') {
            if (\strlen($input['password']) < 6) {
                $this->jsonError('Password must be at least 6 characters', 400);
            }
            $fields['password_hash'] = \password_hash($input['password'], \PASSWORD_BCRYPT);
        }

        if ($fields === []) {
            $this->jsonError('No fields to update', 400);
        }

        $this->customerModel->update($customerId, $fields);
        $this->jsonResponse(['success' => true]);
    }

    // =========================================================================
    //  Customer — Forgot Password
    // =========================================================================

    /** @return never */
    public function forgotPassword(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $email = isset($input['email']) && \is_string($input['email']) ? \strtolower(\trim($input['email'])) : '';
        if ($email === '' || !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Valid email is required', 400);
        }

        $customer = $this->customerModel->findByEmail($email);
        if ($customer === null) {
            // Don't reveal if email exists
            $this->jsonResponse(['success' => true, 'message' => 'If the email exists, a reset link has been sent']);
        }

        $this->customerModel->deleteOldPasswordResets($email);

        $token = \bin2hex(\random_bytes(32));
        $expiresAt = \date('Y-m-d H:i:s', \time() + 3600);
        $this->customerModel->createPasswordReset($email, $token, $expiresAt);

        /** @var string $host */
        $host = isset($_SERVER['HTTP_HOST']) && \is_string($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:4321';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $storeUrl = $scheme . '://' . $host;
        $resetUrl = $storeUrl . '/reset-password?token=' . $token;

        try {
            $name = isset($customer['name']) && \is_string($customer['name']) ? $customer['name'] : '';
            $this->getEmailService()->sendTemplatedEmail('password_reset', $email, [
                'customer_name' => $name,
                'reset_url'     => $resetUrl,
                'store_url'     => $storeUrl,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => true, 'message' => 'If the email exists, a reset link has been sent']);
        }

        $this->jsonResponse(['success' => true, 'message' => 'If the email exists, a reset link has been sent']);
    }

    // =========================================================================
    //  Customer — Reset Password
    // =========================================================================

    /** @return never */
    public function resetPassword(): void
    {
        $raw = \file_get_contents('php://input');
        /** @var array<string, mixed>|null $input */
        $input = $raw !== false ? \json_decode($raw, true) : null;
        if (!\is_array($input)) {
            $this->jsonError('Invalid request body', 400);
        }

        $token = isset($input['token']) && \is_string($input['token']) ? $input['token'] : '';
        $password = isset($input['password']) && \is_string($input['password']) ? $input['password'] : '';

        if ($token === '' || $password === '') {
            $this->jsonError('Token and password are required', 400);
        }
        if (\strlen($password) < 6) {
            $this->jsonError('Password must be at least 6 characters', 400);
        }

        $reset = $this->customerModel->getPasswordResetByToken($token);
        if ($reset === null) {
            $this->jsonError('Invalid or expired reset token', 400);
        }

        $email = isset($reset['email']) && \is_string($reset['email']) ? $reset['email'] : '';
        $customer = $this->customerModel->findByEmail($email);
        if ($customer === null) {
            $this->jsonError('Customer not found', 404);
        }

        /** @var mixed $customerIdRaw */
        $customerIdRaw = $customer['id'] ?? null;
        $customerId = \is_numeric($customerIdRaw) ? (int) $customerIdRaw : 0;
        /** @var mixed $resetIdRaw */
        $resetIdRaw = $reset['id'] ?? null;
        $resetId = \is_numeric($resetIdRaw) ? (int) $resetIdRaw : 0;

        $this->customerModel->update($customerId, [
            'password_hash' => \password_hash($password, \PASSWORD_BCRYPT),
        ]);
        $this->customerModel->markPasswordResetUsed($resetId);
        $this->customerModel->deleteAllSessions($customerId);

        $this->jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
    }

    // =========================================================================
    //  Customer — List Orders
    // =========================================================================

    /** @return never */
    public function orders(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $customerIdRaw */
        $customerIdRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($customerIdRaw) ? (int) $customerIdRaw : 0;
        $orders = $this->customerModel->getCustomerOrders($customerId);
        $this->jsonResponse(['items' => $orders]);
    }

    // =========================================================================
    //  Customer — Get Order Detail
    // =========================================================================

    /** @return never */
    public function getOrder(): void
    {
        $customer = $this->getAuthCustomer();
        if ($customer === null) {
            $this->jsonError('Unauthorized', 401);
        }

        /** @var mixed $customerIdRaw */
        $customerIdRaw = $customer['customer_id'] ?? null;
        $customerId = \is_numeric($customerIdRaw) ? (int) $customerIdRaw : 0;
        $orderNumber = isset($_GET['id']) && \is_string($_GET['id']) ? \trim($_GET['id']) : '';
        if ($orderNumber === '') {
            $this->jsonError('Order number is required', 400);
        }

        $order = $this->customerModel->getCustomerOrder($customerId, $orderNumber);
        if ($order === null) {
            $this->jsonError('Order not found', 404);
        }

        $this->jsonResponse($order);
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function createCustomerToken(int $customerId, string $email): string
    {
        $token = \bin2hex(\random_bytes(32));
        $expiresAt = \date('Y-m-d H:i:s', \time() + 86400 * 30); // 30 days
        $ip = isset($_SERVER['REMOTE_ADDR']) && \is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) && \is_string($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $this->customerModel->createSession($customerId, $token, $ip, $userAgent, $expiresAt);
        return $token;
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
