<?php

namespace App\Services\Billing;

use App\Models\PremiumPurchase;
use App\Services\PremiumCoverageService;
use RuntimeException;

class BillingPaymentVerificationService
{
    public function __construct(
        private BillingGatewayManager $gatewayManager,
        private PaymentGatewayConfigurationService $configurationService,
        private PremiumCoverageService $premiumCoverageService
    ) {
    }

    public function verifyPurchase(PremiumPurchase $purchase): array
    {
        $gatewayCode = $purchase->gateway_code ?: $purchase->plan?->payment_gateway ?: $this->configurationService->getActiveGatewayCode();

        if (!$gatewayCode) {
            throw new RuntimeException('No payment gateway is associated with this purchase.');
        }

        $configuration = $this->configurationService->getConfig($gatewayCode);
        $result = $this->gatewayManager->gateway($gatewayCode)->verifyPayment($purchase->payment_reference, $configuration);

        $purchase->forceFill([
            'gateway_code' => $gatewayCode,
            'gateway_status' => $result['status'] ?? null,
            'gateway_response' => $result['raw_response'] ?? null,
            'verified_at' => now(),
        ])->save();

        if ($result['paid'] ?? false) {
            $purchase = $this->premiumCoverageService->markPurchasePaidFromGateway($purchase, $result);
        }

        return [
            'purchase' => $purchase->fresh(['plan', 'benefactor', 'fundingType', 'group']),
            'verification' => $result,
        ];
    }
}
