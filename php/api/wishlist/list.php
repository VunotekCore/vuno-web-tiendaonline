<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/storage.php';

setCorsHeaders();

$email = $_GET['email'] ?? '';
if (empty($email)) jsonError('Customer email is required');

$items = getWishlistByEmail($email);
jsonResponse(['items' => $items, 'total' => count($items)]);
