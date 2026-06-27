<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\ApiResponse;

final class AuthController
{
    use ApiResponse;

    private ?SettingModel $settingModel = null;

    public function __construct(
        private AuthService $auth,
        private UserModel $userModel,
    ) {}

    private function getSettings(): SettingModel
    {
        if ($this->settingModel === null) {
            $this->settingModel = new SettingModel(\App\Config\Database::getConnection());
        }
        return $this->settingModel;
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /** @return array<string, mixed> */
    private function input(): array
    {
        $raw = \json_decode((string) \file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    private function isPost(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'POST';
    }

    private function isGet(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        return is_string($method) && $method === 'GET';
    }

    /** @param array<string, mixed> $data */
    private function str(array $data, string $key, string $default = ''): string
    {
        $val = $data[$key] ?? null;
        if (is_string($val)) return $val;
        if (is_scalar($val)) return (string) $val;
        return $default;
    }

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $default = 0): int
    {
        $val = $data[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
    }

    private function queryInt(string $key, int $default = 0): int
    {
        $val = $_GET[$key] ?? null;
        return is_numeric($val) ? (int) $val : $default;
    }

    // =========================================================================
    //  Auth Endpoints
    // =========================================================================

    /** @return never */
    public function login(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }
        $data = $this->input();
        $email = $this->str($data, 'email');
        $password = $this->str($data, 'password');

        if ($email === '' || $password === '') {
            $this->jsonError('Email and password are required');
        }

        $this->auth->login($email, $password);
    }

    /** @return never */
    public function verify(): void
    {
        if (!$this->isGet()) {
            $this->jsonError('Method not allowed', 405);
        }
        $this->auth->verifySession();
    }

    /** @return never */
    public function logout(): void
    {
        if (!$this->isGet()) {
            $this->jsonError('Method not allowed', 405);
        }
        $this->auth->logout();
    }

    // =========================================================================
    //  User Management Endpoints
    // =========================================================================

    /** @return never */
    public function listUsers(): void
    {
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $users = $this->userModel->getAll();
        $roles = $this->userModel->getRoles();

        /** @var array<int, array<string, mixed>> $items */
        $items = [];
        foreach ($users as $u) {
            $uId = is_numeric($u['id'] ?? null) ? (int) $u['id'] : 0;
            $uCreatedRaw = $u['created_at'] ?? null;
            $uCreated = is_string($uCreatedRaw) || is_numeric($uCreatedRaw)
                ? \date('c', (int) \strtotime((string) $uCreatedRaw))
                : '';
            $items[] = [
                'id'        => $uId,
                'email'     => $u['email'] ?? '',
                'name'      => $u['name'] ?? '',
                'role'      => $u['role_code'] ?? '',
                'roleName'  => $u['role_name'] ?? '',
                'createdAt' => $uCreated,
            ];
        }

        $this->jsonResponse(['items' => $items, 'roles' => $roles]);
    }

    /** @return never */
    public function createUser(): void
    {
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $data = $this->input();
        $email = \trim($this->str($data, 'email'));
        $name = \trim($this->str($data, 'name', $email));
        $password = $this->str($data, 'password');
        $role = $this->str($data, 'role', 'editor');

        if ($email === '' || $password === '') {
            $this->jsonError('Email and password are required');
        }
        if (\strlen($password) < 6) {
            $this->jsonError('Password must be at least 6 characters');
        }
        if ($this->userModel->existsByEmail($email)) {
            $this->jsonError('A user with this email already exists', 409);
        }

        $roleRow = $this->userModel->getRoleByCode($role);
        if ($roleRow === null) {
            $this->jsonError('Invalid role');
        }

        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $userId = $this->userModel->create($email, $name ?: $email, $hash, (int) $roleRow['id']);

        $this->auth->logAction('create', 'admin_user', (string) $userId, 'Created user: ' . $email);

        $this->jsonResponse(['success' => true, 'id' => $userId]);
    }

    /** @return never */
    public function updateUser(): void
    {
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $data = $this->input();
        $userId = $this->int($data, 'user_id');

        if ($userId <= 0) {
            $this->jsonError('user_id is required');
        }

        $fields = [];
        $newEmail = \trim($this->str($data, 'email'));
        $newName = \trim($this->str($data, 'name'));
        $newPassword = $this->str($data, 'password');
        $newRole = $this->str($data, 'role');

        if ($newEmail !== '') {
            if ($this->userModel->existsByEmail($newEmail, $userId)) {
                $this->jsonError('Another user already has this email', 409);
            }
            $fields['email'] = $newEmail;
        }
        if ($newName !== '') {
            $fields['name'] = $newName;
        }
        if ($newPassword !== '') {
            if (\strlen($newPassword) < 6) {
                $this->jsonError('Password must be at least 6 characters');
            }
            $fields['password_hash'] = \password_hash($newPassword, \PASSWORD_DEFAULT);
        }
        if ($newRole !== '') {
            $roleRow = $this->userModel->getRoleByCode($newRole);
            if ($roleRow === null) {
                $this->jsonError('Invalid role');
            }
            $fields['role_id'] = (int) $roleRow['id'];
        }

        if ($fields === []) {
            $this->jsonError('No fields to update');
        }

        $this->userModel->update($userId, $fields);
        $this->auth->logAction('update', 'admin_user', (string) $userId, 'Updated user');

        $this->jsonResponse(['success' => true]);
    }

    /** @return never */
    public function deleteUser(): void
    {
        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }
        $this->auth->requireRole('superadmin');

        $userId = $this->queryInt('id');
        if ($userId <= 0) {
            $this->jsonError('user_id is required');
        }

        // Prevent deleting own account
        $currentId = $this->auth->getCurrentUserId();
        if ($currentId !== null && $userId === $currentId) {
            $this->jsonError('Cannot delete your own account', 403);
        }

        $deleted = $this->userModel->delete($userId);
        if (!$deleted) {
            $this->jsonError('User not found', 404);
        }

        $this->auth->logAction('delete', 'admin_user', (string) $userId, 'Deleted user');
        $this->jsonResponse(['success' => true]);
    }

    // =========================================================================
    //  2FA Endpoints
    // =========================================================================

    /** @return never */
    public function totpSetup(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }

        $data = $this->input();
        $password = $this->str($data, 'password');
        if ($password === '') {
            $this->jsonError('Password confirmation is required', 401);
        }

        $email = $this->auth->getCurrentEmail();
        if (!$this->userModel->validatePassword($email, $password)) {
            $this->jsonError('Password confirmation is required', 401);
        }

        $userId = $this->auth->getCurrentUserId();
        if ($userId === null) {
            $this->jsonError('Unauthorized', 401);
        }

        if ($this->userModel->isTotpEnabled($userId)) {
            $this->jsonError('2FA is already enabled. Disable it first to regenerate.');
        }

        $settings = $this->getSettings()->getAll();
        $storeSection = is_array($settings['store'] ?? null) ? $settings['store'] : [];
        $storeNameRaw = $storeSection['name'] ?? null;
        $storeName = is_string($storeNameRaw) ? $storeNameRaw : 'Ram;Lop';

        $secret = $this->auth->generateTotpSecret();
        $uri = $this->auth->getProvisioningUri($secret, $email, $storeName);

        $this->userModel->setTotpSecret($userId, $secret);

        $this->jsonResponse([
            'secret'  => \chunk_split($secret, 4, ' '),
            'qrUri'   => $uri,
            'email'   => $email,
        ]);
    }

