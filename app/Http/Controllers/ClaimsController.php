<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\ClaimDiagnosis;
use App\Models\ClaimTreatment;
use App\Models\ClaimAuditLog;
use App\Models\PACode;
use App\Models\Facility;
use App\Models\Referral;
use App\Models\CaseRecord;
use App\Models\TariffItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClaimsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of claims based on user role
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Claim::with(['facility', 'paCode', 'submittedBy', 'diagnoses', 'treatments']);

        // Filter based on user role
        if ($user->hasRole('desk_officer')) {
            // Desk officers see claims from their facility or all if admin
            $query->where('submitted_by', $user->id);
        } elseif ($user->hasRole('doctor')) {
            // Doctors see claims pending their review or already reviewed by them
            $query->whereIn('status', ['doctor_review', 'doctor_approved', 'doctor_rejected'])
                  ->orWhere('doctor_reviewed_by', $user->id);
        } elseif ($user->hasRole('pharmacist')) {
            // Pharmacists see claims with medications pending their review
            $query->whereIn('status', ['pharmacist_review', 'pharmacist_approved', 'pharmacist_rejected'])
                  ->orWhere('pharmacist_reviewed_by', $user->id);
        } elseif ($user->hasRole('claim_reviewer')) {
            // Claim reviewers see claims pending review
            $query->whereIn('status', ['claim_review', 'claim_confirmed', 'claim_approved', 'claim_rejected']);
        } elseif ($user->hasRole('claim_confirmer')) {
            // Claim confirmers see claims pending confirmation
            $query->whereIn('status', ['claim_review', 'claim_confirmed', 'claim_approved', 'claim_rejected']);
        } elseif ($user->hasRole('claim_approver')) {
            // Claim approvers see claims pending final approval
            $query->whereIn('status', ['claim_confirmed', 'claim_approved', 'claim_rejected', 'paid']);
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('nicare_number', 'like', "%{$search}%")
                  ->orWhere('enrollee_name', 'like', "%{$search}%")
                  ->orWhere('pa_code', 'like', "%{$search}%");
            });
        }

        $claims = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims,
            'user_role' => $user->roles->first()->name ?? 'unknown'
        ]);
    }

    /**
     * Store a newly created claim
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nicare_number' => 'required|string',
            'enrollee_name' => 'required|string',
            'gender' => 'required|in:Male,Female',
            'facility_id' => 'required|exists:facilities,id',
            'pa_code' => 'nullable|string|exists:pa_codes,pa_code',
            'pa_request_type' => 'required|in:Initial,Follow-up,Amendment,Renewal',
            'priority' => 'required|in:Routine,Urgent,Emergency',
            'attending_physician_name' => 'required|string',
            'attending_physician_license' => 'nullable|string',
            'attending_physician_specialization' => 'nullable|string',
            'diagnoses' => 'required|array|min:1',
            'diagnoses.*.type' => 'required|in:primary,secondary',
            'diagnoses.*.icd_10_code' => 'required|string',
            'diagnoses.*.icd_10_description' => 'required|string',
            'diagnoses.*.illness_description' => 'nullable|string',
            'treatments' => 'required|array|min:1',
            'treatments.*.service_date' => 'required|date',
            'treatments.*.service_type' => 'required|in:professional_service,hospital_stay,medication,consumable,laboratory,radiology,other',
            'treatments.*.service_code' => 'required|string',
            'treatments.*.service_description' => 'required|string',
            'treatments.*.quantity' => 'required|integer|min:1',
            'treatments.*.unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get facility information
            $facility = Facility::findOrFail($request->facility_id);

            // Get PA Code information if provided
            $paCodeRecord = null;
            if ($request->pa_code) {
                $paCodeRecord = PACode::where('pa_code', $request->pa_code)->first();
            }

            // Create the claim
            $claim = Claim::create([
                'nicare_number' => $request->nicare_number,
                'enrollee_name' => $request->enrollee_name,
                'gender' => $request->gender,
                'plan' => $request->plan,
                'marital_status' => $request->marital_status,
                'phone_main' => $request->phone_main,
                'phone_during_care' => $request->phone_during_care,
                'email_main' => $request->email_main,
                'email_during_care' => $request->email_during_care,
                'referral_date' => $request->referral_date,
                'facility_id' => $facility->id,
                'facility_name' => $facility->facility_name,
                'facility_nicare_code' => $facility->nicare_code,
                'pa_code_id' => $paCodeRecord?->id,
                'pa_code' => $request->pa_code,
                'pa_request_type' => $request->pa_request_type,
                'priority' => $request->priority,
                'pa_validity_start' => $paCodeRecord?->issued_at,
                'pa_validity_end' => $paCodeRecord?->expires_at,
                'attending_physician_name' => $request->attending_physician_name,
                'attending_physician_license' => $request->attending_physician_license,
                'attending_physician_specialization' => $request->attending_physician_specialization,
                'status' => 'draft',
                'submitted_by' => Auth::id(),
            ]);

            // Create diagnoses
            foreach ($request->diagnoses as $diagnosisData) {
                ClaimDiagnosis::create([
                    'claim_id' => $claim->id,
                    'type' => $diagnosisData['type'],
                    'icd_10_code' => $diagnosisData['icd_10_code'],
                    'icd_10_description' => $diagnosisData['icd_10_description'],
                    'illness_description' => $diagnosisData['illness_description'] ?? null,
                ]);
            }

            // Create treatments
            foreach ($request->treatments as $treatmentData) {
                ClaimTreatment::create([
                    'claim_id' => $claim->id,
                    'service_date' => $treatmentData['service_date'],
                    'service_type' => $treatmentData['service_type'],
                    'service_code' => $treatmentData['service_code'],
                    'service_description' => $treatmentData['service_description'],
                    'quantity' => $treatmentData['quantity'],
                    'unit_price' => $treatmentData['unit_price'],
                ]);
            }

            // Calculate total amounts
            $claim->calculateTotalAmounts();

            // Log the creation
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'created',
                Auth::user(),
                null,
                null,
                'draft',
                'Claim created',
                'New claim created by ' . Auth::user()->name
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Claim created successfully',
                'data' => $claim->load(['diagnoses', 'treatments'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified claim
     */
    public function show(Claim $claim)
    {
        $claim->load([
            'facility',
            'paCode',
            'submittedBy',
            'diagnoses.doctorValidatedBy',
            'treatments.doctorValidatedBy',
            'treatments.pharmacistValidatedBy',
            'treatments.attachments',
            'attachments.uploadedBy',
            'attachments.validatedBy',
            'auditLogs.user'
        ]);

        return response()->json([
            'success' => true,
            'data' => $claim
        ]);
    }

    /**
     * Update the specified claim (only in draft status)
     */
    public function update(Request $request, Claim $claim)
    {
        if (!$claim->canBeEditedBy(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot edit this claim'
            ], 403);
        }

        // Validation logic similar to store method
        $validator = Validator::make($request->all(), [
            'nicare_number' => 'required|string',
            'enrollee_name' => 'required|string',
            'gender' => 'required|in:Male,Female',
            'attending_physician_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $oldData = $claim->toArray();
            $claim->update($request->only([
                'nicare_number', 'enrollee_name', 'gender', 'plan', 'marital_status',
                'phone_main', 'phone_during_care', 'email_main', 'email_during_care',
                'referral_date', 'attending_physician_name', 'attending_physician_license',
                'attending_physician_specialization'
            ]));

            // Log the update
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'updated',
                Auth::user(),
                'claim_data',
                json_encode($oldData),
                json_encode($claim->toArray()),
                'Claim updated',
                'Claim data updated by ' . Auth::user()->name
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Claim updated successfully',
                'data' => $claim->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a claim for review
     */
    public function submit(Claim $claim)
    {
        if (!$claim->canBeSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'Claim cannot be submitted. Ensure all required fields are completed.'
            ], 422);
        }

        if (!$claim->canBeEditedBy(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot submit this claim'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $claim->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Determine next status based on treatments
            $hasMedications = $claim->treatments()->where('service_type', 'medication')->exists();
            $nextStatus = $hasMedications ? 'doctor_review' : 'doctor_review';

            $claim->update(['status' => $nextStatus]);

            // Log the submission
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'submitted',
                Auth::user(),
                'status',
                'draft',
                $nextStatus,
                'Claim submitted for review',
                'Claim submitted by ' . Auth::user()->name
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Claim submitted successfully',
                'data' => $claim
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available services/tariff items for a referral or PA code
     * This restricts desk officers to only select services defined for the case
     */
    public function getServicesForReferralOrPACode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'referral_id' => 'nullable|exists:referrals,id',
            'pa_code_id' => 'nullable|exists:p_a_codes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $services = [];
            $caseId = null;

            // Get case ID from referral
            if ($request->referral_id) {
                $referral = Referral::find($request->referral_id);
                if (!$referral) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Referral not found'
                    ], 404);
                }
                $caseId = $referral->case_id;
            }

            // Get case ID from PA code
            if ($request->pa_code_id) {
                $paCode = PACode::find($request->pa_code_id);
                if (!$paCode) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PA Code not found'
                    ], 404);
                }
                // Get the referral from PA code
                $referral = $paCode->referral;
                if ($referral) {
                    $caseId = $referral->case_id;
                }
            }

            // Get tariff items for the case
            if ($caseId) {
                $services = TariffItem::where('case_id', $caseId)
                    ->where('status', true)
                    ->select('id', 'case_id', 'tariff_item', 'price', 'service_type_id')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $services,
                'case_id' => $caseId,
                'message' => $services->isEmpty() ? 'No services defined for this case' : 'Services retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve services',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
