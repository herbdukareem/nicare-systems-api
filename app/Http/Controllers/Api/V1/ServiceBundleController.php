<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceBundle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ServiceBundleController extends Controller
{
    /**
     * Display a listing of service bundles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ServiceBundle::with(['caseRecord', 'components.caseRecord']);

            // Apply search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('diagnosis_icd10', 'like', "%{$search}%")
                      ->orWhereHas('caseRecord', function ($cq) use ($search) {
                          $cq->where('case_name', 'like', "%{$search}%")
                             ->orWhere('nicare_code', 'like', "%{$search}%");
                      });
                });
            }

            // Apply status filter
            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', (bool) $request->is_active);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $bundles = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bundles->items(),
                'total' => $bundles->total(),
                'current_page' => $bundles->currentPage(),
                'per_page' => $bundles->perPage(),
                'last_page' => $bundles->lastPage()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service bundles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created service bundle
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_record_id' => 'required|exists:case_records,id',
                'description' => 'required|string',
                'fixed_price' => 'required|numeric|min:0',
                'diagnosis_icd10' => 'nullable|string|max:20',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bundle = ServiceBundle::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Service bundle created successfully',
                'data' => $bundle->load(['caseRecord', 'components.caseRecord'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service bundle
     */
    public function show(ServiceBundle $serviceBundle): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $serviceBundle->load(['caseRecord', 'components.caseRecord'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified service bundle
     */
    public function update(Request $request, ServiceBundle $serviceBundle): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_record_id' => 'required|exists:case_records,id',
                'description' => 'required|string',
                'fixed_price' => 'required|numeric|min:0',
                'diagnosis_icd10' => 'nullable|string|max:20',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $serviceBundle->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Service bundle updated successfully',
                'data' => $serviceBundle->load(['caseRecord', 'components.caseRecord'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified service bundle
     */
    public function destroy(ServiceBundle $serviceBundle): JsonResponse
    {
        try {
            // Check if bundle has components
            if ($serviceBundle->components()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete bundle with components. Please remove components first.'
                ], 422);
            }

            $serviceBundle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service bundle deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service bundle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service bundle statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $total = ServiceBundle::count();
            $active = ServiceBundle::where('is_active', true)->count();
            $withComponents = ServiceBundle::has('components')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'with_components' => $withComponents,
                    'inactive' => $total - $active
                ]
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

