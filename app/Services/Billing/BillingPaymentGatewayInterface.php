<?php

namespace App\Services\Billing;

interface BillingPaymentGatewayInterface
{
    public function code(): string;

    public function initializeCheckout(array $payload, array $configuration): array;

    public function verifyPayment(string $reference, array $configuration): array;
}
