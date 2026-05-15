<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePremiumPlanRequest;
use App\Http\Resources\PremiumPlanResource;
use App\Models\PremiumPlan;
use App\Services\PremiumAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PremiumPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = PremiumPlan::with(['programme', 'benefitPackage'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('code', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return PremiumPlanResource::collection($plans);
    }

    public function store(StorePremiumPlanRequest $request, PremiumAuditService $audit): JsonResponse
    {
        $plan = PremiumPlan::create($request->normalized() + ['created_by' => auth()->id()]);
        $audit->record($plan, 'premium_plan_created', "Premium plan {$plan->name} created.", [], $plan->toArray());

        return response()->json(['success' => true, 'data' => new PremiumPlanResource($plan->load(['programme', 'benefitPackage']))], 201);
    }

    public function show(PremiumPlan $premiumPlan): PremiumPlanResource
    {
        return new PremiumPlanResource($premiumPlan->load(['programme', 'benefitPackage']));
    }

    public function update(StorePremiumPlanRequest $request, PremiumPlan $premiumPlan, PremiumAuditService $audit): JsonResponse
    {
        $old = $premiumPlan->toArray();
        $premiumPlan->update($request->normalized() + ['updated_by' => auth()->id()]);
        $audit->record($premiumPlan, 'premium_plan_updated', "Premium plan {$premiumPlan->name} updated.", $old, $premiumPlan->fresh()->toArray());

        return response()->json(['success' => true, 'data' => new PremiumPlanResource($premiumPlan->fresh(['programme', 'benefitPackage']))]);
    }

    public function destroy(PremiumPlan $premiumPlan, PremiumAuditService $audit): JsonResponse
    {
        $old = $premiumPlan->toArray();
        $hasUsage = $premiumPlan->enrollees()->exists()
            || $premiumPlan->premiumPins()->exists()
            || $premiumPlan->purchases()->exists();

        if ($hasUsage) {
            $premiumPlan->update(['status' => 'archived', 'updated_by' => auth()->id()]);
            $audit->record($premiumPlan, 'premium_plan_archived', "Premium plan {$premiumPlan->name} archived because it already has usage records.", $old, $premiumPlan->fresh()->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Premium plan has existing usage, so it was archived instead of permanently deleted.',
                'data' => new PremiumPlanResource($premiumPlan->fresh(['programme', 'benefitPackage'])),
            ]);
        }

        $premiumPlan->delete();
        $audit->record($premiumPlan, 'premium_plan_deleted', "Unused premium plan {$old['name']} deleted.", $old, []);

        return response()->json(['success' => true, 'message' => 'Premium plan deleted']);
    }
}
