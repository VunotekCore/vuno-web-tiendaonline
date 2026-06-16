<?php
declare(strict_types=1);

/**
 * Stripe Service - PHP
 * Uses Stripe SDK (composer require stripe/stripe-php)
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/storage.php';

function getStripeClient(): \Stripe\StripeClient
{
    $settings = getSettings();
    $key = $settings['stripe']['secretKey'] ?? '';
    if (!$key) {
        throw new \RuntimeException('Stripe secret key not configured in admin settings');
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
    $settings = getSettings();
    $secret = $settings['stripe']['webhookSecret'] ?? '';
    if (!$secret) {
        throw new \RuntimeException('Stripe webhook secret not configured in admin settings');
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
