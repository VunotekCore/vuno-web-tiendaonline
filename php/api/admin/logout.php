<?php
declare(strict_types=1);

/**
 * GET /api/admin/logout.php - Logout admin (JSON response)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();

logoutAdmin();
jsonResponse(['success' => true]);
