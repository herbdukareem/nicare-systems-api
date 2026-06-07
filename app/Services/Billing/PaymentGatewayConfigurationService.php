<?php

namespace App\Services\Billing;

use App\Models\Configuration;
use Illuminate\Support\Arr;

class PaymentGatewayConfigurationService
{
    private const ACTIVE_GATEWAY_KEY = 'PAYMENT_GATEWAY_ACTIVE';

    private const GATEWAY_CONFIG_PREFIX = 'PAYMENT_GATEWAY_CONFIG_';

    public function availableGatewayOptions(): array
    {
        return [
            ['name' => 'Paystack', 'code' => 'paystack', 'type' => 'online'],
            ['name' => 'Bank Transfer', 'code' => 'bank_transfer', 'type' => 'offline'],
            ['name' => 'POS', 'code' => 'pos', 'type' => 'offline'],
            ['name' => 'Cash Office', 'code' => 'cash', 'type' => 'offline'],
        ];
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
            default => [
                'code' => $code,
                'provider_name' => str($code)->replace('_', ' ')->title()->toString(),
                'enabled' => false,
            ],
        };
    }

    private function configKey(string $code): string
    {
        return self::GATEWAY_CONFIG_PREFIX . strtoupper($code);
    }
}
