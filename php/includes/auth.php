<?php
declare(strict_types=1);

/**
 * Admin Authentication - PHP Sessions (API mode)
 *
 * Database tables required:
 * - admin_roles        (seed: superadmin, editor, viewer)
 * - admin_users        (seeded via schema.sql / usuarios admin UI)
 * - admin_activity_log (written by logAdminAction)
 * - admin_activity_details (written by logAdminAction)
 */

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        configureSession();
        session_start();
    }
}

function validateCredentials(string $email, string $password): bool
{
    try {
        $db = getDb();
        $stmt = $db->prepare(
            'SELECT password_hash FROM admin_users WHERE email = ? AND is_active = 1'
        );
        $stmt->execute([$email]);
        $hash = $stmt->fetchColumn();
        return $hash !== false && password_verify($password, $hash);
    } catch (\PDOException $e) {
        return false;
    }
}

function loginAdmin(string $email): array
{
    startAdminSession();
    $userId = getAdminUserId($email);
    $totpEnabled = isTotpEnabledForUser($userId);

    if ($totpEnabled) {
        $_SESSION['admin_totp_pending'] = true;
        $_SESSION['admin_totp_email'] = $email;
        $_SESSION['admin_totp_user_id'] = $userId;
        session_regenerate_id(true);
        return ['success' => true, 'totpRequired' => true];
    }

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['admin_user_id'] = $userId;
    session_regenerate_id(true);
    return ['success' => true];
}

function getAdminUserId(string $email): int
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM admin_users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    jsonError('Usuario no encontrado en la base de datos', 401);
}

function isAdminLoggedIn(): bool
{
    startAdminSession();
    return !empty($_SESSION['admin_logged_in']);
}

function logoutAdmin(): void
{
    startAdminSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function getAdminRole(): string
{
    startAdminSession();
    $userId = $_SESSION['admin_user_id'] ?? null;
    if (!$userId) return '';

    $db = getDb();
    $stmt = $db->prepare(
        'SELECT ar.code FROM admin_users au
         JOIN admin_roles ar ON ar.id = au.role_id
         WHERE au.id = ?'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: '';
}

function requireRole(string ...$roles): void
{
    $role = getAdminRole();
    if (!in_array($role, $roles, true)) {
        jsonError('Forbidden: insufficient permissions', 403);
    }
}

function adminVerify(): array
{
    startAdminSession();
    if (isAdminLoggedIn()) {
        $role = getAdminRole();
        $_SESSION['admin_role'] = $role;
        return [
            'valid' => true,
            'email' => $_SESSION['admin_email'] ?? '',
            'role'  => $role,
        ];
    }
    if (!empty($_SESSION['admin_totp_pending'])) {
        return [
            'valid'       => false,
            'totpPending' => true,
            'email'       => $_SESSION['admin_totp_email'] ?? '',
        ];
    }
    return ['valid' => false];
}

// =============================================================================
//  Activity Logging
// =============================================================================

function logAdminAction(string $action, string $entityType, string $entityId, string $description = '', array $details = []): void
{
    $db = getDb();
    $adminId = $_SESSION['admin_user_id'] ?? null;
    if (!$adminId) return;

    $db->prepare(
        'INSERT INTO admin_activity_log (admin_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $adminId, $action, $entityType, $entityId, $description,
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $logId = (int)$db->lastInsertId();

    if ($details) {
        $stmt = $db->prepare(
            'INSERT INTO admin_activity_details (log_id, meta_key, meta_value) VALUES (?, ?, ?)'
        );
        foreach ($details as $key => $value) {
            $stmt->execute([$logId, $key, is_string($value) ? $value : json_encode($value)]);
        }
    }
}

// =============================================================================
//  2FA / TOTP
// =============================================================================

function generateTotpSecret(): string
{
    return \OTPHP\TOTP::generate()->getSecret();
}

function getTotpProvisioningUri(string $secret, string $email): string
{
    $totp = \OTPHP\TOTP::createFromSecret($secret);
    $totp->setLabel($email);
    $totp->setIssuer('Ram;Lop Admin');
    return $totp->getProvisioningUri();
}

function verifyTotpCode(string $secret, string $code): bool
{
    $totp = \OTPHP\TOTP::createFromSecret($secret);
    return $totp->verify($code, null, 1);
}

function generateBackupCodes(): array
{
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

function isTotpEnabledForUser(int $userId): bool
{
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT totp_enabled FROM admin_users WHERE id = ?');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        return false; // Column not migrated yet
    }
}

// =============================================================================
//  Rate Limiting - Login brute-force protection
// =============================================================================

const MAX_LOGIN_ATTEMPTS = 5;
const RATE_LIMIT_WINDOW = 900; // 15 minutes in seconds

function ensureLoginAttemptsTable(): void
{
    $db = getDb();
    $db->exec(
        'CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address  VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success     BOOLEAN DEFAULT FALSE,
            INDEX idx_ip_time (ip_address, attempted_at)
        ) ENGINE=InnoDB'
    );
}

function recordLoginAttempt(bool $success): void
{
    try {
        ensureLoginAttemptsTable();
        $db = getDb();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $db->prepare('INSERT INTO admin_login_attempts (ip_address, success) VALUES (?, ?)');
        $stmt->execute([$ip, $success ? 1 : 0]);
    } catch (\Throwable $e) {
        error_log('Record login attempt failed: ' . $e->getMessage());
    }
}

function checkLoginRateLimit(): void
{
    try {
        ensureLoginAttemptsTable();
        $db = getDb();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $since = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW);

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM admin_login_attempts
             WHERE ip_address = ? AND success = 0 AND attempted_at >= ?'
        );
        $stmt->execute([$ip, $since]);
        $failedAttempts = (int)$stmt->fetchColumn();

        if ($failedAttempts >= MAX_LOGIN_ATTEMPTS) {
            $retryAfter = RATE_LIMIT_WINDOW;
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            jsonError(
                'Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.',
                429
            );
        }
    } catch (\Throwable $e) {
        error_log('Rate limit check failed: ' . $e->getMessage());
    }
}

function clearLoginAttempts(): void
{
    ensureLoginAttemptsTable();
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $db->prepare('DELETE FROM admin_login_attempts WHERE ip_address = ?');
    $stmt->execute([$ip]);
}
