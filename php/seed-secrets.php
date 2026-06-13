<?php
/**
 * Seed encrypted secrets into the settings table.
 * Run once after deploying encryption infrastructure.
 *
 * Usage: php php/seed-secrets.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/storage.php';

$secrets = [
    ['imagekit', 'privateKey', 'private_g8TNfEqDDfr2MMCw38GNGxct4Vs='],
    ['smtp', 'pass', 'D3683616df$'],
];

$db = getDb();
$inserted = 0;

foreach ($secrets as [$section, $key, $plaintext]) {
    $encrypted = encryptSecret($plaintext);

    $stmt = $db->prepare(
        'INSERT INTO settings (section, `key`, `value`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute([$section, $key, $encrypted]);
    $inserted++;
}

echo "✅ $inserted secret(s) encrypted and inserted into DB.\n";

// Verify by reading back
$settings = getSettings();
$verified = 0;
foreach ($secrets as [$section, $key, $plaintext]) {
    $actual = $settings[$section][$key] ?? null;
    if ($actual === $plaintext) {
        echo "  ✓ $section.$key: decrypts correctly\n";
        $verified++;
    } else {
        echo "  ✗ $section.$key: MISMATCH (got: " . var_export($actual, true) . ")\n";
    }
}

echo "\n$verified/$inserted secrets verified.\n";
