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
        if ($purchase->payment_method !== 'online_payment') {
            $isConfirmed = $purchase->payment_status === 'confirmed';
            $status = $purchase->payment_status === 'cancelled'
                ? 'cancelled'
                : ($isConfirmed ? 'confirmed' : 'pending_manual_confirmation');

            return [
                'purchase' => $purchase->fresh(['plan', 'benefactor', 'fundingType', 'group']),
                'verification' => [
                    'provider' => 'manual_confirmation',
                    'reference' => $purchase->payment_reference,
                    'status' => $status,
                    'paid' => $isConfirmed,
                    'message' => $isConfirmed
                        ? 'Payment has already been confirmed manually.'
                        : 'This payment is awaiting manual confirmation.',
                ],
            ];
        }

        $gatewayCode = $purchase->gateway_code ?: $purchase->plan?->payment_gateway ?: $this->configurationService->getActiveGatewayCode();

        if (!$gatewayCode) {
            throw new RuntimeException('No payment gateway is associated with this purchase.');
        }

        $configuration = $this->configurationService->getConfig($gatewayCode);
        $result = $this->gatewayManager->gateway($gatewayCode)->verifyPayment(
            $purchase->payment_reference,
            $configuration,
            [
                'amount' => (float) $purchase->amount,
                'purchase_id' => $purchase->id,
            ]
        );

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
