<?php
declare(strict_types=1);

// Load Composer autoloader (App\ PSR-4 + Stripe + PHPMailer + OTPHP)
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    // Fallback to root vendor/ when backend/vendor/ not available (e.g. first deploy)
    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
}
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . '/autoload.php';
// Database.php is auto-loaded via PSR-4 (App\Config\Database → Config/Database.php)
require_once __DIR__ . '/config.php'; // Legacy: env(), configureSession(), setCorsHeaders(), jsonResponse(), auth helpers
