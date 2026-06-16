<?php
/**
 * Vuno-store — Web Installer
 *
 * One-time setup wizard for production deployment.
 * Flow:
 *   1. DB connection form  →  2. Run schema.sql  →  3. Store/admin setup  →  Done
 *
 * Security:
 *   - Creates php/.installer-lock after success (blocks re-run)
 *   - Checks if php/database/config.php already has valid credentials
 *   - All passwords hashed with password_hash()
 *   - SQL executed via prepared statements where possible
 */

// --- Constants ---
define('INSTALLER_VERSION', '1.0.0');
define('LOCK_FILE', __DIR__ . '/.installer-lock');

// --- Lock check: prevent re-run ---
$isInstalled = false;
if (file_exists(LOCK_FILE)) {
    $isInstalled = true;
} else {
    $cfgFile = __DIR__ . '/database/config.php';
    if (file_exists($cfgFile)) {
        @include_once $cfgFile;
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
                new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ]);
                $isInstalled = true;
            } catch (\Exception $e) {
                // Connection failed — allow re-install
            }
        }
    }
}

if ($isInstalled) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ya Instalado — Vuno-Store</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; background: #faf9f8; color: #1A1A1A; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .card { background: #fff; border: 1px solid #e0ddd9; box-shadow: 0 4px 24px rgba(0,0,0,.06); padding: 48px 40px; max-width: 480px; width: 100%; margin: 24px; text-align: center; }
            h1 { font-size: 24px; font-weight: 500; letter-spacing: -.01em; margin-bottom: 12px; }
            p { font-size: 14px; line-height: 1.6; color: #6b6b6b; margin-bottom: 24px; }
            .badge { display: inline-block; background: #e8e6e3; color: #1A1A1A; font-size: 11px; font-weight: 600; letter-spacing: .08em; padding: 6px 14px; text-transform: uppercase; }
            .lock-note { font-size: 12px; color: #999; margin-top: 16px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="badge">✓ Instalado</div>
            <h1>Vuno-Store ya está configurado</h1>
            <p>El instalador ya fue ejecutado. Si necesitas reinstalar, eliminá el archivo <code>php/.installer-lock</code> del servidor y volvé a acceder a esta página.</p>
            <p><a href="/admin/login" style="color:#1A1A1A;text-decoration:underline;font-weight:500;">Ir al panel administrador →</a></p>
            <div class="lock-note">o eliminá install.php después de la instalación.</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Step Router ---
$step = isset($_POST['_step']) ? (int)$_POST['_step'] : 1;

function renderHeader(string $title, string $stepTitle, int $step = 1): void
{
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> — Vuno-Store Installer</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; background: #faf9f8; color: #1A1A1A; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .card { background: #fff; border: 1px solid #e0ddd9; box-shadow: 0 4px 24px rgba(0,0,0,.06); padding: 48px 40px; max-width: 520px; width: 100%; margin: 24px; }
            h1 { font-size: 22px; font-weight: 600; letter-spacing: -.01em; margin-bottom: 4px; }
            .step-indicator { font-size: 11px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: #999; margin-bottom: 16px; }
            label { display: block; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #6b6b6b; margin-bottom: 6px; margin-top: 20px; }
            label:first-of-type { margin-top: 0; }
            input, select { width: 100%; padding: 10px 0; border: none; border-bottom: 1px solid #d4d2ce; background: transparent; font-size: 15px; color: #1A1A1A; outline: none; transition: border-color .15s; }
            input:focus { border-bottom-color: #1A1A1A; }
            .hint { font-size: 12px; color: #999; margin-top: 4px; margin-bottom: 4px; }
            .error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; font-size: 13px; border-radius: 2px; margin-bottom: 16px; }
            .success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; font-size: 13px; border-radius: 2px; margin-bottom: 16px; }
            .btn { width: 100%; padding: 14px 24px; background: #1A1A1A; color: #fff; border: none; font-size: 12px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; cursor: pointer; transition: opacity .15s; margin-top: 28px; }
            .btn:hover { opacity: .88; }
            .btn:disabled { opacity: .4; cursor: not-allowed; }
            .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .grid-2 label { margin-top: 0; }
            code { background: #f0efec; padding: 1px 5px; font-size: 13px; border-radius: 2px; }
            .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #f0efec; font-size: 12px; color: #999; text-align: center; }
            ul { font-size: 13px; color: #6b6b6b; line-height: 1.8; padding-left: 18px; margin: 12px 0; }
            .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #ccc; border-top-color: #1A1A1A; border-radius: 50%; animation: spin .6s linear infinite; vertical-align: middle; margin-right: 6px; }
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="step-indicator">Paso <?= $step ?> de 3 — <?= htmlspecialchars($stepTitle) ?></div>
            <h1><?= htmlspecialchars($title) ?></h1>
    <?php
}

function renderFooter(): void
{
    ?>
            <div class="footer">Vuno-Store Installer v<?= INSTALLER_VERSION ?></div>
        </div>
    </body>
    </html>
    <?php
}

// --- Step 1: Database Connection ---
if ($step === 1) {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_host'])) {
        $dbHost = trim($_POST['db_host']);
        $dbPort = trim($_POST['db_port']) ?: '3306';
        $dbName = trim($_POST['db_name']);
        $dbUser = trim($_POST['db_user']);
        $dbPass = $_POST['db_pass'] ?? '';

        if (!$dbHost || !$dbName || !$dbUser) {
            $error = 'Completá los campos obligatorios (Host, Base de Datos, Usuario).';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbHost, $dbPort);
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);

                // Try to create database if it doesn't exist
                $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $dbName)));
                $pdo->exec(sprintf('USE `%s`', str_replace('`', '``', $dbName)));

                // Verify connection to the actual database
                $checkDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
                new PDO($checkDsn, $dbUser, $dbPass, [PDO::ATTR_TIMEOUT => 3]);

                // Store in session? No — pass via hidden fields
                echo renderHeader('Conexión Exitosa', 'Base de Datos', $step);
                ?>
                <div class="success">✓ Conexión establecida correctamente a <strong><?= htmlspecialchars($dbName) ?></strong></div>
                <p style="font-size:14px;color:#6b6b6b;line-height:1.6;">La base de datos está accesible. Ahora vamos a crear las tablas e insertar los datos de demostración.</p>
                <form method="post">
                    <input type="hidden" name="_step" value="2">
                    <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
                    <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPort) ?>">
                    <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
                    <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
                    <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">
                    <button type="submit" class="btn">Instalar Base de Datos →</button>
                </form>
                <?php
                renderFooter();
                exit;

            } catch (\PDOException $e) {
                $error = 'Error de conexión: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    echo renderHeader('Configurar Base de Datos', 'Base de Datos', $step);
    if ($error) echo '<div class="error">' . $error . '</div>';
    ?>
    <p style="font-size:14px;color:#6b6b6b;line-height:1.6;margin-bottom:8px;">Ingresá los datos de conexión a tu base de datos MySQL. Los conseguís en el panel de <strong>Hostinger</strong> → Bases de Datos → MySQL.</p>
    <form method="post">
        <input type="hidden" name="_step" value="1">
        <div class="grid-2">
            <div>
                <label for="db_host">Host</label>
                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                <div class="hint">Ej: localhost o mysql.hostinger.com</div>
            </div>
            <div>
                <label for="db_port">Puerto</label>
                <input type="text" id="db_port" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
            </div>
        </div>
        <label for="db_name">Nombre de la Base de Datos</label>
        <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'vuno_ecommerce') ?>" required>
        <div class="hint">Se creará automáticamente si no existe.</div>
        <label for="db_user">Usuario</label>
        <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required autocomplete="off">
        <label for="db_pass">Contraseña</label>
        <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" autocomplete="off">
        <button type="submit" class="btn">Probar Conexión →</button>
    </form>
    <?php
    renderFooter();
    exit;
}

// --- Step 2: Execute schema.sql ---
if ($step === 2) {
    $dbHost = $_POST['db_host'] ?? '';
    $dbPort = $_POST['db_port'] ?? '3306';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';

    if (!$dbHost || !$dbName || !$dbUser) {
        echo renderHeader('Error', 'Instalación', $step);
        echo '<div class="error">Faltan datos de conexión. Volvé al paso 1.</div>';
        echo '<form method="post"><input type="hidden" name="_step" value="1"><button class="btn">Volver</button></form>';
        renderFooter();
        exit;
    }

    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        echo renderHeader('Error', 'Instalación', $step);
        echo '<div class="error">No se encontró el archivo <code>php/database/schema.sql</code>. Verificá que el archivo exista.</div>';
        renderFooter();
        exit;
    }

    try {
        $sql = file_get_contents($schemaFile);
        if ($sql === false) throw new \RuntimeException('No se pudo leer schema.sql');

        // Remove USE and CREATE DATABASE blocks (multi-line) — DB already selected
        $sql = preg_replace('/CREATE\s+DATABASE\s+.*?;/is', '', $sql);
        $sql = preg_replace('/USE\s+.*?;/i', '', $sql);

        // Use mysqli::multi_query() — handles semicolons inside string literals
        // (CSS in email templates would break a naive explode(';'))
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
        $mysqli->set_charset('utf8mb4');

        $count = 0;
        $errors = [];

        // Strip comment-only lines (multi_query can choke on bare -- comments)
        $cleanLines = [];
        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $cleanLines[] = $line;
        }
        $cleanSql = implode("\n", $cleanLines);

        if (!$mysqli->multi_query($cleanSql)) {
            throw new \RuntimeException('multi_query failed: ' . $mysqli->error);
        }

        do {
            // Consume result sets (SELECT queries, etc.) to avoid "commands out of sync"
            $result = $mysqli->store_result();
            if ($result) $result->free();

            if ($mysqli->errno) {
                $errors[] = htmlspecialchars('Error #' . $mysqli->errno . ': ' . $mysqli->error);
            } else {
                $count++;
            }
        } while ($mysqli->more_results() && $mysqli->next_result());

        $mysqli->close();

        echo renderHeader('Base de Datos Instalada', 'Instalación', $step);
        if (empty($errors)) {
            echo '<div class="success">✓ Base de datos instalada correctamente. <strong>' . $count . '</strong> sentencias ejecutadas.</div>';
        } else {
            echo '<div class="success">✓ Instalación completada con ' . count($errors) . ' advertencias (posiblemente ya existían). ' . $count . ' sentencias ejecutadas.</div>';
            echo '<ul>';
            foreach (array_slice($errors, 0, 5) as $e) {
                echo '<li>' . $e . '</li>';
            }
            if (count($errors) > 5) echo '<li>... y ' . (count($errors) - 5) . ' más</li>';
            echo '</ul>';
        }
        ?>
        <p style="font-size:14px;color:#6b6b6b;line-height:1.6;">Ahora configurá los datos básicos de la tienda y el administrador.</p>
        <form method="post">
            <input type="hidden" name="_step" value="3">
            <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
            <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPort) ?>">
            <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
            <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
            <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">
            <button type="submit" class="btn">Configurar Tienda →</button>
        </form>
        <?php
        renderFooter();
        exit;

    } catch (\Exception $e) {
        echo renderHeader('Error', 'Instalación', $step);
        echo '<div class="error">' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<form method="post"><input type="hidden" name="_step" value="1"><button class="btn">Volver</button></form>';
        renderFooter();
        exit;
    }
}

