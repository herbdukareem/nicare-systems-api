<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Services\DrugService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DrugsImport;
use App\Exports\DrugsExport;

class DrugController extends Controller
{
    protected DrugService $drugService;

    public function __construct(DrugService $drugService)
    {
        $this->drugService = $drugService;
    }

    /**
     * Display a listing of drugs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $drugs = $this->drugService->getAll($filters);

            return response()->json([
                'success' => true,
                'data' => $drugs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch drugs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created drug
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nicare_code' => 'required|string|max:255|unique:drugs,nicare_code',
                'drug_name' => 'required|string|max:255',
                'drug_dosage_form' => 'required|string|max:255',
                'drug_strength' => 'nullable|string|max:255',
                'drug_presentation' => 'required|string|max:255',
                'drug_unit_price' => 'required|numeric|min:0',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $drugData = $request->all();

            // Check for duplicate drug combination
            if (Drug::existsWithCombination(
                $drugData['drug_name'],
                $drugData['drug_dosage_form'],
                $drugData['drug_strength'] ?? null,
                $drugData['drug_presentation']
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'A drug with the same name, dosage form, strength, and presentation already exists'
                ], 422);
            }

            $drugData['created_by'] = Auth::id();

            $drug = Drug::create($drugData);

            return response()->json([
                'success' => true,
                'message' => 'Drug created successfully',
                'data' => $drug->load(['creator', 'updater'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create drug',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified drug
     */
    public function show(Drug $drug): JsonResponse
    {
        try {
            $drug->load(['creator', 'updater']);

            return response()->json([
                'success' => true,
                'data' => $drug
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch drug',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified drug
     */
    public function update(Request $request, Drug $drug): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nicare_code' => 'required|string|max:255|unique:drugs,nicare_code,' . $drug->id,
                'drug_name' => 'required|string|max:255',
                'drug_dosage_form' => 'required|string|max:255',
                'drug_strength' => 'nullable|string|max:255',
                'drug_presentation' => 'required|string|max:255',
                'drug_unit_price' => 'required|numeric|min:0',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $drugData = $request->all();

            // Check for duplicate drug combination (excluding current drug)
            if (Drug::existsWithCombination(
                $drugData['drug_name'],
                $drugData['drug_dosage_form'],
                $drugData['drug_strength'] ?? null,
                $drugData['drug_presentation'],
                $drug->id
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'A drug with the same name, dosage form, strength, and presentation already exists'
                ], 422);
            }

            $drugData['updated_by'] = Auth::id();

            $drug->update($drugData);

            return response()->json([
                'success' => true,
                'message' => 'Drug updated successfully',
                'data' => $drug->fresh(['creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update drug',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified drug
     */
    public function destroy(Drug $drug): JsonResponse
    {
        try {
            $drug->delete();

            return response()->json([
                'success' => true,
                'message' => 'Drug deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete drug',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import drugs from Excel file
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
            $import = new DrugsImport();
            
            Excel::import($import, $file);

            return response()->json([
                'success' => true,
                'message' => 'Drugs imported successfully',
                'data' => [
                    'imported_count' => $import->getImportedCount(),
                    'errors' => $import->getErrors()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import drugs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export drugs to Excel file
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['search', 'status']);
            $filename = 'drugs_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            return Excel::download(new DrugsExport($filters), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export drugs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get drug statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_drugs' => Drug::count(),
                'active_drugs' => Drug::where('status', true)->count(),
                'inactive_drugs' => Drug::where('status', false)->count(),
                'recent_additions' => Drug::where('created_at', '>=', now()->subDays(30))->count(),
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
     * Download import template
     */
    public function downloadTemplate()
    {
        try {
            $filename = 'drugs_import_template.xlsx';
            return Excel::download(new DrugsExport([], true), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
