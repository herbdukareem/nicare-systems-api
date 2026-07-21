<?php

namespace App\Services\Billing;

use App\Models\Configuration;
use Illuminate\Support\Arr;

class PaymentGatewayConfigurationService
{
    private const ACTIVE_GATEWAY_KEY = 'PAYMENT_GATEWAY_ACTIVE';

    private const GATEWAY_CONFIG_PREFIX = 'PAYMENT_GATEWAY_CONFIG_';
    private const SUBACCOUNTS_KEY = 'PAYMENT_GATEWAY_SUBACCOUNTS';
    private const SPLIT_PROFILES_KEY = 'PAYMENT_GATEWAY_SPLIT_PROFILES';

    public function availableGatewayOptions(): array
    {
        return [
            ['name' => 'Paystack', 'code' => 'paystack', 'type' => 'online', 'supports_split_profiles' => true],
            ['name' => 'Monnify', 'code' => 'monnify', 'type' => 'online', 'supports_split_profiles' => true],
            ['name' => 'Remita', 'code' => 'remita', 'type' => 'online', 'supports_split_profiles' => true],
            ['name' => 'Quickteller', 'code' => 'quickteller', 'type' => 'online', 'supports_split_profiles' => false],
        ];
    }

    public function availableGatewayCodes(): array
    {
        return collect($this->availableGatewayOptions())->pluck('code')->all();
    }

    public function getConfig(?string $code = null): array
    {
        $code ??= $this->getActiveGatewayCode();

        if (!$code) {
            return $this->defaultConfig('paystack');
        }

        $stored = Configuration::getValue($this->configKey($code));
        $config = is_string($stored) && $stored !== ''
            ? json_decode($stored, true) ?? []
            : [];

        return array_replace_recursive($this->defaultConfig($code), $config);
    }

    public function getAll(): array
    {
        $options = $this->availableGatewayOptions();
        $activeCode = $this->getActiveGatewayCode();

        return [
            'active_gateway' => $activeCode,
            'supported_gateways' => $options,
            'gateway_configurations' => collect($options)
                ->mapWithKeys(fn (array $gateway) => [
                    $gateway['code'] => $this->getConfig($gateway['code']),
                ])
                ->all(),
            'subaccounts' => $this->getSubaccounts(),
            'split_profiles' => $this->getSplitProfiles(),
        ];
    }

    public function save(array $payload): array
    {
        $activeCode = (string) ($payload['active_gateway'] ?? 'paystack');
        Configuration::setValue(self::ACTIVE_GATEWAY_KEY, $activeCode, 'Active online payment gateway for premium enrollment and purchases.');

        foreach ($this->availableGatewayOptions() as $gateway) {
            $code = $gateway['code'];
            $config = array_replace_recursive(
                $this->defaultConfig($code),
                Arr::get($payload, "gateway_configurations.$code", [])
            );

            Configuration::setValue(
                $this->configKey($code),
                json_encode($config, JSON_UNESCAPED_SLASHES),
                ucfirst(str_replace('_', ' ', $code)) . ' gateway configuration.'
            );
        }

        Configuration::setValue(
            self::SUBACCOUNTS_KEY,
            json_encode($this->normalizeSubaccounts((array) ($payload['subaccounts'] ?? [])), JSON_UNESCAPED_SLASHES),
            'Configured external gateway subaccounts.'
        );

        Configuration::setValue(
            self::SPLIT_PROFILES_KEY,
            json_encode($this->normalizeSplitProfiles((array) ($payload['split_profiles'] ?? [])), JSON_UNESCAPED_SLASHES),
            'Configured gateway split profiles for hosted checkout.'
        );

        return $this->getAll();
    }

    public function getActiveGatewayCode(): ?string
    {
        return Configuration::getValue(self::ACTIVE_GATEWAY_KEY, 'paystack');
    }

    public function isGatewayEnabled(string $code): bool
    {
        $config = $this->getConfig($code);

        return (bool) ($config['enabled'] ?? false);
    }

