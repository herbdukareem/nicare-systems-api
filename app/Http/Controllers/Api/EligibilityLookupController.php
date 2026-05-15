<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Services\EligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EligibilityLookupController extends Controller
{
    public function show(Request $request, EligibilityService $eligibility): JsonResponse
    {
        $data = $request->validate([
            'enrollee_id' => ['nullable', 'exists:enrollees,id'],
            'enrollee_number' => ['nullable', 'string'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'date' => ['nullable', 'date'],
        ]);

        $enrollee = !empty($data['enrollee_id'])
            ? Enrollee::find($data['enrollee_id'])
            : Enrollee::where('enrollee_id', $data['enrollee_number'] ?? '')->first();

        if (!$enrollee) {
            return response()->json(['success' => false, 'eligible' => false, 'message' => 'Enrollee not found.'], 404);
        }

        try {
            $coverage = isset($data['date'])
                ? $eligibility->assertCoverageCoversDate($enrollee, $data['date'])
                : $eligibility->assertEligibleForCare($enrollee);

            if (!empty($data['facility_id'])) {
                $coverage = $eligibility->assertFacilityMatchesCoverage(
                    $enrollee,
                    (int) $data['facility_id'],
                    $data['date'] ?? null
                );
            }

            return response()->json(['success' => true, 'eligible' => true, 'data' => $coverage->load(['programme', 'category', 'plan', 'facility', 'fundingType', 'benefactor'])]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'eligible' => false, 'message' => $e->getMessage()]);
        }
    }
}
