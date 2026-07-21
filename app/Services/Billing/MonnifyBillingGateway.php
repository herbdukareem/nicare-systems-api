<?php

namespace App\Services\Billing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MonnifyBillingGateway implements BillingPaymentGatewayInterface
{
    public function code(): string
    {
        return 'monnify';
    }

    public function initializeCheckout(array $payload, array $configuration): array
    {
        $response = Http::baseUrl($configuration['base_url'])
            ->withToken($this->accessToken($configuration))
            ->acceptJson()
            ->post($configuration['initialize_endpoint'], array_filter([
                'amount' => (float) $payload['amount'],
                'customerEmail' => $payload['email'],
                'paymentReference' => $payload['reference'],
                'paymentDescription' => $payload['description'] ?? 'Premium enrollment checkout',
                'currencyCode' => $configuration['currency'] ?? 'NGN',
                'contractCode' => $configuration['contract_code'],
                'redirectUrl' => $payload['callback_url'],
                'paymentMethods' => $payload['channels'] ?? ($configuration['payment_methods'] ?? null),
                'metadata' => $payload['metadata'] ?? [],
                'incomeSplitConfig' => Arr::get($payload, 'split_config.incomeSplitConfig'),
            ], static fn ($value) => $value !== null && $value !== []));

        $data = $response->json();

        if (!$response->successful() || !Arr::get($data, $configuration['response_paths']['success'], false)) {
            throw new RuntimeException((string) Arr::get($data, $configuration['response_paths']['message'], 'Unable to initialize Monnify checkout.'));
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
            ->withToken($this->accessToken($configuration))
            ->acceptJson()
            ->get($endpoint);

        $data = $response->json();

        if (!$response->successful() || !Arr::get($data, $configuration['response_paths']['success'], false)) {
            throw new RuntimeException((string) Arr::get($data, $configuration['response_paths']['message'], 'Unable to verify Monnify payment.'));
        }

        $paidStatus = (string) Arr::get($data, $configuration['response_paths']['paid_status'], '');
        $isPaid = in_array(strtolower($paidStatus), array_map('strtolower', $configuration['successful_payment_values'] ?? ['paid']), true);

        return [
            'provider' => $this->code(),
            'reference' => $reference,
            'paid' => $isPaid,
            'status' => $paidStatus ?: ($isPaid ? 'PAID' : 'PENDING'),
            'message' => Arr::get($data, $configuration['response_paths']['message']),
            'raw_response' => $data,
        ];
    }

    private function accessToken(array $configuration): string
    {
        $cacheKey = 'monnify_access_token:' . md5(json_encode([
            $configuration['base_url'] ?? '',
            $configuration['api_key'] ?? '',
            $configuration['secret_key'] ?? '',
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($configuration): string {
            $credentials = base64_encode(($configuration['api_key'] ?? '') . ':' . ($configuration['secret_key'] ?? ''));

            $response = Http::baseUrl($configuration['base_url'])
                ->withHeaders([
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($configuration['login_endpoint']);

            $data = $response->json();

            if (!$response->successful() || !Arr::get($data, 'requestSuccessful', false)) {
                throw new RuntimeException((string) Arr::get($data, 'responseMessage', 'Unable to authenticate with Monnify.'));
            }

            $token = (string) Arr::get($data, 'responseBody.accessToken', '');
            if ($token === '') {
                throw new RuntimeException('Monnify did not return an access token.');
            }

            return $token;
        });
    }
}
