<?php

namespace App\Services\Billing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QuicktellerBillingGateway implements BillingPaymentGatewayInterface
{
    public function code(): string
    {
        return 'quickteller';
    }

    public function initializeCheckout(array $payload, array $configuration): array
    {
        $merchantCode = trim((string) ($configuration['merchant_code'] ?? ''));
        $payItemId = trim((string) ($configuration['pay_item_id'] ?? ''));

        if ($merchantCode === '' || $payItemId === '') {
            throw new RuntimeException('Quickteller requires both merchant code and pay item ID before checkout can be initialized.');
        }

        $checkoutForm = [
            'method' => 'POST',
            'action' => $this->buildUrl($configuration['base_url'] ?? '', $configuration['initialize_endpoint'] ?? ''),
            'fields' => array_filter([
                'merchant_code' => $merchantCode,
                'pay_item_id' => $payItemId,
                'site_redirect_url' => $payload['callback_url'] ?? null,
                'txn_ref' => $payload['reference'],
                'amount' => $this->scaledAmount($payload['amount'] ?? 0, $configuration),
                'currency' => (string) ($configuration['currency'] ?? '566'),
                'cust_name' => $payload['payer_name'] ?? $payload['name'] ?? null,
                'cust_email' => $payload['email'],
                'cust_mobile_no' => $payload['phone'] ?? null,
                'pay_item_name' => $payload['description'] ?? 'Premium enrollment checkout',
            ], static fn ($value) => $value !== null && $value !== ''),
        ];

        return [
            'provider' => $this->code(),
            'reference' => $payload['reference'],
            'authorization_url' => $checkoutForm['action'],
            'access_code' => $payload['reference'],
            'status' => 'initialized',
            'raw_response' => ['checkout_form' => $checkoutForm],
            'checkout_form' => $checkoutForm,
        ];
    }

    public function verifyPayment(string $reference, array $configuration, array $context = []): array
    {
        $expectedAmount = $this->scaledAmount($context['amount'] ?? 0, $configuration);
        if ((int) $expectedAmount <= 0) {
            throw new RuntimeException('Quickteller verification requires the original transaction amount.');
        }

        $endpoint = strtr($configuration['verify_endpoint'], [
            '{merchant_code}' => urlencode((string) ($configuration['merchant_code'] ?? '')),
            '{reference}' => urlencode($reference),
            '{amount}' => urlencode((string) $expectedAmount),
        ]);

        $response = Http::baseUrl($configuration['base_url'])
            ->acceptJson()
            ->get($endpoint);

        $data = $response->json();

        if (!$response->successful() || !is_array($data)) {
            throw new RuntimeException((string) Arr::get($data, $configuration['response_paths']['message'], 'Unable to verify Quickteller payment.'));
        }

        $status = (string) Arr::get($data, $configuration['response_paths']['paid_status'], '');
        $successValues = array_map('strtolower', $configuration['successful_payment_values'] ?? ['00']);
        $paidByStatus = in_array(strtolower($status), $successValues, true);
        $returnedAmount = Arr::get($data, 'Amount');
        $amountMatches = $returnedAmount === null || (int) $returnedAmount === (int) $expectedAmount;
        $isPaid = $paidByStatus && $amountMatches;

        return [
            'provider' => $this->code(),
            'reference' => Arr::get($data, $configuration['response_paths']['reference'], $reference),
            'paid' => $isPaid,
            'status' => $amountMatches ? ($status ?: ($isPaid ? '00' : 'PENDING')) : 'AMOUNT_MISMATCH',
            'message' => $amountMatches
                ? Arr::get($data, $configuration['response_paths']['message'])
                : 'Quickteller returned a paid status, but the verified amount did not match the expected amount.',
            'raw_response' => $data,
        ];
    }

    private function scaledAmount(float|int|string $amount, array $configuration): int
    {
        return (int) round((float) $amount * (int) ($configuration['request_amount_multiplier'] ?? 100));
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        if (preg_match('/^https?:\/\//i', $endpoint)) {
            return $endpoint;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }
}
