<?php

namespace App\Services\Billing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemitaBillingGateway implements BillingPaymentGatewayInterface
{
    public function code(): string
    {
        return 'remita';
    }

    public function initializeCheckout(array $payload, array $configuration): array
    {
        [$firstName, $lastName] = $this->nameParts($payload);

        $response = Http::baseUrl($configuration['base_url'])
            ->acceptJson()
            ->withHeaders([
                'secretKey' => $configuration['secret_key'] ?? '',
                'Content-Type' => 'application/json',
            ])
            ->post($configuration['initialize_endpoint'], array_filter([
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $payload['email'],
                'phoneNumber' => $payload['phone'] ?? null,
                'paymentIdentifier' => $payload['reference'],
                'currency' => $configuration['currency'] ?? 'NGN',
                'narration' => $payload['description'] ?? 'Premium enrollment checkout',
                'amount' => $this->scaledAmount($payload['amount'] ?? 0, $configuration),
                'responseUrl' => $payload['callback_url'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
                'split' => Arr::get($payload, 'split_config.split'),
            ], static fn ($value) => $value !== null && $value !== [] && $value !== ''));

        $data = $response->json();
        $checkoutForm = is_array($data) ? $this->extractCheckoutForm($data) : null;
        $authorizationUrl = is_array($data)
            ? (Arr::get($data, $configuration['response_paths']['authorization_url']) ?: Arr::get($checkoutForm, 'action'))
            : Arr::get($checkoutForm, 'action');

        if (!$response->successful() || blank($authorizationUrl)) {
            throw new RuntimeException((string) Arr::get($data, $configuration['response_paths']['message'], 'Unable to initialize Remita checkout.'));
        }

        return [
            'provider' => $this->code(),
            'reference' => Arr::get($data, $configuration['response_paths']['reference'], $payload['reference']),
            'authorization_url' => $authorizationUrl,
            'access_code' => Arr::get($data, $configuration['response_paths']['access_code'], $payload['reference']),
            'status' => 'initialized',
            'raw_response' => is_array($data) ? $data : ['body' => (string) $response->body()],
            'checkout_form' => $checkoutForm,
        ];
    }

    public function verifyPayment(string $reference, array $configuration, array $context = []): array
    {
        $endpoint = str_replace('{reference}', urlencode($reference), $configuration['verify_endpoint']);

        $response = Http::baseUrl($configuration['base_url'])
            ->acceptJson()
            ->withHeaders([
                'secretKey' => $configuration['secret_key'] ?? '',
            ])
            ->get($endpoint);

        $data = $response->json();

        if (!$response->successful() || !is_array($data)) {
            throw new RuntimeException((string) Arr::get($data, $configuration['response_paths']['message'], 'Unable to verify Remita payment.'));
        }

        $statusCandidates = array_filter([
            (string) Arr::get($data, $configuration['response_paths']['paid_status'], ''),
            (string) Arr::get($data, 'statuscode', ''),
            (string) Arr::get($data, 'status', ''),
        ]);

        $successValues = array_map('strtolower', $configuration['successful_payment_values'] ?? ['00', 'success', 'approved']);
        $isPaid = collect($statusCandidates)
            ->contains(fn (string $value) => in_array(strtolower($value), $successValues, true));

        return [
            'provider' => $this->code(),
            'reference' => $reference,
            'paid' => $isPaid,
            'status' => Arr::get($data, $configuration['response_paths']['paid_status'])
                ?: Arr::get($data, 'statuscode')
                ?: Arr::get($data, 'status')
                ?: ($isPaid ? 'SUCCESS' : 'PENDING'),
            'message' => Arr::get($data, $configuration['response_paths']['message']),
            'raw_response' => $data,
        ];
    }

    private function scaledAmount(float|int|string $amount, array $configuration): float|int
    {
        $scaled = (float) $amount * (int) ($configuration['request_amount_multiplier'] ?? 1);

        return fmod($scaled, 1.0) === 0.0 ? (int) round($scaled) : round($scaled, 2);
    }

    private function nameParts(array $payload): array
    {
        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));

        if ($firstName !== '' && $lastName !== '') {
            return [$firstName, $lastName];
        }

        $fullName = trim((string) ($payload['payer_name'] ?? $payload['name'] ?? 'Customer'));
        $parts = preg_split('/\s+/', $fullName) ?: [];

        $firstName = $firstName !== '' ? $firstName : (array_shift($parts) ?: 'Customer');
        $lastName = $lastName !== '' ? $lastName : (count($parts) ? implode(' ', $parts) : 'Payment');

        return [$firstName, $lastName];
    }

    private function extractCheckoutForm(array $data): ?array
    {
        $html = Arr::get($data, 'html') ?: Arr::get($data, 'data.html');
        if (!is_string($html) || trim($html) === '') {
            return null;
        }

        if (!preg_match('/<form[^>]*action="([^"]+)"[^>]*method="([^"]+)"/i', $html, $formMatches)) {
            return null;
        }

        preg_match_all('/<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"/i', $html, $inputMatches, PREG_SET_ORDER);

        $fields = [];
        foreach ($inputMatches as $match) {
            $fields[$match[1]] = html_entity_decode($match[2], ENT_QUOTES);
        }

        return [
            'method' => strtoupper($formMatches[2]),
            'action' => html_entity_decode($formMatches[1], ENT_QUOTES),
            'fields' => $fields,
        ];
    }
}