    /** @return never */
    public function totpEnable(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }

        $data = $this->input();
        $code = $this->str($data, 'code');
        if ($code === '') {
            $this->jsonError('Verification code is required');
        }

        $userId = $this->auth->getCurrentUserId();
        if ($userId === null) {
            $this->jsonError('Unauthorized', 401);
        }

        if ($this->userModel->isTotpEnabled($userId)) {
            $this->jsonError('2FA is already enabled');
        }

        $secret = $this->userModel->getTotpSecret($userId);
        if ($secret === null) {
            $this->jsonError('No secret generated. Run setup first.');
        }

        if (!$this->auth->verifyTotpCode($secret, $code)) {
            $this->jsonError('Invalid code. Try again.', 401);
        }

        $rawCodes = $this->auth->generateBackupCodes();
        $hashedCodes = \array_map(fn(string $c): string => \password_hash($c, \PASSWORD_DEFAULT), $rawCodes);

        $this->userModel->enableTotp($userId, $hashedCodes);
        $this->auth->logAction('enable_2fa', 'auth', (string) $userId, '2FA enabled');

        $this->jsonResponse([
            'success'     => true,
            'backupCodes' => $rawCodes,
        ]);
    }

    /** @return never */
    public function totpVerify(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->auth->startSession();

        if (empty($_SESSION['admin_totp_pending'])) {
            $this->jsonError('No pending 2FA verification');
        }

        $data = $this->input();
        $code = $this->str($data, 'code');
        if ($code === '') {
            $this->jsonError('Verification code is required');
        }

        $userId = isset($_SESSION['admin_totp_user_id']) && is_numeric($_SESSION['admin_totp_user_id'])
            ? (int) $_SESSION['admin_totp_user_id'] : 0;
        if ($userId <= 0) {
            $this->jsonError('Session error', 500);
        }

        $valid = false;

        // Try TOTP code
        $secret = $this->userModel->getTotpSecret($userId);
        if ($secret !== null && $this->auth->verifyTotpCode($secret, $code)) {
            $valid = true;
        }

        // Try backup code
        if (!$valid) {
            $valid = $this->auth->verifyBackupCode($userId, $code);
        }

        if (!$valid) {
            $this->jsonError('Invalid verification code', 401);
        }

        $this->auth->completeTotpLogin();
        $this->jsonResponse(['success' => true]);
    }

    /** @return never */
    public function totpStatus(): void
    {
        if (!$this->isGet()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }

        $userId = $this->auth->getCurrentUserId();
        $enabled = $userId !== null && $this->userModel->isTotpEnabled($userId);

        $this->jsonResponse(['enabled' => $enabled]);
    }

    /** @return never */
    public function totpDisable(): void
    {
        if (!$this->isPost()) {
            $this->jsonError('Method not allowed', 405);
        }

        $this->auth->startSession();
        if (!$this->auth->isLoggedIn()) {
            $this->jsonError('Unauthorized', 401);
        }

        $data = $this->input();
        $password = $this->str($data, 'password');
        $code = $this->str($data, 'code');
        if ($password === '' || $code === '') {
            $this->jsonError('Password and verification code are required');
        }

        $email = $this->auth->getCurrentEmail();
        if (!$this->userModel->validatePassword($email, $password)) {
            $this->jsonError('Invalid password', 401);
        }

        $userId = $this->auth->getCurrentUserId();
        if ($userId === null) {
            $this->jsonError('Unauthorized', 401);
        }

        if (!$this->userModel->isTotpEnabled($userId)) {
            $this->jsonError('2FA is not enabled');
        }

        $secret = $this->userModel->getTotpSecret($userId);
        if ($secret !== null && !$this->auth->verifyTotpCode($secret, $code)) {
            $this->jsonError('Invalid verification code', 401);
        }

        $this->userModel->disableTotp($userId);
        $this->auth->logAction('disable_2fa', 'auth', (string) $userId, '2FA disabled');

        $this->jsonResponse(['success' => true]);
    }
}
