<?php
declare(strict_types=1);

namespace App\Models;

final class UserModel
{
    public function __construct(private \PDO $db) {}

    // =========================================================================
    //  Admin Users
    // =========================================================================

    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT au.id, au.email, au.name, au.is_active, au.last_login_at, au.created_at,
                    ar.code AS role_code, ar.name AS role_name
             FROM admin_users au
             JOIN admin_roles ar ON ar.id = au.role_id
             ORDER BY au.created_at ASC'
        );
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT au.id, au.email, au.name, au.is_active, au.totp_enabled, au.last_login_at, au.created_at,
                    ar.code AS role_code, ar.name AS role_name
             FROM admin_users au
             JOIN admin_roles ar ON ar.id = au.role_id
             WHERE au.id = ?'
        );
        $stmt->execute([$id]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT au.id, au.email, au.name, au.is_active, au.totp_enabled, au.last_login_at, au.created_at,
                    ar.code AS role_code, ar.name AS role_name
             FROM admin_users au
             JOIN admin_roles ar ON ar.id = au.role_id
             WHERE au.email = ?'
        );
        $stmt->execute([$email]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function create(string $email, string $name, string $passwordHash, int $roleId): int
    {
        $this->db->prepare(
            'INSERT INTO admin_users (email, name, password_hash, role_id) VALUES (?, ?, ?, ?)'
        )->execute([$email, $name, $passwordHash, $roleId]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $sets = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $this->db->prepare(
            'UPDATE admin_users SET ' . implode(', ', $sets) . ' WHERE id = ?'
        )->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM admin_users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM admin_users WHERE email = ?');
            $stmt->execute([$email]);
        }
        return $stmt->fetchColumn() !== false;
    }

    public function validatePassword(string $email, string $password): bool
    {
        $stmt = $this->db->prepare(
            'SELECT password_hash FROM admin_users WHERE email = ? AND is_active = 1'
        );
        $stmt->execute([$email]);
        $hash = $stmt->fetchColumn();
        return $hash !== false && \password_verify($password, (string) $hash);
    }

    // =========================================================================
    //  Roles
    // =========================================================================

    /** @return array<int, array{id: string, code: string, name: string}> */
    public function getRoles(): array
    {
        $stmt = $this->db->query('SELECT id, code, name FROM admin_roles ORDER BY id');
        /** @var array<int, array{id: string, code: string, name: string}>|false $rows */
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    /** @return array{id: string, code: string, name: string}|null */
    public function getRoleByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT id, code, name FROM admin_roles WHERE code = ?');
        $stmt->execute([$code]);
        /** @var array{id: string, code: string, name: string}|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    // =========================================================================
    //  TOTP / 2FA
    // =========================================================================

    public function getTotpSecret(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT totp_secret FROM admin_users WHERE id = ?');
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== false && is_string($val) ? $val : null;
    }

    public function isTotpEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT totp_enabled FROM admin_users WHERE id = ?');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function setTotpSecret(int $userId, string $secret): void
    {
        $this->db->prepare('UPDATE admin_users SET totp_secret = ? WHERE id = ?')
            ->execute([$secret, $userId]);
    }

    /** @param array<int, string> $hashedCodes */
    public function enableTotp(int $userId, array $hashedCodes): void
    {
        $this->db->prepare(
            'UPDATE admin_users SET totp_enabled = 1, backup_codes = ? WHERE id = ?'
        )->execute([\json_encode($hashedCodes), $userId]);
    }

    public function disableTotp(int $userId): void
    {
        $this->db->prepare(
            'UPDATE admin_users SET totp_secret = NULL, totp_enabled = 0, backup_codes = NULL WHERE id = ?'
        )->execute([$userId]);
    }

    /** @return array<int, string> */
    public function getBackupCodes(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT backup_codes FROM admin_users WHERE id = ?');
        $stmt->execute([$userId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || !is_string($raw)) {
            return [];
        }
        /** @var array<int, string> $decoded */
        $decoded = \json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int, string> $hashedCodes */
    public function setBackupCodes(int $userId, array $hashedCodes): void
    {
        $this->db->prepare('UPDATE admin_users SET backup_codes = ? WHERE id = ?')
            ->execute([\json_encode($hashedCodes), $userId]);
    }

    // =========================================================================
    //  Login Attempts (Rate Limiting)
    // =========================================================================

    public function ensureLoginAttemptsTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS admin_login_attempts (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_address  VARCHAR(45) NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                success     BOOLEAN DEFAULT FALSE,
                INDEX idx_ip_time (ip_address, attempted_at)
            ) ENGINE=InnoDB'
        );
    }

    public function recordLoginAttempt(string $ip, bool $success): void
    {
        $this->db->prepare(
            'INSERT INTO admin_login_attempts (ip_address, success) VALUES (?, ?)'
        )->execute([$ip, $success ? 1 : 0]);
    }

    public function getFailedAttemptCount(string $ip, int $windowSeconds): int
    {
        $since = \date('Y-m-d H:i:s', \time() - $windowSeconds);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM admin_login_attempts
             WHERE ip_address = ? AND success = 0 AND attempted_at >= ?'
        );
        $stmt->execute([$ip, $since]);
        return (int) $stmt->fetchColumn();
    }

    public function clearLoginAttempts(string $ip): void
    {
        $this->db->prepare('DELETE FROM admin_login_attempts WHERE ip_address = ?')
            ->execute([$ip]);
    }

    // =========================================================================
    //  Activity Log
    // =========================================================================

    /** @param array<string, mixed> $details */
    public function logActivity(
        int $adminId,
        string $action,
        string $entityType,
        string $entityId,
        string $description,
        string $ip,
        array $details = []
    ): int {
        $this->db->prepare(
            'INSERT INTO admin_activity_log (admin_id, action, entity_type, entity_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$adminId, $action, $entityType, $entityId, $description, $ip]);

        $logId = (int) $this->db->lastInsertId();

        if ($details !== []) {
            $stmt = $this->db->prepare(
                'INSERT INTO admin_activity_details (log_id, meta_key, meta_value) VALUES (?, ?, ?)'
            );
            foreach ($details as $key => $value) {
                $stmt->execute([
                    $logId,
                    $key,
                    is_string($value) ? $value : \json_encode($value),
                ]);
            }
        }

        return $logId;
    }
}
