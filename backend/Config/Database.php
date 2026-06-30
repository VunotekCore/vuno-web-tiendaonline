<?php
declare(strict_types=1);

namespace App\Config;

final class Database
{
    private static ?\PDO $instance = null;

    public static function getConnection(): \PDO
    {
        if (self::$instance === null) {
            $host = self::getEnv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
            $port = self::getEnv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : '3306');
            $name = self::getEnv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'vuno_ramlop_ecommerce');
            $user = self::getEnv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'dail');
            $pass = self::getEnv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

            self::$instance = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function getEnv(string $key): string
    {
        $value = $_ENV[$key] ?? null;
        if ($value !== null && is_string($value)) {
            return $value;
        }
        $envValue = getenv($key);
        if (is_string($envValue)) {
            return $envValue;
        }
        return '';
    }
}
