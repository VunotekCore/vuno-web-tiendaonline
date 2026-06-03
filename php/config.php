<?php
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
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false && $value !== null ? $value : $default;
}

// --- Database Constants ---
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'vuno_ramlop_ecommerce'));
define('DB_USER', env('DB_USER', 'dail'));
define('DB_PASS', env('DB_PASS', ''));

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
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    jsonResponse(['error' => $message], $status);
}
