<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalServiceDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProfessionalServiceDetailController extends Controller
{
    /**
     * Display a listing of professional service details
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $specialty = $request->input('specialty');

            $query = ProfessionalServiceDetail::query();

            // Search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('service_name', 'like', "%{$search}%")
                      ->orWhere('service_code', 'like', "%{$search}%")
                      ->orWhere('specialty', 'like', "%{$search}%");
                });
            }

            // Specialty filter
            if ($specialty) {
                $query->where('specialty', $specialty);
            }

            $services = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $services->items(),
                'total' => $services->total(),
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch professional services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created professional service detail
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_name' => 'required|string|max:255',
                'service_code' => 'nullable|string|max:255|unique:professional_service_details,service_code',
                'specialty' => 'nullable|string|max:255',
                'duration_minutes' => 'nullable|integer|min:0',
                'provider_type' => 'nullable|string|max:255',
                'equipment_needed' => 'nullable|string',
                'procedure_description' => 'nullable|string',
                'indications' => 'nullable|string',
                'contraindications' => 'nullable|string',
                'complications' => 'nullable|string',
                'pre_procedure_requirements' => 'nullable|string',
                'post_procedure_care' => 'nullable|string',
                'anesthesia_required' => 'boolean',
                'anesthesia_type' => 'nullable|string|max:255',
                'admission_required' => 'boolean',
                'recovery_time_hours' => 'nullable|integer|min:0',
                'follow_up_required' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $service = ProfessionalServiceDetail::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Professional service created successfully',
                'data' => $service
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create professional service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified professional service detail
     */
    public function show(ProfessionalServiceDetail $professionalServiceDetail): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $professionalServiceDetail
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch professional service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified professional service detail
     */
    public function update(Request $request, ProfessionalServiceDetail $professionalServiceDetail): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_name' => 'required|string|max:255',
                'service_code' => 'nullable|string|max:255|unique:professional_service_details,service_code,' . $professionalServiceDetail->id,
                'specialty' => 'nullable|string|max:255',
                'duration_minutes' => 'nullable|integer|min:0',
                'provider_type' => 'nullable|string|max:255',
                'equipment_needed' => 'nullable|string',
                'procedure_description' => 'nullable|string',
                'indications' => 'nullable|string',
                'contraindications' => 'nullable|string',
                'complications' => 'nullable|string',
                'pre_procedure_requirements' => 'nullable|string',
                'post_procedure_care' => 'nullable|string',
                'anesthesia_required' => 'boolean',
                'anesthesia_type' => 'nullable|string|max:255',
                'admission_required' => 'boolean',
                'recovery_time_hours' => 'nullable|integer|min:0',
                'follow_up_required' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $professionalServiceDetail->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Professional service updated successfully',
                'data' => $professionalServiceDetail->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update professional service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified professional service detail
     */
    public function destroy(ProfessionalServiceDetail $professionalServiceDetail): JsonResponse
    {
        try {
            $professionalServiceDetail->delete();

            return response()->json([
                'success' => true,
                'message' => 'Professional service deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete professional service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get professional service statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => ProfessionalServiceDetail::count(),
                'anesthesia_required' => ProfessionalServiceDetail::where('anesthesia_required', true)->count(),
                'admission_required' => ProfessionalServiceDetail::where('admission_required', true)->count(),
                'recent_additions' => ProfessionalServiceDetail::where('created_at', '>=', now()->subDays(30))->count(),
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

    /**
     * Get specialties
     */
    public function specialties(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProfessionalServiceDetail::getSpecialties()
        ]);
    }

    /**
     * Get provider types
     */
    public function providerTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProfessionalServiceDetail::getProviderTypes()
        ]);
    }

    /**
     * Get anesthesia types
     */
    public function anesthesiaTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProfessionalServiceDetail::getAnesthesiaTypes()
        ]);
    }
}

