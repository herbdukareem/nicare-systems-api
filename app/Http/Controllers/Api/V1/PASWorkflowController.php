<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\PACode;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Service;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PASWorkflowController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Create referral from workflow data
     */
    public function createReferral(Request $request): JsonResponse
    {
        try {
            // Handle JSON string services from FormData
            $requestData = $request->all();
            if (isset($requestData['services']) && is_string($requestData['services'])) {
                $requestData['services'] = json_decode($requestData['services'], true);
            }

            $validator = Validator::make($requestData, [
                'facility_id' => 'required|exists:facilities,id',
                'enrollee_id' => 'required|exists:enrollees,id',
                'request_type' => 'required|in:referral,pa_code',
                'services' => 'required|array|min:1',
                'services.*.id' => 'required|exists:services,id',
                'receiving_facility_id' => 'required|exists:facilities,id',
                'severity_level' => 'required|in:emergency,urgent,routine',
                'presenting_complaints' => 'nullable|string',
                'reasons_for_referral' => 'required|string',
                'preliminary_diagnosis' => 'nullable|string',
                'personnel_full_name' => 'nullable|string|max:255',
                'personnel_phone' => 'nullable|string|max:20',
                'contact_full_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'contact_email' => 'nullable|email|max:255',
                'enrollee_id_card' => 'nullable|file|mimes:jpeg,png,pdf|max:5120',
                'referral_letter' => 'nullable|file|mimes:jpeg,png,pdf,doc,docx|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get enrollee and facilities
            $enrollee = Enrollee::findOrFail($requestData['enrollee_id']);
            $referringFacility = Facility::findOrFail($requestData['facility_id']);
            $receivingFacility = Facility::findOrFail($requestData['receiving_facility_id']);

            // Prepare referral data
            $referralData = [
                // Facility IDs (Foreign Keys)
                'referring_facility_id' => $requestData['facility_id'],
                'receiving_facility_id' => $requestData['receiving_facility_id'],
                'enrollee_id' => $requestData['enrollee_id'],

                // Referring Provider
                'referring_facility_name' => $referringFacility->name,
                'referring_nicare_code' => $referringFacility->hcp_code,
                'referring_address' => $referringFacility->address ?? 'Address not provided',
                'referring_phone' => $referringFacility->phone ?? 'Phone not provided',
                'referring_email' => $referringFacility->email,

                // Contact Person
                'contact_full_name' => $requestData['contact_full_name'] ?? 'Contact not provided',
                'contact_phone' => $requestData['contact_phone'] ?? 'Phone not provided',
                'contact_email' => $requestData['contact_email'] ?? null,

                // Receiving Provider
                'receiving_facility_name' => $receivingFacility->name,
                'receiving_nicare_code' => $receivingFacility->hcp_code,
                'receiving_address' => $receivingFacility->address ?? 'Address not provided',
                'receiving_phone' => $receivingFacility->phone ?? 'Phone not provided',
                'receiving_email' => $receivingFacility->email,

                // Patient/Enrollee - Use correct field mappings
                'nicare_number' => $enrollee->enrollee_id ?? 'ID not available',
                'enrollee_full_name' => trim(($enrollee->first_name ?? '') . ' ' . ($enrollee->middle_name ?? '') . ' ' . ($enrollee->last_name ?? '')) ?: 'Name not available',
                'gender' => $enrollee->sex == 1 ? 'Male' : ($enrollee->sex == 2 ? 'Female' : 'Not specified'),
                'age' => $enrollee->age ?? 0,
                'enrollee_phone_main' => $enrollee->phone ?? 'Phone not provided',
                'referral_date' => now()->format('Y-m-d'),

                // Clinical Justification
                'presenting_complaints' => $requestData['presenting_complaints'] ?? null,
                'reasons_for_referral' => $requestData['reasons_for_referral'],
                'preliminary_diagnosis' => $requestData['preliminary_diagnosis'] ?? null,

                // Severity Level
                'severity_level' => $requestData['severity_level'],

                // Referring Personnel
                'personnel_full_name' => $requestData['personnel_full_name'] ?? null,
                'personnel_phone' => $requestData['personnel_phone'] ?? null,

                // Additional data
                'total_cost' => $requestData['total_cost'] ?? 0,
                'services_requested' => json_encode($requestData['services']),
            ];

            // Create referral
            $referral = Referral::create($referralData);

            // Generate referral code
            $referralCode = $referral->generateReferralCode();
            $referral->update(['referral_code' => $referralCode]);

            // Handle file uploads
            $uploadResults = [];

            if ($request->hasFile('enrollee_id_card')) {
                $result = $this->fileUploadService->uploadPASDocument(
                    $request->file('enrollee_id_card'),
                    $referralCode,
                    'enrollee_id_card'
                );

                if ($result['success']) {
                    $referral->update(['enrollee_id_card_path' => $result['path']]);
                    $uploadResults['enrollee_id_card'] = $result;
                }
            }

            if ($request->hasFile('referral_letter')) {
                $result = $this->fileUploadService->uploadPASDocument(
                    $request->file('referral_letter'),
                    $referralCode,
                    'referral_letter'
                );

                if ($result['success']) {
                    $referral->update(['referral_letter_path' => $result['path']]);
                    $uploadResults['referral_letter'] = $result;
                }
            }

            // Auto-approve if it's for PA code generation
            if ($requestData['request_type'] === 'pa_code') {
                $referral->approve(Auth::user(), 'Auto-approved for PA code generation');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Referral created successfully',
                'data' => [
                    'referral' => $referral->fresh(),
                    'uploads' => $uploadResults
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PA code from workflow data
     */
    public function generatePACode(Request $request): JsonResponse
    {
        try {
            // Handle JSON string services from FormData
            $requestData = $request->all();
            if (isset($requestData['services']) && is_string($requestData['services'])) {
                $requestData['services'] = json_decode($requestData['services'], true);
            }

            $validator = Validator::make($requestData, [
                'facility_id' => 'required|exists:facilities,id',
                'enrollee_id' => 'required|exists:enrollees,id',
                'services' => 'required|array|min:1',
                'services.*.id' => 'required|exists:services,id',
                'severity_level' => 'required|in:emergency,urgent,routine',
                'presenting_complaints' => 'nullable|string',
                'reasons_for_referral' => 'required|string',
                'preliminary_diagnosis' => 'nullable|string',
                'personnel_full_name' => 'nullable|string|max:255',
                'personnel_phone' => 'nullable|string|max:20',
                'contact_full_name' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'contact_email' => 'nullable|email|max:255',
                'validity_days' => 'integer|min:1|max:365',
                'max_usage' => 'integer|min:1|max:10',
                'issuer_comments' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // First create a referral (auto-approved)
            $referralResponse = $this->createReferral($request);
            $referralData = json_decode($referralResponse->getContent(), true);

            if (!$referralData['success']) {
                DB::rollBack();
                return $referralResponse;
            }

            $referral = Referral::find($referralData['data']['referral']['id']);

            // Generate PA code and UTN
            $paCode = PACode::generatePACode();
            $utn = PACode::generateUTN();

            // Get enrollee and facility
            $enrollee = Enrollee::findOrFail($requestData['enrollee_id']);
            $facility = Facility::findOrFail($requestData['facility_id']);

            // Prepare service description
            $services = Service::whereIn('id', collect($requestData['services'])->pluck('id'))->get();
            $serviceDescription = $services->pluck('service_description')->join(', ');

            // Create PA code record
            $paCodeRecord = PACode::create([
                'pa_code' => $paCode,
                'utn' => $utn,
                'referral_id' => $referral->id,
                'nicare_number' => $enrollee->enrollee_id ?? 'ID not available',
                'enrollee_name' => trim(($enrollee->first_name ?? '') . ' ' . ($enrollee->middle_name ?? '') . ' ' . ($enrollee->last_name ?? '')) ?: 'Name not available',
                'facility_name' => $facility->name,
                'facility_nicare_code' => $facility->hcp_code,
                'service_type' => 'Multiple Services',
                'service_description' => $serviceDescription,
                'approved_amount' => $requestData['total_cost'] ?? 0,
                'conditions' => $requestData['preliminary_diagnosis'],
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addDays($requestData['validity_days'] ?? 30),
                'usage_count' => 0,
                'max_usage' => $requestData['max_usage'] ?? 1,
                'issued_by' => Auth::id(),
                'issuer_comments' => $requestData['issuer_comments'] ?? 'Generated from workflow'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PA code generated successfully',
                'data' => $paCodeRecord->fresh(['referral', 'issuedBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PA code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
