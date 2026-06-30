<?php
declare(strict_types=1);

use App\Controllers\BlogController;
use App\Models\BlogCategoryModel;
use App\Models\BlogPostModel;

require_once __DIR__ . '/../../bootstrap.php';
setCorsHeaders();
\startAdminSession();
if (!\isAdminLoggedIn()) {
    \jsonError('Unauthorized', 401);
}

$controller = new BlogController(
    new BlogPostModel(\App\Config\Database::getConnection()),
    new BlogCategoryModel(\App\Config\Database::getConnection()),
);
$controller->delete();
