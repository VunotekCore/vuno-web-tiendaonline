<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Config\Database;
use App\Controllers\StripeController;
use App\Models\OrderModel;
use App\Services\StripeService;

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$db = Database::getConnection();
$controller = new StripeController(
    new StripeService(),
    new OrderModel($db),
);
$controller->createPaymentIntent();
