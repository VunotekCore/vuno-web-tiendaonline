<?php
declare(strict_types=1);

/**
 * POST /api/stripe/create-payment-intent.php
 * Creates a Stripe PaymentIntent from cart items
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/helpers.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$customerEmail = $input['customerEmail'] ?? '';
$total = isset($input['total']) ? (float)$input['total'] : null;

if (empty($items)) {
    jsonError('No items provided');
}

try {
    $result = createPaymentIntent($items, $customerEmail, $total);
    jsonResponse([
        'clientSecret' => $result['clientSecret'],
        'id' => $result['id'],
    ]);
} catch (\Exception $e) {
    jsonError($e->getMessage(), 500);
}
