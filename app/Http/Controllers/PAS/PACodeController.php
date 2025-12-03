<?php

namespace App\Http\Controllers\PAS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PACode;
use App\Models\Facility;
use App\Models\PACode\RequestPACodeRequest; // Assumed Form Request for validation
use App\Models\Referral;

class PACodeController extends Controller
{
    /**
     * Handles POST /v1/pas/pa-codes: Creates a new PA request.
     */
    public function store(Request $request) // Use RequestPACodeRequest for real validation
    {
        $request->validate([
            'enrollee_id' => 'required|exists:enrollees,id',
            'facility_id' => 'required|exists:facilities,id',
            'is_complication_pa' => 'boolean',
            'requested_items' => 'required|array',
            'justification' => 'required_if:is_complication_pa,true|string|max:1000',
            'referral_id' => 'required|exists:referrals,id',
        ]);
        

        // 1. POLICY CHECK: MUST have an approved Referral PA (RR) first.
        $referral = Referral::find($request->referral_id);
        if (!$referral || $referral->status !== 'APPROVED') {
             return response()->json([
                'message' => 'PA Code request denied. A valid, approved Referral Pre-Authorisation (RR) is required before issuing a Follow-up PA Code.',
            ], 403); 
        }

        // 2. Existing Bundle Check (from previous logic)
        // Check for existing primary PA logic here (preventing double bundling)
        
        $paType = $request->is_complication_pa ? PACode::TYPE_FFS_TOP_UP : PACode::TYPE_BUNDLE;

      

        // --- Core Policy Check: Prevent double bundle PA for the same episode ---
        $hasPrimaryPa = PACode::where('enrollee_id', $request->enrollee_id)
                                ->where('type', PACode::TYPE_BUNDLE)
                                ->where('status', 'APPROVED')
                                ->exists();

        if ($paType === PACode::TYPE_BUNDLE && $hasPrimaryPa) {
             return response()->json(['message' => 'A primary bundle PA is already approved for this enrollee episode.'], 409);
        }
        
        // Ensure FFS top-up PA is not requested without a primary PA
        if ($paType === PACode::TYPE_FFS_TOP_UP && !$hasPrimaryPa) {
             return response()->json(['message' => 'Cannot request FFS Top-Up PA without an existing primary Bundle PA.'], 403);
        }

        $paCode = PACode::create([
            'enrollee_id' => $request->enrollee_id,
            'facility_id' => $request->facility_id,
            'referral_id' => $request->referral_id,
            'code' => 'PA-' . strtoupper(bin2hex(random_bytes(3))), // Generates a unique code like PA-A5F8B9
            'type' => $paType,
            'status' => 'PENDING',
            'justification' => $request->justification,
            'requested_services' => $request->requested_items,
        ]);


        return response()->json($paCode, 201);
    }
    
    /**
     * Handles POST /v1/pas/pa-codes/{id}/approve: Approves a PA.
     */
    public function approve(PACode $paCode)
    {
        $paCode->update(['status' => 'APPROVED']);
        return response()->json(['message' => 'PA code approved.', 'pa_code' => $paCode]);
    }
}