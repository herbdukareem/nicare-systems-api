<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Resources\EnrolleeResource;
use App\Models\PremiumPurchase;
use App\Services\Billing\BillingPaymentVerificationService;
use App\Services\PublicEnrollmentService;
use Illuminate\Http\Request;

class PublicEnrollmentPaymentController extends BaseController
{
    public function __construct(private BillingPaymentVerificationService $verificationService)
    {
    }

    public function verify(Request $request, string $reference, PublicEnrollmentService $publicEnrollmentService)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $purchase = PremiumPurchase::with('plan')->where('payment_reference', $reference)->firstOrFail();

        if (
            data_get($purchase->payer_details, 'channel') !== 'self_service_enrollment'
            || !hash_equals((string) data_get($purchase->payer_details, 'public_verification_token'), $validated['token'])
        ) {
            return $this->sendError('The payment verification link is invalid or incomplete.', [], 422);
        }

        $result = $this->verificationService->verifyPurchase($purchase);
        $enrollmentResult = $publicEnrollmentService->finalizePaymentVerification($result['purchase']);

        return $this->sendResponse([
            ...$result,
            'enrollee' => isset($enrollmentResult['enrollee']) ? new EnrolleeResource($enrollmentResult['enrollee']) : null,
            'nin_verification' => $enrollmentResult['nin_verification'] ?? null,
            'pin_applied' => $enrollmentResult['pin_applied'] ?? null,
            'payment_breakdown' => $enrollmentResult['payment_breakdown'] ?? data_get($result['purchase']->payer_details, 'payment_breakdown'),
            'warnings' => $enrollmentResult['warnings'] ?? [],
            'next_steps' => $enrollmentResult['next_steps'] ?? [],
        ], 'Payment verification completed successfully.');
    }
}
