<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\PremiumPurchase;
use App\Services\Billing\BillingPaymentVerificationService;

class PublicEnrollmentPaymentController extends BaseController
{
    public function __construct(private BillingPaymentVerificationService $verificationService)
    {
    }

    public function verify(string $reference)
    {
        $purchase = PremiumPurchase::with('plan')->where('payment_reference', $reference)->firstOrFail();

        $result = $this->verificationService->verifyPurchase($purchase);

        return $this->sendResponse($result, 'Payment verification completed successfully.');
    }
}
