<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LaboratoryDetailController extends Controller
{
    /**
     * Display a listing of laboratory details
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $testCategory = $request->input('test_category');

            $query = LaboratoryDetail::query();

            // Search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('test_name', 'like', "%{$search}%")
                      ->orWhere('test_code', 'like', "%{$search}%")
                      ->orWhere('test_category', 'like', "%{$search}%");
                });
            }

            // Test category filter
            if ($testCategory) {
                $query->where('test_category', $testCategory);
            }

            $labs = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $labs->items(),
                'total' => $labs->total(),
                'current_page' => $labs->currentPage(),
                'last_page' => $labs->lastPage(),
                'per_page' => $labs->perPage()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch laboratory tests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created laboratory detail
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'test_name' => 'required|string|max:255',
                'test_code' => 'nullable|string|max:255|unique:laboratory_details,test_code',
                'specimen_type' => 'nullable|string|max:255',
                'specimen_volume' => 'nullable|string|max:255',
                'collection_method' => 'nullable|string|max:255',
                'test_method' => 'nullable|string|max:255',
                'test_category' => 'nullable|string|max:255',
                'turnaround_time' => 'nullable|integer|min:0',
                'preparation_instructions' => 'nullable|string',
                'reference_range' => 'nullable|string',
                'reporting_unit' => 'nullable|string|max:255',
                'fasting_required' => 'boolean',
                'urgent_available' => 'boolean',
                'urgent_surcharge' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $lab = LaboratoryDetail::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Laboratory test created successfully',
                'data' => $lab
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create laboratory test',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified laboratory detail
     */
    public function show(LaboratoryDetail $laboratoryDetail): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $laboratoryDetail
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch laboratory test',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified laboratory detail
     */
    public function update(Request $request, LaboratoryDetail $laboratoryDetail): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'test_name' => 'required|string|max:255',
                'test_code' => 'nullable|string|max:255|unique:laboratory_details,test_code,' . $laboratoryDetail->id,
                'specimen_type' => 'nullable|string|max:255',
                'specimen_volume' => 'nullable|string|max:255',
                'collection_method' => 'nullable|string|max:255',
                'test_method' => 'nullable|string|max:255',
                'test_category' => 'nullable|string|max:255',
                'turnaround_time' => 'nullable|integer|min:0',
                'preparation_instructions' => 'nullable|string',
                'reference_range' => 'nullable|string',
                'reporting_unit' => 'nullable|string|max:255',
                'fasting_required' => 'boolean',
                'urgent_available' => 'boolean',
                'urgent_surcharge' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $laboratoryDetail->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Laboratory test updated successfully',
                'data' => $laboratoryDetail->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update laboratory test',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified laboratory detail
     */
    public function destroy(LaboratoryDetail $laboratoryDetail): JsonResponse
    {
        try {
            $laboratoryDetail->delete();

            return response()->json([
                'success' => true,
                'message' => 'Laboratory test deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete laboratory test',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get laboratory statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => LaboratoryDetail::count(),
                'fasting_required' => LaboratoryDetail::where('fasting_required', true)->count(),
                'urgent_available' => LaboratoryDetail::where('urgent_available', true)->count(),
                'recent_additions' => LaboratoryDetail::where('created_at', '>=', now()->subDays(30))->count(),
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
     * Get specimen types
     */
    public function specimenTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LaboratoryDetail::getSpecimenTypes()
        ]);
    }

    /**
     * Get test categories
     */
    public function testCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LaboratoryDetail::getTestCategories()
        ]);
    }
}

