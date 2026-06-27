<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;

final class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes

    public function __construct(private ?UserModel $userModel = null)
    {
        $this->userModel ??= new UserModel(\App\Config\Database::getConnection());
    }

    // =========================================================================
    //  Session Management
    // =========================================================================

    public function startSession(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \configureSession();
            \session_start();
        }
    }

    public function isLoggedIn(): bool
    {
        $this->startSession();
        return !empty($_SESSION['admin_logged_in']);
    }

    public function getCurrentUserId(): ?int
    {
        $this->startSession();
        $id = $_SESSION['admin_user_id'] ?? null;
        return is_numeric($id) ? (int) $id : null;
    }

    public function getCurrentEmail(): string
    {
        $this->startSession();
        $email = $_SESSION['admin_email'] ?? null;
        return is_string($email) ? $email : '';
    }

    public function getCurrentRole(): string
    {
        $this->startSession();
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return '';
        }

        $user = $this->userModel->getById($userId);
        $roleCode = $user['role_code'] ?? null;
        return is_string($roleCode) ? $roleCode : '';
    }

    public function hasRole(string ...$roles): bool
    {
        return \in_array($this->getCurrentRole(), $roles, true);
    }

    public function requireRole(string ...$roles): void
    {
        if (!$this->hasRole(...$roles)) {
            \jsonError('Forbidden: insufficient permissions', 403);
        }
    }

    // =========================================================================
    //  Login Flow
    // =========================================================================

    public function checkRateLimit(string $ip): void
    {
        try {
            $this->userModel->ensureLoginAttemptsTable();
            $failedAttempts = $this->userModel->getFailedAttemptCount($ip, self::RATE_LIMIT_WINDOW);

            if ($failedAttempts >= self::MAX_LOGIN_ATTEMPTS) {
                \http_response_code(429);
                \header('Retry-After: ' . (string) self::RATE_LIMIT_WINDOW);
                \jsonError('Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.', 429);
            }
        } catch (\Throwable $e) {
            \error_log('Rate limit check failed: ' . $e->getMessage());
        }
    }

    /** @return never */
    public function login(string $email, string $password): void
    {
        $ip = $this->getClientIp();
        $this->checkRateLimit($ip);

        if (!$this->userModel->validatePassword($email, $password)) {
            $this->userModel->recordLoginAttempt($ip, false);
            \jsonError('Invalid credentials', 401);
        }

        $this->userModel->recordLoginAttempt($ip, true);
        $this->userModel->clearLoginAttempts($ip);

        $user = $this->userModel->getByEmail($email);
        if ($user === null) {
            \jsonError('User not found', 401);
        }

        $userId = is_numeric($user['id'] ?? null) ? (int) $user['id'] : 0;
        $totpEnabled = $this->userModel->isTotpEnabled($userId);

        $this->startSession();

        if ($totpEnabled) {
            $_SESSION['admin_totp_pending'] = true;
            $_SESSION['admin_totp_email'] = $email;
            $_SESSION['admin_totp_user_id'] = $userId;
            \session_regenerate_id(true);
            \jsonResponse(['success' => true, 'totpRequired' => true]);
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_ip'] = $ip;
        $_SESSION['admin_user_id'] = $userId;
        \session_regenerate_id(true);
        \jsonResponse(['success' => true]);
    }

    public function completeTotpLogin(): void
    {
        $this->startSession();

        $email = isset($_SESSION['admin_totp_email']) && is_string($_SESSION['admin_totp_email'])
            ? $_SESSION['admin_totp_email'] : '';
        $userId = isset($_SESSION['admin_totp_user_id']) && is_numeric($_SESSION['admin_totp_user_id'])
            ? (int) $_SESSION['admin_totp_user_id'] : 0;

        \session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_ip'] = $this->getClientIp();
        $_SESSION['admin_user_id'] = $userId;
        unset($_SESSION['admin_totp_pending'], $_SESSION['admin_totp_email'], $_SESSION['admin_totp_user_id']);

        $this->logAction('login', 'auth', (string) $userId, '2FA login: ' . $email);
    }

    /** @return never */
    public function logout(): void
    {
        $this->startSession();
        $_SESSION = [];

        if (\ini_get('session.use_cookies')) {
            $params = \session_get_cookie_params();
            \setcookie(\session_name() ?: '', '', [
                'expires' => \time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        \session_destroy();
        \jsonResponse(['success' => true]);
    }

    /** @return never */
    public function verifySession(): void
    {
        $this->startSession();

        if ($this->isLoggedIn()) {
            $role = $this->getCurrentRole();
            $_SESSION['admin_role'] = $role;
            \jsonResponse([
                'valid' => true,
                'email' => $this->getCurrentEmail(),
                'role'  => $role,
            ]);
        }

        $totpPending = !empty($_SESSION['admin_totp_pending']);
        if ($totpPending) {
            $totpEmail = isset($_SESSION['admin_totp_email']) && is_string($_SESSION['admin_totp_email'])
                ? $_SESSION['admin_totp_email'] : '';
            \jsonResponse([
                'valid'       => false,
                'totpPending' => true,
                'email'       => $totpEmail,
            ]);
        }

        \jsonResponse(['valid' => false]);
    }

    // =========================================================================
    //  TOTP / 2FA
    // =========================================================================

    public function generateTotpSecret(): string
    {
        return \OTPHP\TOTP::generate()->getSecret();
    }

    public function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        /** @var non-empty-string $nonEmptySecret */
        $nonEmptySecret = $secret;
        /** @var non-empty-string $nonEmptyEmail */
        $nonEmptyEmail = $email;
        /** @var non-empty-string $nonEmptyIssuer */
        $nonEmptyIssuer = $issuer ?? 'Ram;Lop Admin';
        $totp = \OTPHP\TOTP::createFromSecret($nonEmptySecret);
        $totp->setLabel($nonEmptyEmail);
        $totp->setIssuer($nonEmptyIssuer);
        return $totp->getProvisioningUri();
    }

    public function verifyTotpCode(string $secret, string $code): bool
    {
        /** @var non-empty-string $nonEmptySecret */
        $nonEmptySecret = $secret;
        $totp = \OTPHP\TOTP::createFromSecret($nonEmptySecret);
        /** @var non-empty-string $nonEmptyCode */
        $nonEmptyCode = $code;
        return $totp->verify($nonEmptyCode, null, 1);
    }

    /** @return array<int, string> */
    public function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = \strtoupper(\bin2hex(\random_bytes(4)));
        }
        return $codes;
    }

    public function verifyBackupCode(int $userId, string $code): bool
    {
        $hashedCodes = $this->userModel->getBackupCodes($userId);
        foreach ($hashedCodes as $i => $hashed) {
            if (\password_verify($code, $hashed)) {
                unset($hashedCodes[$i]);
                $this->userModel->setBackupCodes($userId, \array_values($hashedCodes));
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    //  Activity Logging
    // =========================================================================

    /** @param array<string, mixed> $details */
    public function logAction(
        string $action,
        string $entityType,
        string $entityId,
        string $description = '',
        array $details = []
    ): void {
        $adminId = $this->getCurrentUserId();
        if ($adminId === null) {
            return;
        }
        $this->userModel->logActivity(
            $adminId,
            $action,
            $entityType,
            $entityId,
            $description,
            $this->getClientIp(),
            $details
        );
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? $ip : '0.0.0.0';
    }
}
