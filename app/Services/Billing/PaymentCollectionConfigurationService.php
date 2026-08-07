<?php

namespace App\Services\Billing;

use App\Models\Configuration;
use Illuminate\Support\Arr;

class PaymentCollectionConfigurationService
{
    private const KEY = 'PAYMENT_COLLECTION_CONFIG';

    public function get(): array
    {
        $stored = Configuration::getValue(self::KEY);
        $stored = is_string($stored) ? json_decode($stored, true) : [];

        return array_replace_recursive($this->defaults(), is_array($stored) ? $stored : []);
    }

    public function save(array $config): array
    {
        $saved = array_replace_recursive($this->defaults(), $config);
        $saved['per_payment']['expiry_minutes'] = min(480, max(15, (int) $saved['per_payment']['expiry_minutes']));
        $saved['per_payer']['intent_expiry_hours'] = min(168, max(1, (int) $saved['per_payer']['intent_expiry_hours']));
        $saved['bulk_pin']['intent_expiry_hours'] = min(168, max(24, (int) $saved['bulk_pin']['intent_expiry_hours']));
        Configuration::setValue(self::KEY, json_encode($saved, JSON_UNESCAPED_SLASHES), 'Paystack virtual-account collection settings.');

        return $saved;
    }

    public function defaults(): array
    {
        return [
            'enabled' => false,
            'provider' => 'paystack',
            'default_mode' => 'per_payment',
            'allow_modes' => ['per_payment', 'per_payer'],
            'exact_amount_only' => true,
            'refunds_allowed' => true,
            'per_payment' => ['expiry_minutes' => 480],
            'per_payer' => ['intent_expiry_hours' => 24],
            'bulk_pin' => ['intent_expiry_hours' => 72],
        ];
    }

    public function expiryFor(string $mode, string $purpose): \DateTimeInterface
    {
        $config = $this->get();
        if ($mode === 'per_payment') return now()->addMinutes((int) Arr::get($config, 'per_payment.expiry_minutes', 480));
        $hours = $purpose === 'premium_pin_purchase'
            ? (int) Arr::get($config, 'bulk_pin.intent_expiry_hours', 72)
            : (int) Arr::get($config, 'per_payer.intent_expiry_hours', 24);
        return now()->addHours($hours);
    }
}
