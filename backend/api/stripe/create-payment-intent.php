<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Services\StripeService;

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$customerEmail = $input['customerEmail'] ?? '';
$total = isset($input['total']) ? (float) $input['total'] : null;

if (empty($items)) {
    jsonError('No items provided');
}

try {
    $service = new StripeService();
    $result = $service->createPaymentIntent($items, $customerEmail, $total);
    jsonResponse([
        'clientSecret' => $result['clientSecret'],
        'id' => $result['id'],
    ]);
} catch (\Throwable $e) {
    jsonError($e->getMessage(), 500);
}
