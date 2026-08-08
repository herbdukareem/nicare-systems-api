<?php

namespace App\Services\Billing;

interface BillingPaymentGatewayInterface
{
    public function code(): string;

    /** @return array{base_amount: float, processing_fee: float, customer_total: float} */
    public function quoteCheckout(float $baseAmount, array $configuration): array;

    public function initializeCheckout(array $payload, array $configuration): array;

    public function verifyPayment(string $reference, array $configuration, array $context = []): array;
}
