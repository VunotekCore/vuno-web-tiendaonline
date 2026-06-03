<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$productId = $_GET['product_id'] ?? '';
if (empty($productId)) jsonError('product_id is required');

$reviews = getProductReviews($productId, true);
$stats = getReviewStats($productId);

jsonResponse([
    'reviews' => $reviews,
    'stats'   => $stats,
]);
