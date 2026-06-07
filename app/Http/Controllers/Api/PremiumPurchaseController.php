<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePremiumPurchaseRequest;
use App\Models\PremiumPurchase;
use App\Services\Billing\BillingCheckoutService;
use App\Services\Billing\BillingPaymentVerificationService;
use App\Services\PremiumCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PremiumPurchaseController extends Controller
{
    public function index(Request $request)
    {
        return PremiumPurchase::with(['plan', 'benefactor', 'fundingType', 'group'])
            ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->payer_type, fn ($q) => $q->where('payer_type', $request->payer_type))
            ->latest()
            ->paginate($request->get('per_page', 15));
    }

    public function store(
        StorePremiumPurchaseRequest $request,
        PremiumCoverageService $service,
        BillingCheckoutService $checkoutService
    ): JsonResponse
    {
        $purchase = $service->createPurchase($request->validated());
        $checkout = null;

        if ($request->boolean('initialize_checkout') && $purchase->payment_method === 'online_payment') {
            $checkout = $checkoutService->initializePurchaseCheckout($purchase);
            $purchase->update([
                'gateway_code' => $checkout['provider'] ?? $purchase->gateway_code,
                'gateway_status' => $checkout['status'] ?? 'initialized',
                'authorization_url' => $checkout['authorization_url'] ?? null,
                'gateway_access_code' => $checkout['access_code'] ?? null,
                'gateway_response' => $checkout['raw_response'] ?? null,
            ]);
            $purchase = $purchase->fresh();
        }

        return response()->json([
            'success' => true,
            'data' => $purchase->load(['plan', 'benefactor', 'fundingType', 'group']),
            'checkout' => $checkout,
        ], 201);
    }

    public function show(PremiumPurchase $premiumPurchase): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $premiumPurchase->load(['plan', 'benefactor', 'fundingType', 'group', 'pins'])]);
    }

    public function confirm(PremiumPurchase $premiumPurchase, PremiumCoverageService $service): JsonResponse
    {
        $purchase = $service->confirmPurchase($premiumPurchase);

        return response()->json(['success' => true, 'data' => $purchase->load(['plan', 'benefactor', 'fundingType', 'group'])]);
    }

    public function cancel(PremiumPurchase $premiumPurchase, PremiumCoverageService $service): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $service->cancelPurchase($premiumPurchase)]);
    }

    public function initializeCheckout(PremiumPurchase $premiumPurchase, BillingCheckoutService $checkoutService): JsonResponse
    {
        $checkout = $checkoutService->initializePurchaseCheckout($premiumPurchase);

        $premiumPurchase->update([
            'gateway_code' => $checkout['provider'] ?? $premiumPurchase->gateway_code,
            'gateway_status' => $checkout['status'] ?? 'initialized',
            'authorization_url' => $checkout['authorization_url'] ?? null,
            'gateway_access_code' => $checkout['access_code'] ?? null,
            'gateway_response' => $checkout['raw_response'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $premiumPurchase->fresh(['plan', 'benefactor', 'fundingType', 'group']),
            'checkout' => $checkout,
        ]);
    }

    public function verify(PremiumPurchase $premiumPurchase, BillingPaymentVerificationService $verificationService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $verificationService->verifyPurchase($premiumPurchase),
        ]);
    }
}