// --- Step 3: Store & Admin Setup ---
if ($step === 3) {
    $dbHost = $_POST['db_host'] ?? '';
    $dbPort = $_POST['db_port'] ?? '3306';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['store_name'])) {
        $storeName = trim($_POST['store_name']);
        $storeEmail = trim($_POST['store_email']);
        $adminEmail = trim($_POST['admin_email']);
        $adminName = trim($_POST['admin_name']);
        $adminPass = $_POST['admin_pass'] ?? '';
        $siteUrl = trim($_POST['site_url']);

        if (!$storeName || !$adminEmail || !$adminName || !$adminPass) {
            $error = 'Completá todos los campos obligatorios.';
        } elseif (strlen($adminPass) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || !filter_var($storeEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresá emails válidos.';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                // Update admin user
                $hash = password_hash($adminPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('UPDATE admin_users SET email = ?, password_hash = ?, name = ? WHERE role_id = 1 LIMIT 1');
                $stmt->execute([$adminEmail, $hash, $adminName]);
                if ($stmt->rowCount() === 0) {
                    // No superadmin exists — insert one
                    $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash, name, role_id, is_active) VALUES (?, ?, ?, 1, 1)');
                    $stmt->execute([$adminEmail, $hash, $adminName]);
                }

                // Update store settings
                $settings = [
                    ['store', 'name', $storeName],
                    ['store', 'email', $storeEmail],
                    ['smtp', 'adminEmail', $adminEmail],
                ];
                $stmt = $pdo->prepare('UPDATE settings SET `value` = ? WHERE section = ? AND `key` = ?');
                foreach ($settings as $s) {
                    $stmt->execute([$s[2], $s[0], $s[1]]);
                }

                // Write database config file
                $configDir = __DIR__ . '/database';
                if (!is_dir($configDir)) {
                    mkdir($configDir, 0755, true);
                }
                $configContent = <<<PHP
<?php
/**
 * Database Configuration — generated by install.php
 */
define('DB_HOST', '{$dbHost}');
define('DB_PORT', '{$dbPort}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');

PHP;
                file_put_contents($configDir . '/config.php', $configContent);

                // Create lock file
                file_put_contents(LOCK_FILE, date('c') . ' — Installed via install.php');

                // Success!
                echo renderHeader('¡Instalación Completa!', 'Finalizado', $step);
                ?>
                <div class="success">✓ Vuno-Store está listo.</div>
                <ul>
                    <li><strong>Tienda:</strong> <?= htmlspecialchars($storeName) ?></li>
                    <li><strong>Admin email:</strong> <?= htmlspecialchars($adminEmail) ?></li>
                </ul>
                <p style="font-size:14px;color:#6b6b6b;line-height:1.6;">
                    <strong>Importante:</strong> eliminá el archivo <code>install.php</code> del servidor por seguridad.
                </p>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
                    <a href="/admin/login" class="btn" style="display:block;text-align:center;text-decoration:none;">Ir al Panel Admin →</a>
                    <a href="/" class="btn" style="display:block;text-align:center;text-decoration:none;background:#f0efec;color:#1A1A1A;">Ver Tienda →</a>
                </div>
                <?php
                renderFooter();
                exit;

            } catch (\Exception $e) {
                $error = 'Error al guardar la configuración: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    echo renderHeader('Configurar Tienda', 'Tienda y Admin', $step);
    if ($error) echo '<div class="error">' . $error . '</div>';
    ?>
    <p style="font-size:14px;color:#6b6b6b;line-height:1.6;margin-bottom:8px;">Estos datos se guardarán en la base de datos y en <code>php/database/config.php</code>.</p>
    <form method="post">
        <input type="hidden" name="_step" value="3">
        <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
        <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPort) ?>">
        <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
        <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
        <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">

        <h2 style="font-size:15px;font-weight:600;margin:24px 0 4px;letter-spacing:-.01em;">Tienda</h2>
        <label for="store_name">Nombre de la Tienda *</label>
        <input type="text" id="store_name" name="store_name" value="<?= htmlspecialchars($_POST['store_name'] ?? 'Vuno Store') ?>" required>
        <label for="store_email">Email de Contacto *</label>
        <input type="email" id="store_email" name="store_email" value="<?= htmlspecialchars($_POST['store_email'] ?? '') ?>" required>
        <div class="hint">Email público que verán los clientes.</div>

        <h2 style="font-size:15px;font-weight:600;margin:24px 0 4px;letter-spacing:-.01em;">Administrador</h2>
        <label for="admin_email">Email del Admin *</label>
        <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
        <label for="admin_name">Nombre del Admin *</label>
        <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrador') ?>" required>
        <label for="admin_pass">Contraseña *</label>
        <input type="password" id="admin_pass" name="admin_pass" required minlength="6" autocomplete="new-password">
        <div class="hint">Mínimo 6 caracteres.</div>

        <h2 style="font-size:15px;font-weight:600;margin:24px 0 4px;letter-spacing:-.01em;">URL del Sitio</h2>
        <label for="site_url">URL del sitio (opcional)</label>
        <input type="url" id="site_url" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? '') ?>" placeholder="https://tudominio.com">
        <div class="hint">Se usará en emails y sitemap. Podés cambiarlo después en Configuración → SEO.</div>

        <button type="submit" class="btn">Finalizar Instalación</button>
    </form>
    <?php
    renderFooter();
    exit;
}

// Fallback — redirect to step 1
header('Location: ?');
exit;
