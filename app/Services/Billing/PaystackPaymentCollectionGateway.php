<?php

namespace App\Services\Billing;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentCollectionGateway
{
    public function createTemporaryAccount(array $intent, array $config): array
    {
        $response = Http::baseUrl($config['base_url'])->withToken($config['secret_key'])->acceptJson()->post('/charge', [
            'email' => $intent['payer']['email'], 'amount' => (int) round($intent['amount_due'] * 100),
            'currency' => $intent['currency'], 'reference' => $intent['reference'],
            'bank_transfer' => ['account_expires_at' => Carbon::parse($intent['expires_at'])->utc()->toIso8601String()],
            'metadata' => $intent['metadata'],
        ]);
        $data = $response->json();
        if (!$response->successful() || !Arr::get($data, 'status')) throw new RuntimeException((string) Arr::get($data, 'message', 'Paystack could not create a temporary transfer account.'));
        return $this->accountPayload(Arr::get($data, 'data', []), 'per_payment');
    }

    public function createDedicatedAccount(array $payer, array $config): array
    {
        $customer = Http::baseUrl($config['base_url'])->withToken($config['secret_key'])->acceptJson()->post('/customer', [
            'email' => $payer['email'], 'first_name' => $payer['first_name'], 'last_name' => $payer['last_name'], 'phone' => $payer['phone'] ?? null,
        ]);
        $customerData = $customer->json();
        if (!$customer->successful() || !Arr::get($customerData, 'status')) throw new RuntimeException((string) Arr::get($customerData, 'message', 'Paystack could not create the customer profile.'));
        $customerCode = Arr::get($customerData, 'data.customer_code');
        $account = Http::baseUrl($config['base_url'])->withToken($config['secret_key'])->acceptJson()->post('/dedicated_account', ['customer' => $customerCode]);
        $data = $account->json();
        if (!$account->successful() || !Arr::get($data, 'status')) throw new RuntimeException((string) Arr::get($data, 'message', 'Paystack could not create a dedicated virtual account.'));
        return $this->accountPayload(Arr::get($data, 'data', []), 'per_payer') + ['customer_code' => $customerCode];
    }

    public function validWebhook(string $payload, ?string $signature, array $config): bool
    {
        return is_string($signature) && hash_equals(hash_hmac('sha512', $payload, (string) $config['secret_key']), $signature);
    }

    public function refund(string $reference, float $amount, string $reason, array $config): array
    {
        $response = Http::baseUrl($config['base_url'])->withToken($config['secret_key'])->acceptJson()->post('/refund', ['transaction' => $reference, 'amount' => (int) round($amount * 100), 'currency' => 'NGN', 'customer_note' => $reason, 'merchant_note' => $reason]);
        $data = $response->json();
        if (!$response->successful() || !Arr::get($data, 'status')) throw new RuntimeException((string) Arr::get($data, 'message', 'Paystack could not queue the refund.'));
        return $data;
    }

    private function accountPayload(array $data, string $mode): array
    {
        return ['provider_account_id' => (string) ($data['id'] ?? ''), 'provider_reference' => (string) ($data['reference'] ?? ''), 'bank_name' => (string) data_get($data, 'bank.name', ''), 'account_name' => (string) ($data['account_name'] ?? ''), 'account_number' => (string) ($data['account_number'] ?? ''), 'expires_at' => $data['account_expires_at'] ?? null, 'metadata' => ['assignment' => $data['assignment'] ?? null, 'customer' => $data['customer'] ?? null], 'mode' => $mode];
    }
}
