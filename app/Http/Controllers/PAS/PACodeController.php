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
     * Get all PA codes with filters.
     */
    public function index(Request $request)
    {
        $query = PACode::with(['enrollee', 'facility', 'referral', 'serviceBundle']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $paCodes = $query->latest()->get();

        return response()->json(['data' => $paCodes]);
    }

    /**
     * Get a single PA code with all relationships.
     */
    public function show(PACode $paCode)
    {
        $paCode->load(['enrollee', 'facility', 'referral', 'serviceBundle']);

        return response()->json(['data' => $paCode]);
    }

    /**
     * Handles POST /v1/pas/pa-codes: Creates a new PA request.
     */
    public function store(Request $request) // Use RequestPACodeRequest for real validation
    {
        $request->validate([
            'enrollee_id' => 'required|exists:enrollees,id',
            'facility_id' => 'required|exists:facilities,id',
            'is_complication_pa' => 'boolean',
            'requested_items' => 'nullable|array',
            'justification' => 'required|string|max:1000',
            'referral_id' => 'required|exists:referrals,id',
            'service_selection_type' => ['required', 'in:bundle,direct'],
            'service_bundle_id' => ['nullable', 'required_if:service_selection_type,bundle', 'exists:service_bundles,id'],
            'case_record_ids' => ['nullable', 'required_if:service_selection_type,direct', 'array'],
            'case_record_ids.*' => ['exists:case_records,id'],
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
            'requested_services' => $request->requested_items ?? [],
            'service_selection_type' => $request->service_selection_type,
            'service_bundle_id' => $request->service_bundle_id,
            'case_record_ids' => $request->case_record_ids,
        ]);

        $paCode->load(['enrollee', 'facility', 'referral', 'serviceBundle']);

        return response()->json(['data' => $paCode], 201);
    }
    
    /**
     * Handles POST /v1/pas/pa-codes/{id}/approve: Approves a PA.
     */
    public function approve(PACode $paCode)
    {
        $paCode->update([
            'status' => 'APPROVED',
            'approval_date' => now(),
        ]);

        $paCode->load(['enrollee', 'facility', 'referral', 'serviceBundle']);

        return response()->json(['message' => 'PA code approved successfully.', 'data' => $paCode]);
    }

    /**
     * Handles POST /v1/pas/pa-codes/{id}/reject: Rejects a PA.
     */
    public function reject(Request $request, PACode $paCode)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $paCode->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
        ]);

        $paCode->load(['enrollee', 'facility', 'referral', 'serviceBundle']);

        return response()->json(['message' => 'PA code rejected successfully.', 'data' => $paCode]);
    }
}