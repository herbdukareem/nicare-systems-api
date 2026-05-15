<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Services\PremiumAuditService;
use App\Services\PremiumCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PremiumPinController extends Controller
{
    public function index(Request $request)
    {
        return PremiumPin::with(['plan', 'purchase', 'usedByEnrollee'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->batch_code, fn ($q) => $q->where('batch_code', $request->batch_code))
            ->latest()
            ->paginate($request->get('per_page', 20));
    }

    public function show(PremiumPin $premiumPin): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $premiumPin->load(['plan.programme', 'plan.benefitPackage', 'insuranceProgramme', 'benefitPackage', 'lga', 'ward', 'purchase', 'usedByEnrollee.facility']),
        ]);
    }

    public function generate(Request $request, PremiumCoverageService $service): JsonResponse
    {
        $validated = $request->validate([
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $plan = PremiumPlan::findOrFail($validated['premium_plan_id']);

        if ($plan->payment_required && empty($validated['payment_reference'])) {
            return response()->json([
                'success' => false,
                'message' => 'Payment must be completed before generating PINs for this plan.',
                'payment_required' => true,
                'payment_gateway' => $plan->payment_gateway,
            ], 422);
        }

        $purchase = $service->createPurchase([
            'premium_plan_id' => $plan->id,
            'payer_type' => $plan->payment_required ? 'individual' : 'government',
            'payer_name' => $plan->payment_required ? 'Premium PIN Buyer' : 'Payment Waived',
            'payment_method' => $plan->payment_required ? ($plan->payment_gateway ?? 'online_payment') : 'payment_not_required',
            'payment_status' => 'confirmed',
            'payment_reference' => $validated['payment_reference'] ?? 'WAIVED-' . now()->format('YmdHis'),
            'quantity' => $validated['quantity'],
            'amount' => $plan->payment_required ? ((float) $plan->amount * (int) $validated['quantity']) : 0,
            'paid_at' => now(),
        ]);

        $pins = $service->generatePins($plan, $validated['quantity'], $purchase);

        return response()->json(['success' => true, 'data' => ['purchase' => $purchase, 'pins' => $pins]], 201);
    }

    public function sell(Request $request, PremiumPin $premiumPin, PremiumCoverageService $service): JsonResponse
    {
        $validated = $request->validate(['premium_purchase_id' => ['required', 'exists:premium_purchases,id']]);
        $pin = $service->sellPin($premiumPin, PremiumPurchase::findOrFail($validated['premium_purchase_id']));

        return response()->json(['success' => true, 'data' => $pin]);
    }

    public function validatePin(Request $request, PremiumCoverageService $service): JsonResponse
    {
        $validated = $request->validate(['pin' => ['required', 'string']]);
        $pin = $service->validatePin($validated['pin']);

        return response()->json(['success' => true, 'data' => $pin->load('plan')]);
    }

    public function use(Request $request, PremiumPin $premiumPin, PremiumCoverageService $service): JsonResponse
    {
        $validated = $request->validate([
            'enrollee_id' => ['required', 'exists:enrollees,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
        ]);

        $enrollee = $service->usePinForCoverage($premiumPin, Enrollee::findOrFail($validated['enrollee_id']), $validated['facility_id'] ?? null);

        return response()->json(['success' => true, 'data' => $enrollee->load(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage', 'facility'])]);
    }

    public function cancel(PremiumPin $premiumPin, PremiumAuditService $audit): JsonResponse
    {
        if ($premiumPin->status === PremiumPin::STATUS_USED) {
            return response()->json(['success' => false, 'message' => 'Used premium PIN cannot be cancelled.'], 422);
        }

        $old = $premiumPin->toArray();
        $premiumPin->update(['status' => PremiumPin::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => auth()->id()]);
        $audit->record($premiumPin, 'premium_pin_cancelled', "Premium PIN {$premiumPin->serial_number} cancelled.", $old, $premiumPin->fresh()->toArray());

        return response()->json(['success' => true, 'data' => $premiumPin->fresh()]);
    }
}
