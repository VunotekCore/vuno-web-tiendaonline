<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['product_id']) || empty($data['rating'])) {
    jsonError('product_id and rating are required');
}

$rating = (int)$data['rating'];
if ($rating < 1 || $rating > 5) {
    jsonError('Rating must be between 1 and 5');
}

$id = saveReview([
    'product_id'     => $data['product_id'],
    'reviewer_name'  => $data['reviewer_name'] ?? '',
    'reviewer_email' => $data['reviewer_email'] ?? '',
    'rating'         => $rating,
    'title'          => $data['title'] ?? '',
    'comment'        => $data['comment'] ?? '',
]);

jsonResponse(['success' => true, 'id' => $id]);
