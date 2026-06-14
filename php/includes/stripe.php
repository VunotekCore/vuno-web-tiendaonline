<?php
declare(strict_types=1);

/**
 * Stripe Service - PHP
 * Uses Stripe SDK (composer require stripe/stripe-php)
 */

require_once __DIR__ . '/../../vendor/autoload.php';

function getStripeClient(): \Stripe\StripeClient
{
    $key = env('STRIPE_SECRET_KEY');
    if (!$key) {
        throw new \RuntimeException('STRIPE_SECRET_KEY not configured');
    }
    return new \Stripe\StripeClient($key);
}

function createPaymentIntent(array $items, string $customerEmail = '', ?float $total = null): array
{
    $stripe = getStripeClient();

    $amount = (int)round(($total !== null ? $total : calculateSubtotal($items)) * 100);
    if ($amount <= 0) {
        throw new \InvalidArgumentException('Invalid amount');
    }

    $description = implode(', ', array_map(
        fn($i) => ($i['product']['name'] ?? 'Item') . ' x' . ($i['quantity'] ?? 1),
        $items
    ));

    $intent = $stripe->paymentIntents->create([
        'amount' => $amount,
        'currency' => 'usd',
        'description' => substr($description, 0, 200),
        'receipt_email' => $customerEmail ?: null,
        'automatic_payment_methods' => ['enabled' => true],
    ]);

    return [
        'clientSecret' => $intent->client_secret,
        'id' => $intent->id,
    ];
}

function handleWebhook(): array
{
    $secret = env('STRIPE_WEBHOOK_SECRET');
    if (!$secret) {
        throw new \RuntimeException('STRIPE_WEBHOOK_SECRET not configured');
    }

    $payload = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);

    $eventType = $event->type;
    $intent = $event->data->object;

    return match ($eventType) {
        'payment_intent.succeeded' => [
            'type' => 'payment_intent.succeeded',
            'id' => $intent->id,
            'amount' => $intent->amount_received / 100,
        ],
        'payment_intent.payment_failed' => [
            'type' => 'payment_intent.payment_failed',
            'id' => $intent->id,
            'error' => $intent->last_payment_error?->message ?? 'Unknown error',
        ],
        default => [
            'type' => $eventType,
            'id' => $intent->id ?? null,
        ],
    };
}