    public function supportsSplitProfiles(string $code): bool
    {
        $gateway = collect($this->availableGatewayOptions())->firstWhere('code', $code);

        return (bool) ($gateway['supports_split_profiles'] ?? false);
    }

    public function defaultConfig(string $code): array
    {
        return match ($code) {
            'paystack' => [
                'code' => 'paystack',
                'provider_name' => 'Paystack',
                'enabled' => false,
                'base_url' => 'https://api.paystack.co',
                'initialize_endpoint' => '/transaction/initialize',
                'verify_endpoint' => '/transaction/verify/{reference}',
                'public_key' => '',
                'secret_key' => '',
                'currency' => 'NGN',
                'callback_path' => '/enroll/start?checkout_return=1',
                'request_amount_multiplier' => 100,
                'response_paths' => [
                    'success' => 'status',
                    'authorization_url' => 'data.authorization_url',
                    'access_code' => 'data.access_code',
                    'reference' => 'data.reference',
                    'paid_status' => 'data.status',
                    'message' => 'message',
                ],
                'successful_payment_values' => ['success'],
            ],
            'monnify' => [
                'code' => 'monnify',
                'provider_name' => 'Monnify',
                'enabled' => false,
                'base_url' => 'https://sandbox.monnify.com',
                'login_endpoint' => '/api/v1/auth/login',
                'initialize_endpoint' => '/api/v1/merchant/transactions/init-transaction',
                'verify_endpoint' => '/api/v2/merchant/transactions/query?paymentReference={reference}',
                'api_key' => '',
                'secret_key' => '',
                'contract_code' => '',
                'currency' => 'NGN',
                'callback_path' => '/enroll/start?checkout_return=1',
                'payment_methods' => ['CARD', 'ACCOUNT_TRANSFER', 'USSD'],
                'request_amount_multiplier' => 1,
                'response_paths' => [
                    'success' => 'requestSuccessful',
                    'authorization_url' => 'responseBody.checkoutUrl',
                    'access_code' => 'responseBody.transactionReference',
                    'reference' => 'responseBody.paymentReference',
                    'paid_status' => 'responseBody.paymentStatus',
                    'message' => 'responseMessage',
                ],
                'successful_payment_values' => ['PAID'],
            ],
            'remita' => [
                'code' => 'remita',
                'provider_name' => 'Remita',
                'enabled' => false,
                'base_url' => 'https://api-demo.systemspecsng.com',
                'initialize_endpoint' => '/services/connect-gateway/api/v1/payment/charge',
                'verify_endpoint' => '/services/connect-gateway/api/v1/payment-engine/payment/merchant/verify/{reference}',
                'secret_key' => '',
                'currency' => 'NGN',
                'callback_path' => '/enroll/start?checkout_return=1',
                'request_amount_multiplier' => 1,
                'response_paths' => [
                    'success' => 'status',
                    'authorization_url' => 'data.paymentLink',
                    'access_code' => 'data.paymentIdentifier',
                    'reference' => 'data.paymentIdentifier',
                    'paid_status' => 'data.status',
                    'message' => 'message',
                ],
                'successful_payment_values' => ['SUCCESS', 'APPROVED', '00'],
            ],
            'quickteller' => [
                'code' => 'quickteller',
                'provider_name' => 'Quickteller',
                'enabled' => false,
                'base_url' => 'https://sandbox.interswitchng.com',
                'initialize_endpoint' => '/collections/w/pay',
                'verify_endpoint' => '/collections/api/v1/gettransaction.json?merchantcode={merchant_code}&transactionreference={reference}&amount={amount}',
                'merchant_code' => '',
                'pay_item_id' => '',
                'currency' => '566',
                'mode' => 'TEST',
                'callback_path' => '/enroll/start?checkout_return=1',
                'request_amount_multiplier' => 100,
                'response_paths' => [
                    'paid_status' => 'ResponseCode',
                    'message' => 'ResponseDescription',
                    'reference' => 'MerchantReference',
                    'access_code' => 'PaymentReference',
                ],
                'successful_payment_values' => ['00'],
            ],
            default => [
                'code' => $code,
                'provider_name' => str($code)->replace('_', ' ')->title()->toString(),
                'enabled' => false,
            ],
        };
    }

