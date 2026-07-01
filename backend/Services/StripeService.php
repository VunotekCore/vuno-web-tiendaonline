<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SettingModel;
use App\Utils\Str;

final class StripeService
{
    private ?\Stripe\StripeClient $client = null;

    public function __construct(
        private ?SettingModel $settings = null,
        private ?string $secretKey = null,
        private ?string $webhookSecret = null,
    ) {
        if ($this->settings === null && $this->secretKey === null) {
            $this->settings = new SettingModel(\App\Config\Database::getConnection());
        }
    }

    public function createPaymentIntent(array $items, string $customerEmail = ''): array
    {
        $client = $this->getClient();

        $amount = (int) round(Str::calculateSubtotal($items) * 100);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Invalid amount');
        }

        $description = implode(', ', array_map(
            fn(array $i): string => ($i['product']['name'] ?? 'Item') . ' x' . ($i['quantity'] ?? 1),
            $items
        ));

        $intent = $client->paymentIntents->create([
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

    /**
     * @return array{type: string, id: mixed, amount?: float, error?: string}
     */
    public function verifyWebhook(string $payload, string $sigHeader): array
    {
        $secret = $this->webhookSecret;
        if ($secret === null) {
            $allSettings = $this->getSettingsFromModel();
            $secret = $allSettings['stripe']['webhookSecret'] ?? '';
        }
        if ($secret === '') {
            throw new \RuntimeException('Stripe webhook secret not configured in admin settings');
        }

        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        $eventType = $event->type;
        $intent = $event->data->object;

        return match ($eventType) {
            'payment_intent.succeeded' => [
                'type' => 'payment_intent.succeeded',
                'id'   => $intent->id,
                'amount' => $intent->amount_received / 100,
            ],
            'payment_intent.payment_failed' => [
                'type'  => 'payment_intent.payment_failed',
                'id'    => $intent->id,
                'error' => $intent->last_payment_error?->message ?? 'Unknown error',
            ],
            default => [
                'type' => $eventType,
                'id'   => $intent->id ?? null,
            ],
        };
    }

    private function getClient(): \Stripe\StripeClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $key = $this->secretKey;
        if ($key === null) {
            $allSettings = $this->getSettingsFromModel();
            $key = $allSettings['stripe']['secretKey'] ?? '';
        }
        if ($key === '') {
            throw new \RuntimeException('Stripe secret key not configured in admin settings');
        }

        $this->client = new \Stripe\StripeClient($key);
        return $this->client;
    }

    /** @return array<string, mixed> */
    private function getSettingsFromModel(): array
    {
        if ($this->settings === null) {
            throw new \RuntimeException('SettingModel not available');
        }
        return $this->settings->getAll();
    }
}
