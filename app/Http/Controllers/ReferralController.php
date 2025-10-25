<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of referrals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Referral::with(['referringFacility', 'receivingFacility', 'enrollee', 'approvedBy', 'deniedBy']);

            // Apply filters
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('severity_level') && !empty($request->severity_level)) {
                $query->where('severity_level', $request->severity_level);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('referral_code', 'like', "%{$search}%")
                      ->orWhere('enrollee_full_name', 'like', "%{$search}%")
                      ->orWhere('nicare_number', 'like', "%{$search}%")
                      ->orWhere('referring_facility_name', 'like', "%{$search}%")
                      ->orWhere('receiving_facility_name', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $referrals = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $referrals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referrals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created referral
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                // Referring Provider
                'referring_facility_name' => 'required|string|max:255',
                'referring_nicare_code' => 'required|string|max:50',
                'referring_address' => 'required|string',
                'referring_phone' => 'required|string|max:20',
                'referring_email' => 'nullable|email|max:255',

                // Contact Person
                'contact_full_name' => 'required|string|max:255',
                'contact_phone' => 'required|string|max:20',
                'contact_email' => 'nullable|email|max:255',

                // Receiving Provider
                'receiving_facility_name' => 'required|string|max:255',
                'receiving_nicare_code' => 'required|string|max:50',
                'receiving_address' => 'required|string',
                'receiving_phone' => 'required|string|max:20',
                'receiving_email' => 'nullable|email|max:255',

                // Patient/Enrollee
                'nicare_number' => 'required|string|max:50',
                'enrollee_full_name' => 'required|string|max:255',
                'gender' => 'required|in:Male,Female',
                'age' => 'required|integer|min:0|max:150',
                'enrollee_phone_main' => 'required|string|max:20',
                'referral_date' => 'required|date',

                // Clinical Justification
                'presenting_complaints' => 'required|string',
                'reasons_for_referral' => 'required|string',
                'preliminary_diagnosis' => 'required|string',

                // Severity Level
                'severity_level' => 'required|in:emergency,urgent,routine',

                // Referring Personnel
                'personnel_full_name' => 'required|string|max:255',
                'personnel_phone' => 'required|string|max:20',

                // File uploads
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

            // Create referral record
            $referralData = $request->except(['enrollee_id_card', 'referral_letter']);
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
     * Display the specified referral
     */
    public function show(Referral $referral): JsonResponse
    {
        try {
            $referral->load(['approvedBy', 'deniedBy', 'paCodes']);

            // Add file URLs if files exist
            if ($referral->enrollee_id_card_path) {
                $referral->enrollee_id_card_url = $this->fileUploadService->getFileUrl($referral->enrollee_id_card_path);
            }

            if ($referral->referral_letter_path) {
                $referral->referral_letter_url = $this->fileUploadService->getFileUrl($referral->referral_letter_path);
            }

            return response()->json([
                'success' => true,
                'data' => $referral
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a referral
     */
    public function approve(Request $request, Referral $referral): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($referral->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending referrals can be approved'
                ], 400);
            }

            $referral->approve(Auth::user(), $request->comments);

            return response()->json([
                'success' => true,
                'message' => 'Referral approved successfully',
                'data' => $referral->fresh(['approvedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deny a referral
     */
    public function deny(Request $request, Referral $referral): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($referral->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending referrals can be denied'
                ], 400);
            }

            $referral->deny(Auth::user(), $request->comments);

            return response()->json([
                'success' => true,
                'message' => 'Referral denied successfully',
                'data' => $referral->fresh(['deniedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deny referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending referrals by facility
     * For modify referral flow - shows referrals FROM the selected facility
     */
    public function getPendingByFacility($facilityId): JsonResponse
    {
        try {
            $referrals = Referral::where('status', 'pending')
                ->where(function($query) use ($facilityId) {
                    // For modify referral, we want referrals FROM this facility (referring_facility_id)
                    // Check if facilityId is numeric (ID) or string (NiCare code)
                    if (is_numeric($facilityId)) {
                        $query->where('referring_facility_id', $facilityId);
                    } else {
                        $query->where('referring_nicare_code', $facilityId);
                    }
                })
                ->with(['referringFacility', 'receivingFacility', 'enrollee', 'approvedBy', 'deniedBy'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($referral) {
                    return [
                        'id' => $referral->id,
                        'referral_code' => $referral->referral_code,
                        'patient_name' => $referral->enrollee_full_name,
                        'current_service' => $referral->service_description ?? 'General Consultation',
                        'severity_level' => $referral->severity_level,
                        'created_at' => $referral->created_at,
                        'nicare_number' => $referral->nicare_number,
                        'presenting_complaints' => $referral->presenting_complaints,
                        'preliminary_diagnosis' => $referral->preliminary_diagnosis,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $referrals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending referrals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modify referral service
     */
    public function modifyService(Request $request, Referral $referral): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_service_id' => 'required|integer|exists:services,id',
                'modification_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($referral->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending referrals can be modified'
                ], 400);
            }

            DB::beginTransaction();

            // Store the old service information for history tracking
            $oldServiceData = [
                'old_service_id' => $referral->service_id,
                'old_service_description' => $referral->service_description,
                'modified_at' => now(),
                'modified_by' => Auth::id(),
                'modification_reason' => $request->modification_reason
            ];

            // Update referral with new service
            $referral->update([
                'service_id' => $request->new_service_id,
                'service_description' => $request->service_description ?? 'Updated Service',
                'modification_history' => json_encode(array_merge(
                    json_decode($referral->modification_history ?? '[]', true),
                    [$oldServiceData]
                ))
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Referral service modified successfully',
                'data' => $referral->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to modify referral service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get referral statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_referrals' => Referral::count(),
                'pending_requests' => Referral::where('status', 'pending')->count(),
                'approved_referrals' => Referral::where('status', 'approved')->count(),
                'denied_referrals' => Referral::where('status', 'denied')->count(),
                'emergency_cases' => Referral::where('severity_level', 'emergency')->count(),
                'urgent_cases' => Referral::where('severity_level', 'urgent')->count(),
                'routine_cases' => Referral::where('severity_level', 'routine')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
