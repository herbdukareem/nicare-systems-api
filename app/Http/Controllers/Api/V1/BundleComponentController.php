<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BundleComponent;
use App\Models\ServiceBundle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BundleComponentController extends Controller
{
    /**
     * Display a listing of bundle components
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BundleComponent::with(['serviceBundle', 'caseRecord']);

            // Filter by bundle
            if ($request->has('service_bundle_id') && $request->service_bundle_id) {
                $query->where('service_bundle_id', $request->service_bundle_id);
            }

            // Filter by item type
            if ($request->has('item_type') && $request->item_type) {
                $query->where('item_type', $request->item_type);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $components = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $components->items(),
                'total' => $components->total(),
                'current_page' => $components->currentPage(),
                'per_page' => $components->perPage(),
                'last_page' => $components->lastPage()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bundle components',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created bundle component
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_bundle_id' => 'required|exists:service_bundles,id',
                'case_record_id' => 'required|exists:case_records,id',
                'item_type' => 'required|string|max:50',
                'max_quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate component
            $exists = BundleComponent::where('service_bundle_id', $request->service_bundle_id)
                ->where('case_record_id', $request->case_record_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This component already exists in the bundle'
                ], 422);
            }

            $component = BundleComponent::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Bundle component created successfully',
                'data' => $component->load(['serviceBundle', 'caseRecord'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bundle component',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified bundle component
     */
    public function show(BundleComponent $bundleComponent): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $bundleComponent->load(['serviceBundle', 'caseRecord'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bundle component',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified bundle component
     */
    public function update(Request $request, BundleComponent $bundleComponent): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_bundle_id' => 'required|exists:service_bundles,id',
                'case_record_id' => 'required|exists:case_records,id',
                'item_type' => 'required|string|max:50',
                'max_quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bundleComponent->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Bundle component updated successfully',
                'data' => $bundleComponent->load(['serviceBundle', 'caseRecord'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bundle component',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified bundle component
     */
    public function destroy(BundleComponent $bundleComponent): JsonResponse
    {
        try {
            $bundleComponent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bundle component deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bundle component',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create bundle components
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_bundle_id' => 'required|exists:service_bundles,id',
                'components' => 'required|array|min:1',
                'components.*.case_record_id' => 'required|exists:case_records,id',
                'components.*.item_type' => 'required|string|max:50',
                'components.*.max_quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $created = [];
            foreach ($request->components as $componentData) {
                // Check for duplicate
                $exists = BundleComponent::where('service_bundle_id', $request->service_bundle_id)
                    ->where('case_record_id', $componentData['case_record_id'])
                    ->exists();

                if (!$exists) {
                    $component = BundleComponent::create([
                        'service_bundle_id' => $request->service_bundle_id,
                        'case_record_id' => $componentData['case_record_id'],
                        'item_type' => $componentData['item_type'],
                        'max_quantity' => $componentData['max_quantity']
                    ]);
                    $created[] = $component;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($created) . ' components created successfully',
                'data' => $created
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk create components',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bundle component statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $total = BundleComponent::count();
            $byType = BundleComponent::select('item_type', DB::raw('count(*) as count'))
                ->groupBy('item_type')
                ->pluck('count', 'item_type');

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_type' => $byType
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

