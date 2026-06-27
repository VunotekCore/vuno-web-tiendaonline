<?php
declare(strict_types=1);

/**
 * Ram;Lop - PHP Backend Configuration
 * Loads environment variables from .env or hosting environment.
 */

// Load .env file if present (check local dir, then parent)
$envFile = is_file(__DIR__ . '/.env') ? __DIR__ . '/.env' : __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    if (isset($_ENV[$key])) return $_ENV[$key];
    $value = getenv($key);
    if ($value === false) return $default;
    return $value;
}

// --- Secrets Encryption ---
define('SENSITIVE_KEYS', ['pass', 'privateKey', 'private_key', 'secretKey', 'secret_key', 'webhookSecret']);

function getEncryptionKey(): string {
    $file = __DIR__ . '/encryption.key';
    if (!file_exists($file)) {
        file_put_contents($file, bin2hex(random_bytes(32)));
    }
    return trim(file_get_contents($file));
}

function encryptSecret(string $plaintext): string {
    $key = hex2bin(getEncryptionKey());
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = @openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new \RuntimeException('Encryption failed');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptSecret(string $encoded): string {
    $key = hex2bin(getEncryptionKey());
    $data = base64_decode($encoded);
    if ($data === false || strlen($data) < 29) return ''; // too short to be valid
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $cipher = substr($data, 28);
    $result = @openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $result !== false ? $result : '';
}

// --- Product Constraints ---
define('MAX_PRODUCT_IMAGES', 20);

// --- Database Constants (from local config or env fallback) ---
$localDb = __DIR__ . '/database/config.php';
if (file_exists($localDb)) {
    require_once $localDb;
}
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_PORT')) define('DB_PORT', env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'vuno_ramlop_ecommerce'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'dail'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));

// --- Database Connection ---
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// --- Session Config (supports cross-origin for local dev) ---
function configureSession(): void
{
    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $isLocal || $isHttps,
        'httponly' => true,
        'samesite' => $isLocal ? 'None' : 'Strict',
    ]);
}

// --- Security Headers ---
function setSecurityHeaders(): void
{
    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
    if ($isLocal) return;

    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://js.stripe.com; style-src \'self\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com; frame-src https://js.stripe.com; img-src \'self\' data: https:; connect-src \'self\' https://api.stripe.com');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self)');
}

// --- CORS Headers for API ---
function setCorsHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    if ($origin === '*') {
        header('Access-Control-Allow-Origin: *');
    } else {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');

    setSecurityHeaders();

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// --- JSON Response Helper ---
/** @return never */
function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return never */
function jsonError(string $message, int $status = 400): never
{
    jsonResponse(['error' => $message], $status);
}

// =============================================================================
//  Auth wrappers — delegates to App\AuthService
//  These thin global functions allow 45+ API entry points to work without
//  modifying them. New code should use AuthService directly.
// =============================================================================

/** @return \App\Services\AuthService */
function auth(): \App\Services\AuthService
{
    static $instance = null;
    if ($instance === null) {
        $instance = new \App\Services\AuthService();
    }
    return $instance;
}

function startAdminSession(): void
{
    auth()->startSession();
}

function isAdminLoggedIn(): bool
{
    return auth()->isLoggedIn();
}

function requireRole(string ...$roles): void
{
    auth()->requireRole(...$roles);
}

function logAdminAction(string $action, string $entityType, string $entityId, string $description = '', array $details = []): void
{
    auth()->logAction($action, $entityType, $entityId, $description, $details);
}