    public function getSubaccounts(): array
    {
        $stored = Configuration::getValue(self::SUBACCOUNTS_KEY);
        $subaccounts = is_string($stored) && $stored !== '' ? json_decode($stored, true) : [];

        return $this->normalizeSubaccounts(is_array($subaccounts) ? $subaccounts : []);
    }

    public function getSplitProfiles(): array
    {
        $stored = Configuration::getValue(self::SPLIT_PROFILES_KEY);
        $profiles = is_string($stored) && $stored !== '' ? json_decode($stored, true) : [];

        return $this->normalizeSplitProfiles(is_array($profiles) ? $profiles : []);
    }

    private function configKey(string $code): string
    {
        return self::GATEWAY_CONFIG_PREFIX . strtoupper($code);
    }

    private function normalizeSubaccounts(array $subaccounts): array
    {
        $supported = $this->availableGatewayCodes();

        return collect($subaccounts)
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['code'] ?? '')) !== '')
            ->map(function (array $item) use ($supported): array {
                $gatewayCode = (string) ($item['gateway_code'] ?? 'paystack');
                if (!in_array($gatewayCode, $supported, true)) {
                    $gatewayCode = 'paystack';
                }

                return [
                    'code' => trim((string) $item['code']),
                    'gateway_code' => $gatewayCode,
                    'name' => trim((string) ($item['name'] ?? '')),
                    'external_code' => trim((string) ($item['external_code'] ?? '')),
                    'currency' => trim((string) ($item['currency'] ?? 'NGN')) ?: 'NGN',
                    'account_name' => trim((string) ($item['account_name'] ?? '')),
                    'bank_code' => trim((string) ($item['bank_code'] ?? '')),
                    'account_number' => trim((string) ($item['account_number'] ?? '')),
                    'email' => trim((string) ($item['email'] ?? '')),
                    'active' => (bool) ($item['active'] ?? true),
                ];
            })
            ->unique('code')
            ->values()
            ->all();
    }

    private function normalizeSplitProfiles(array $profiles): array
    {
        $supported = $this->availableGatewayCodes();

        return collect($profiles)
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['code'] ?? '')) !== '')
            ->map(function (array $item) use ($supported): array {
                $gatewayCode = (string) ($item['gateway_code'] ?? 'paystack');
                if (!in_array($gatewayCode, $supported, true)) {
                    $gatewayCode = 'paystack';
                }

                return [
                    'code' => trim((string) $item['code']),
                    'name' => trim((string) ($item['name'] ?? '')),
                    'gateway_code' => $gatewayCode,
                    'active' => (bool) ($item['active'] ?? true),
                    'settings' => [
                        'paystack' => [
                            'bearer_type' => trim((string) Arr::get($item, 'settings.paystack.bearer_type', 'account')) ?: 'account',
                            'bearer_subaccount_code' => trim((string) Arr::get($item, 'settings.paystack.bearer_subaccount_code', '')),
                        ],
                    ],
                    'entries' => collect((array) ($item['entries'] ?? []))
                        ->filter(fn ($entry) => is_array($entry) && trim((string) ($entry['subaccount_code'] ?? '')) !== '')
                        ->map(fn (array $entry): array => [
                            'subaccount_code' => trim((string) $entry['subaccount_code']),
                            'share_type' => trim((string) ($entry['share_type'] ?? 'percentage')) ?: 'percentage',
                            'share_value' => (float) ($entry['share_value'] ?? 0),
                            'fee_bearer' => (bool) ($entry['fee_bearer'] ?? false),
                            'fee_percentage' => (float) ($entry['fee_percentage'] ?? 0),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->unique('code')
            ->values()
            ->all();
    }
}
