<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Services\ClaimsAutomation\BundleClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimLineController extends Controller
{
    private BundleClassificationService $classificationService;

    public function __construct(BundleClassificationService $classificationService)
    {
        $this->classificationService = $classificationService;
    }

    /**
     * Add a bundle treatment to a claim
     * POST /api/claims/{claimId}/lines/bundle
     */
    public function addBundleTreatment(Request $request, Claim $claim): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pa_code_id' => 'required|integer|exists:pa_codes,id',
                'service_description' => 'required|string',
                'quantity' => 'required|integer|min:1',
            ]);

            $claimLine = $this->classificationService->addBundleTreatment(
                $claim,
                $validated['pa_code_id'],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Bundle treatment added successfully',
                'data' => $claimLine,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add an FFS treatment to a claim
     * POST /api/claims/{claimId}/lines/ffs
     */
    public function addFFSTreatment(Request $request, Claim $claim): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pa_code_id' => 'required|integer|exists:pa_codes,id',
                'service_description' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'unit_price' => 'required|numeric|min:0',
            ]);

            $claimLine = $this->classificationService->addFFSTreatment(
                $claim,
                $validated['pa_code_id'],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'FFS treatment added successfully',
                'data' => $claimLine,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get claim line details
     * GET /api/claim-lines/{id}
     */
    public function show(ClaimLine $claimLine): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $claimLine->load(['claim', 'paCode']),
        ]);
    }

    /**
     * Delete a claim line
     * DELETE /api/claim-lines/{id}
     */
    public function destroy(ClaimLine $claimLine): JsonResponse
    {
        try {
            $claimLine->delete();

            return response()->json([
                'success' => true,
                'message' => 'Claim line deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get claim treatment classification
     * GET /api/claims/{claimId}/classification
     */
    public function getClassification(Claim $claim): JsonResponse
    {
        $classification = $this->classificationService->classifyTreatments($claim);

        return response()->json([
            'success' => true,
            'data' => $classification,
        ]);
    }
}

