<?php

namespace App\Http\Controllers\PAS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Services\ClaimValidationService;

class ClaimController extends Controller
{
    protected $claimValidator;

    public function __construct(ClaimValidationService $claimValidator)
    {
        $this->claimValidator = $claimValidator;
    }

    /**
     * Handles POST /v1/pas/claims: Submits a new claim.
     */
    public function store(Request $request)
    {
        // Simple validation, assuming the front-end sends a structured payload
        $request->validate([
            'enrollee_id' => 'required|exists:enrollees,id',
            'facility_id' => 'required|exists:facilities,id',
            'service_date' => 'required|date',
            'claim_lines' => 'required|array',
            'claim_lines.*.case_record_id' => 'required|exists:cases,id',
            'claim_lines.*.pa_code_id' => 'nullable|exists:pa_codes,id', // Can be null for non-PA services
            'claim_lines.*.unit_price' => 'required|numeric',
            'claim_lines.*.tariff_type' => 'required|in:BUNDLE,FFS',
        ]);
        
        $totalAmount = 0;

        DB::beginTransaction();
        try {
            // 1. Create the main claim record
            $claim = Claim::create([
                'enrollee_id' => $request->enrollee_id,
                'facility_id' => $request->facility_id,
                'claim_number' => 'CLM-' . time(),
                'service_date' => $request->service_date,
                'submission_date' => now(),
                'status' => 'SUBMITTED',
            ]);

            // 2. Create the claim line items
            foreach ($request->claim_lines as $line) {
                $lineTotal = $line['unit_price'] * ($line['quantity'] ?? 1);
                $totalAmount += $lineTotal;

                ClaimLine::create([
                    'claim_id' => $claim->id,
                    'case_record_id' => $line['case_record_id'],
                    'pa_code_id' => $line['pa_code_id'] ?? null,
                    'tariff_type' => $line['tariff_type'],
                    'service_description' => $line['service_description'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'] ?? 1,
                    'line_total' => $lineTotal,
                ]);
            }
            
            // Update claim total
            $claim->update(['total_amount' => $totalAmount]);
            
            // 3. Run Automated Compliance Checks
            $alerts = $this->claimValidator->runChecks($claim);
            // In a real system, alerts would be saved to a dedicated table (e.g., claim_alerts)

            DB::commit();

            return response()->json(['claim' => $claim, 'alerts' => $alerts], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Claim submission failed.', 'error' => $e->getMessage()], 500);
        }
    }
}