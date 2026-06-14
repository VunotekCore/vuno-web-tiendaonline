<?php
declare(strict_types=1);

/**
 * GET /api/admin/verify.php - Verify admin session
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

setCorsHeaders();

jsonResponse(adminVerify());
