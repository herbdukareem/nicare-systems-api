<?php

namespace App\Services\Billing;

use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use Illuminate\Support\Arr;
use RuntimeException;

class BillingCheckoutService
{
    public function __construct(
        private BillingGatewayManager $gatewayManager,
        private PaymentGatewayConfigurationService $configurationService,
        private BillingSplitConfigurationService $splitConfigurationService
    ) {
    }

    public function initializePremiumEnrollmentPlanCheckout(PremiumPlan $plan, array $payer, string $reference): array
    {
        $gatewayCode = $plan->payment_gateway ?: $this->configurationService->getActiveGatewayCode();
        $configuration = $this->configurationService->getConfig($gatewayCode);

        if (!in_array($gatewayCode, ['paystack', 'monnify', 'remita', 'quickteller'], true)) {
            throw new RuntimeException('The selected plan is not linked to a supported online checkout gateway yet.');
        }

        if (!$this->configurationService->isGatewayEnabled($gatewayCode)) {
            throw new RuntimeException("The configured {$gatewayCode} gateway is not enabled for online checkout.");
        }

        $callbackBase = rtrim(config('app.url'), '/');
        $callbackPath = (string) ($configuration['callback_path'] ?? '/enroll/start?checkout_return=1');
        $separator = str_contains($callbackPath, '?') ? '&' : '?';
        $callbackUrl = "{$callbackBase}{$callbackPath}{$separator}payment_reference={$reference}";

        return $this->gatewayManager->gateway($gatewayCode)->initializeCheckout([
            'email' => $payer['email'],
            'amount' => $plan->amount,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'description' => $plan->name,
            'name' => $this->payerName(
                $payer['name'] ?? null,
                $payer['first_name'] ?? null,
                $payer['last_name'] ?? null
            ),
            'first_name' => $payer['first_name'] ?? null,
            'last_name' => $payer['last_name'] ?? null,
            'phone' => $payer['phone'] ?? null,
            'metadata' => Arr::get($payer, 'metadata', []),
            'split_config' => $this->splitConfigurationService->resolveForPlan($plan, $gatewayCode),
        ], $configuration);
    }

    public function initializePurchaseCheckout(PremiumPurchase $purchase, ?string $callbackPath = null): array
    {
        $plan = $purchase->plan()->firstOrFail();
        if (blank($purchase->payer_email)) {
            throw new RuntimeException('Payer email is required to initialize secure online payment.');
        }

        $gatewayCode = $purchase->gateway_code ?: $plan->payment_gateway ?: $this->configurationService->getActiveGatewayCode();
        $configuration = $this->configurationService->getConfig($gatewayCode);

        if (!in_array($gatewayCode, ['paystack', 'monnify', 'remita', 'quickteller'], true)) {
            throw new RuntimeException('This purchase is not linked to a supported online checkout gateway yet.');
        }

        if (!$this->configurationService->isGatewayEnabled($gatewayCode)) {
            throw new RuntimeException("The configured {$gatewayCode} gateway is not enabled for online checkout.");
        }

        $callbackBase = rtrim(config('app.url'), '/');
        $callbackPath ??= '/premium/purchases?checkout_return=1';
        $callbackUrl = "{$callbackBase}{$callbackPath}&purchase_id={$purchase->id}&payment_reference={$purchase->payment_reference}";

        return $this->gatewayManager->gateway($gatewayCode)->initializeCheckout([
            'email' => $purchase->payer_email,
            'amount' => $purchase->amount,
            'reference' => $purchase->payment_reference,
            'callback_url' => $callbackUrl,
            'description' => $plan->name,
            'payer_name' => $purchase->payer_name,
            'phone' => $purchase->payer_phone,
            'metadata' => [
                'purchase_id' => $purchase->id,
                'premium_plan_id' => $plan->id,
                'channel' => data_get($purchase->payer_details, 'channel', 'premium_purchase'),
            ],
            'split_config' => $this->splitConfigurationService->resolveForPlan($plan, $gatewayCode),
        ], $configuration);
    }

    private function payerName(?string $name, ?string $firstName, ?string $lastName): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return $name;
        }

        $parts = array_filter([
            trim((string) $firstName),
            trim((string) $lastName),
        ]);

        return $parts !== [] ? implode(' ', $parts) : 'Customer';
    }
}
