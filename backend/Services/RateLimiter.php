<?php
declare(strict_types=1);

namespace App\Services;

final class RateLimiter
{
    /**
     * Check if an action is rate limited.
     * Uses DB table `rate_limiting` (auto-creates rows on first hit).
     *
     * @param \PDO $db
     * @param string $namespace  e.g. 'contact', 'receipt_upload'
     * @param string $identifier e.g. IP address
     * @param int $maxAttempts   Max allowed attempts within the window
     * @param int $windowSeconds Time window in seconds
     * @return bool  True if rate limited
     */
    public static function isLimited(\PDO $db, string $namespace, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM rate_limiting WHERE namespace = ? AND identifier = ? AND created_at >= ?'
        );
        $stmt->execute([$namespace, $identifier, $cutoff]);
        $count = (int) $stmt->fetchColumn();
        return $count >= $maxAttempts;
    }

    /**
     * Record an attempt for rate limiting purposes.
     */
    public static function record(\PDO $db, string $namespace, string $identifier): void
    {
        $stmt = $db->prepare(
            'INSERT INTO rate_limiting (namespace, identifier) VALUES (?, ?)'
        );
        $stmt->execute([$namespace, $identifier]);
    }

    /**
     * Check and record in one call. Returns true if rate limited.
     */
    public static function checkAndRecord(\PDO $db, string $namespace, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        if (self::isLimited($db, $namespace, $identifier, $maxAttempts, $windowSeconds)) {
            return true;
        }
        self::record($db, $namespace, $identifier);
        return false;
    }
}
