<?php

namespace App\Services\Billing;

use App\Models\PaymentCollectionAccount;
use App\Models\PaymentIntent;
use App\Models\PremiumPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentCollectionService
{
    public function __construct(private PaymentCollectionConfigurationService $settings, private PaymentGatewayConfigurationService $gatewaySettings, private PaystackPaymentCollectionGateway $paystack) {}

    public function createForPurchase(PremiumPurchase $purchase, string $mode, string $purpose, array $payer): array
    {
        $config = $this->settings->get();
        if (!($config['enabled'] ?? false)) throw new RuntimeException('Virtual account collection is not enabled.');
        if (($config['provider'] ?? null) !== 'paystack') throw new RuntimeException('The selected payment collection provider is not supported yet.');
        if (!in_array($mode, $config['allow_modes'] ?? [], true)) throw new RuntimeException('This collection mode is not enabled.');
        $payer = $this->normalizePayer($payer);
        $expiry = $this->settings->expiryFor($mode, $purpose);

        return DB::transaction(function () use ($purchase, $mode, $purpose, $payer, $expiry) {
            $existing = PaymentIntent::whereMorphedTo('payable', $purchase)->whereIn('status', ['pending', 'awaiting_payment'])->latest()->first();
            if ($existing && $existing->expires_at?->isFuture()) return $this->present($existing->load('accounts'));
            if ($existing) $existing->update(['status' => 'expired']);
            $intent = PaymentIntent::create(['public_id' => (string) Str::uuid(), 'payable_type' => $purchase::class, 'payable_id' => $purchase->id, 'purpose' => $purpose, 'provider' => 'paystack', 'collection_mode' => $mode, 'reference' => $purchase->payment_reference ?: $this->reference(), 'amount_due' => $purchase->amount, 'currency' => 'NGN', 'status' => 'pending', 'payer' => $payer, 'metadata' => ['purchase_id' => $purchase->id, 'channel' => data_get($purchase->payer_details, 'channel')], 'expires_at' => $expiry]);
            $gatewayConfig = $this->gatewaySettings->getConfig('paystack');
            if (!$this->gatewaySettings->isGatewayEnabled('paystack')) throw new RuntimeException('Paystack is not enabled.');
            $account = $mode === 'per_payer' ? $this->dedicatedAccount($intent, $payer, $gatewayConfig) : $this->paystack->createTemporaryAccount($intent->toArray() + ['payer' => $payer, 'expires_at' => $expiry], $gatewayConfig);
            $record = $intent->accounts()->create(['provider' => 'paystack', 'mode' => $mode, 'payer_key' => $mode === 'per_payer' ? strtolower($payer['email']) : null, 'provider_account_id' => $account['provider_account_id'] ?: null, 'provider_reference' => $mode === 'per_payment' ? ($account['provider_reference'] ?: $intent->reference) : null, 'bank_name' => $account['bank_name'], 'account_name' => $account['account_name'], 'account_number' => $account['account_number'], 'status' => 'active', 'expires_at' => $account['expires_at'] ?? $expiry, 'metadata' => $account['metadata']]);
            $intent->update(['status' => 'awaiting_payment']);
            $purchase->update(['payment_method' => 'virtual_account', 'gateway_code' => 'paystack', 'gateway_status' => 'pending_bank_transfer', 'payer_details' => array_merge($purchase->payer_details ?? [], ['payment_intent_id' => $intent->id, 'payment_collection' => $this->present($intent->fresh('accounts'))])]);
            return $this->present($intent->fresh('accounts'));
        });
    }

    public function present(PaymentIntent $intent): array
    {
        $account = $intent->accounts->sortByDesc('id')->first();
        return ['id' => $intent->public_id, 'reference' => $intent->reference, 'provider' => $intent->provider, 'mode' => $intent->collection_mode, 'amount' => (float) $intent->amount_due, 'currency' => $intent->currency, 'status' => $intent->status, 'expires_at' => $intent->expires_at?->toIso8601String(), 'bank_name' => $account?->bank_name, 'account_name' => $account?->account_name, 'account_number' => $account?->account_number, 'payment_reference' => $intent->reference, 'narration_hint' => "Use {$intent->reference} as the transfer narration.", 'account' => $account ? ['bank_name' => $account->bank_name, 'account_name' => $account->account_name, 'account_number' => $account->account_number, 'expires_at' => $account->expires_at?->toIso8601String()] : null];
    }

    private function dedicatedAccount(PaymentIntent $intent, array $payer, array $gatewayConfig): array
    {
        $existing = PaymentCollectionAccount::where('provider', 'paystack')->where('mode', 'per_payer')->where('payer_key', strtolower($payer['email']))->where('status', 'active')->latest()->first();
        if ($existing) return ['provider_account_id' => $existing->provider_account_id, 'provider_reference' => $existing->provider_reference, 'bank_name' => $existing->bank_name, 'account_name' => $existing->account_name, 'account_number' => $existing->account_number, 'expires_at' => null, 'metadata' => $existing->metadata];
        return $this->paystack->createDedicatedAccount($payer, $gatewayConfig);
    }

    private function normalizePayer(array $payer): array
    {
        $name = trim((string) ($payer['name'] ?? ''));
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $result = ['email' => strtolower(trim((string) ($payer['email'] ?? ''))), 'first_name' => trim((string) ($payer['first_name'] ?? ($parts[0] ?? ''))), 'last_name' => trim((string) ($payer['last_name'] ?? ($parts[1] ?? 'Customer'))), 'phone' => trim((string) ($payer['phone'] ?? '')) ?: null];
        if ($result['email'] === '' || $result['first_name'] === '') throw new RuntimeException('Payer email and name are required for a Paystack virtual account.');
        return $result;
    }

    private function reference(): string { return 'COL-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)); }
}
