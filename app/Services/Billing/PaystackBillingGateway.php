<?php

namespace App\Services\Billing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackBillingGateway implements BillingPaymentGatewayInterface
{
    public function code(): string
    {
        return 'paystack';
    }

    public function quoteCheckout(float $baseAmount, array $configuration): array
    {
        return CheckoutFeeQuote::for($baseAmount, $configuration);
    }

    public function initializeCheckout(array $payload, array $configuration): array
    {
        $response = Http::baseUrl($configuration['base_url'])
            ->withToken($configuration['secret_key'])
            ->acceptJson()
            ->post($configuration['initialize_endpoint'], array_filter([
                'email' => $payload['email'],
                'amount' => (int) round(((float) $payload['amount']) * (int) ($configuration['request_amount_multiplier'] ?? 100)),
                'currency' => $configuration['currency'] ?? 'NGN',
                'reference' => $payload['reference'],
                'callback_url' => $payload['callback_url'],
                'channels' => $payload['channels'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
                'split' => Arr::get($payload, 'split_config.split'),
            ], static fn ($value) => $value !== null));

        $data = $response->json();

        if (!$response->successful() || !Arr::get($data, $configuration['response_paths']['success'], false)) {
            throw new RuntimeException(Arr::get($data, $configuration['response_paths']['message'], 'Unable to initialize online payment.'));
        }

        return [
            'provider' => $this->code(),
            'reference' => Arr::get($data, $configuration['response_paths']['reference'], $payload['reference']),
            'authorization_url' => Arr::get($data, $configuration['response_paths']['authorization_url']),
            'access_code' => Arr::get($data, $configuration['response_paths']['access_code']),
            'status' => 'initialized',
            'raw_response' => $data,
        ];
    }

    public function verifyPayment(string $reference, array $configuration, array $context = []): array
    {
        $endpoint = str_replace('{reference}', urlencode($reference), $configuration['verify_endpoint']);

        $response = Http::baseUrl($configuration['base_url'])
            ->withToken($configuration['secret_key'])
            ->acceptJson()
            ->get($endpoint);

        $data = $response->json();

        if (!$response->successful() || !Arr::get($data, $configuration['response_paths']['success'], false)) {
            throw new RuntimeException(Arr::get($data, $configuration['response_paths']['message'], 'Unable to verify online payment.'));
        }

        $paidStatus = (string) Arr::get($data, $configuration['response_paths']['paid_status'], '');
        $isPaid = in_array(strtolower($paidStatus), array_map('strtolower', $configuration['successful_payment_values'] ?? ['success']), true);
        $providerMessage = Arr::get($data, 'data.gateway_response')
            ?: Arr::get($data, 'data.message')
            ?: Arr::get($data, $configuration['response_paths']['message']);

        return [
            'provider' => $this->code(),
            'reference' => $reference,
            'paid' => $isPaid,
            'status' => $paidStatus ?: ($isPaid ? 'success' : 'pending'),
            // The top-level Paystack message describes the API lookup, not
            // necessarily the payment result (for example: "Verification
            // successful" with an abandoned transaction). Prefer the actual
            // provider transaction message when payment is not confirmed.
            'message' => $isPaid
                ? Arr::get($data, $configuration['response_paths']['message'])
                : $providerMessage,
            'raw_response' => $data,
        ];
    }
}
