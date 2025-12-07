<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Services\ClaimsAutomation\PaymentProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private PaymentProcessingService $paymentService;

    public function __construct(PaymentProcessingService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Calculate payment for an approved claim
     * GET /api/payments/calculate/{claimId}
     */
    public function calculate(Claim $claim): JsonResponse
    {
        try {
            $payment = $this->paymentService->calculatePayment($claim);

            return response()->json([
                'success' => true,
                'data' => $payment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process payment for an approved claim
     * POST /api/payments/process
     */
    public function process(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'claim_id' => 'required|integer|exists:claims,id',
                'payment_method' => 'required|string',
                'bank_details' => 'nullable|array',
                'notes' => 'nullable|string',
            ]);

            $claim = Claim::find($validated['claim_id']);
            $paymentAdvice = $this->paymentService->processPayment($claim, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => $paymentAdvice,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Track payment status
     * GET /api/payments/track/{claimId}
     */
    public function track(Claim $claim): JsonResponse
    {
        $status = $this->paymentService->trackPaymentStatus($claim);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Get facility payment summary
     * GET /api/payments/facility/{facilityId}/summary
     */
    public function facilityPaymentSummary(Request $request, $facilityId): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'status']);

        $summary = $this->paymentService->getFacilityPaymentSummary($facilityId, $filters);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}

