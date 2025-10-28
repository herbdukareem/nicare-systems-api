<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TariffItem;
use App\Models\CaseCategory;
use App\Models\ServiceType;
use App\Models\CaseType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TariffItemsImport;
use App\Exports\TariffItemsExport;

class TariffItemController extends Controller
{
    /**
     * Display a listing of tariff items
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TariffItem::with(['caseCategory', 'serviceType', 'caseType']);

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tariff_item', 'like', "%{$search}%")
                      ->orWhereHas('caseCategory', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('serviceType', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Case Category filter
            if ($request->has('case_category_id') && !empty($request->case_category_id)) {
                $query->where('case_id', $request->case_category_id);
            }

            // Service Type filter
            if ($request->has('service_type_id') && !empty($request->service_type_id)) {
                $query->where('service_type_id', $request->service_type_id);
            }

            // Case Type filter
            if ($request->has('case_type_id') && !empty($request->case_type_id)) {
                $query->where('case_type_id', $request->case_type_id);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $tariffItems = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tariffItems
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tariff items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created tariff item
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_id' => 'required|exists:case_categories,id',
                'service_type_id' => 'required|exists:service_types,id',
                'tariff_item' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'case_type_id' => 'required|exists:case_types,id',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tariffItem = TariffItem::create([
                'case_id' => $request->case_id,
                'service_type_id' => $request->service_type_id,
                'tariff_item' => $request->tariff_item,
                'price' => $request->price,
                'case_type_id' => $request->case_type_id,
                'status' => $request->get('status', true)
            ]);

            $tariffItem->load(['caseCategory', 'serviceType', 'caseType']);

            return response()->json([
                'success' => true,
                'message' => 'Tariff item created successfully',
                'data' => $tariffItem
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tariff item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified tariff item
     */
    public function show(TariffItem $tariffItem): JsonResponse
    {
        try {
            $tariffItem->load(['caseCategory', 'serviceType', 'caseType']);

            return response()->json([
                'success' => true,
                'data' => $tariffItem
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tariff item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified tariff item
     */
    public function update(Request $request, TariffItem $tariffItem): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_id' => 'sometimes|required|exists:case_categories,id',
                'service_type_id' => 'sometimes|required|exists:service_types,id',
                'tariff_item' => 'sometimes|required|string|max:255',
                'price' => 'sometimes|required|numeric|min:0',
                'case_type_id' => 'sometimes|required|exists:case_types,id',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tariffItem->update($request->only([
                'case_id',
                'service_type_id',
                'tariff_item',
                'price',
                'case_type_id',
                'status'
            ]));

            $tariffItem->load(['caseCategory', 'serviceType', 'caseType']);

            return response()->json([
                'success' => true,
                'message' => 'Tariff item updated successfully',
                'data' => $tariffItem
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tariff item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified tariff item
     */
    public function destroy(TariffItem $tariffItem): JsonResponse
    {
        try {
            $tariffItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tariff item deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tariff item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import tariff items from Excel file
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $import = new TariffItemsImport();
            
            Excel::import($import, $file);

            return response()->json([
                'success' => true,
                'message' => 'Tariff items imported successfully',
                'data' => [
                    'imported_count' => $import->getImportedCount(),
                    'errors' => $import->getErrors()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import tariff items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export tariff items to Excel file
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['search', 'status', 'case_category_id', 'service_type_id', 'case_type_id']);
            $filename = 'tariff_items_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            return Excel::download(new TariffItemsExport($filters), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export tariff items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download template for importing tariff items
     */
    public function downloadTemplate()
    {
        try {
            $filename = 'tariff_items_template_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
            return Excel::download(new TariffItemsExport([], true), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tariff items statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalItems = TariffItem::count();
            $activeItems = TariffItem::where('status', true)->count();
            $inactiveItems = TariffItem::where('status', false)->count();
            $recentAdditions = TariffItem::where('created_at', '>=', now()->subDays(30))->count();

            // Group by case type
            $byCaseType = TariffItem::with('caseType')
                ->get()
                ->groupBy('case_type_id')
                ->map(function ($items, $caseTypeId) {
                    $caseType = $items->first()->caseType;
                    return [
                        'case_type' => $caseType ? $caseType->name : 'Unknown',
                        'count' => $items->count(),
                        'total_value' => $items->sum('price')
                    ];
                })->values();

            // Group by service type
            $byServiceType = TariffItem::with('serviceType')
                ->get()
                ->groupBy('service_type_id')
                ->map(function ($items, $serviceTypeId) {
                    $serviceType = $items->first()->serviceType;
                    return [
                        'service_type' => $serviceType ? $serviceType->name : 'Unknown',
                        'count' => $items->count(),
                        'total_value' => $items->sum('price')
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_items' => $totalItems,
                    'active_items' => $activeItems,
                    'inactive_items' => $inactiveItems,
                    'recent_additions' => $recentAdditions,
                    'by_case_type' => $byCaseType,
                    'by_service_type' => $byServiceType
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

