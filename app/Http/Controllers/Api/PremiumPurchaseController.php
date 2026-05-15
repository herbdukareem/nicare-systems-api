<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePremiumPurchaseRequest;
use App\Models\PremiumPurchase;
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

    public function store(StorePremiumPurchaseRequest $request, PremiumCoverageService $service): JsonResponse
    {
        $purchase = $service->createPurchase($request->validated());

        return response()->json(['success' => true, 'data' => $purchase->load(['plan', 'benefactor', 'fundingType', 'group'])], 201);
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
}
