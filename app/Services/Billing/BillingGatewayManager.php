<?php

namespace App\Services\Billing;

use RuntimeException;

class BillingGatewayManager
{
    /**
     * @param  iterable<int, BillingPaymentGatewayInterface>  $gateways
     */
    public function __construct(
        private iterable $gateways,
        private PaymentGatewayConfigurationService $configurationService
    ) {
    }

    public function gateway(?string $code = null): BillingPaymentGatewayInterface
    {
        $code ??= $this->configurationService->getActiveGatewayCode();

        foreach ($this->gateways as $gateway) {
            if ($gateway->code() === $code) {
                return $gateway;
            }
        }

        throw new RuntimeException("No payment gateway adapter is registered for [{$code}].");
    }
}
