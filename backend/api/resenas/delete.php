<?php
declare(strict_types=1);

use App\Controllers\ReviewController;
use App\Models\ReviewModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}
\requireRole('superadmin', 'editor');

$controller = new ReviewController(new ReviewModel(\App\Config\Database::getConnection()));
$controller->delete();
