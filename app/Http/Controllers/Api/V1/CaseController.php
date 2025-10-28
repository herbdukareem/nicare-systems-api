<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CaseGroup;
use App\Services\CaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CasesImport;
use App\Exports\CasesExport;

class CaseController extends Controller
{
    protected CaseService $caseService;

    public function __construct(CaseService $caseService)
    {
        $this->caseService = $caseService;
    }

    /**
     * Display a listing of cases
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'level_of_care', 'group', 'pa_required', 'referable',
                'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $cases = $this->caseService->getAll($filters);

            return response()->json([
                'success' => true,
                'data' => $cases
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cases',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created case
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nicare_code' => 'required|string|max:255|unique:cases,nicare_code',
                'case_description' => 'required|string',
                'level_of_care' => 'required|in:Primary,Secondary,Tertiary',
                'price' => 'required|numeric|min:0',
                'case_group_id' => 'required|exists:case_groups,id',
                'pa_required' => 'boolean',
                'referable' => 'boolean',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            //get group
            $group = CaseGroup::find($request->input('case_group_id'));

            $caseData = $request->all();
            $caseData['created_by'] = Auth::id();
            $caseData['group'] = $group->name;

            $caseRecord = \App\Models\CaseRecord::create($caseData);

            return response()->json([
                'success' => true,
                'message' => 'Case created successfully',
                'data' => $caseRecord->load(['creator', 'updater'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create case',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified case
     */
    public function show(\App\Models\CaseRecord $caseRecord): JsonResponse
    {
        try {
            $caseRecord->load(['creator', 'updater']);

            return response()->json([
                'success' => true,
                'data' => $caseRecord
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch case',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified case
     */
    public function update(Request $request, \App\Models\CaseRecord $caseRecord): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nicare_code' => 'required|string|max:255|unique:cases,nicare_code,' . $caseRecord->id,
                'case_description' => 'required|string',
                'level_of_care' => 'required|in:Primary,Secondary,Tertiary',
                'price' => 'required|numeric|min:0',
                'group' => 'required|string|max:255',
                'pa_required' => 'boolean',
                'referable' => 'boolean',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $caseData = $request->all();
            $caseData['updated_by'] = Auth::id();

            $caseRecord->update($caseData);

            return response()->json([
                'success' => true,
                'message' => 'Case updated successfully',
                'data' => $caseRecord->fresh(['creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update case',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified case
     */
    public function destroy(\App\Models\CaseRecord $caseRecord): JsonResponse
    {
        try {
            $caseRecord->delete();

            return response()->json([
                'success' => true,
                'message' => 'Case deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete case',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import cases from Excel file
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
            $import = new CasesImport();

            Excel::import($import, $file);

            $importedCount = $import->getImportedCount();
            $errors = $import->getErrors();
            $hasErrors = !empty($errors);

            // Determine response based on import results
            if ($importedCount > 0 && !$hasErrors) {
                // All cases imported successfully
                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$importedCount} case(s)",
                    'data' => [
                        'imported_count' => $importedCount,
                        'errors' => []
                    ]
                ]);
            } elseif ($importedCount > 0 && $hasErrors) {
                // Some cases imported, some failed
                return response()->json([
                    'success' => true,
                    'message' => "Imported {$importedCount} case(s) with " . count($errors) . " error(s)",
                    'data' => [
                        'imported_count' => $importedCount,
                        'errors' => $errors
                    ]
                ]);
            } else {
                // No cases imported, all failed
                return response()->json([
                    'success' => false,
                    'message' => 'No cases were imported. Please check the errors below.',
                    'data' => [
                        'imported_count' => 0,
                        'errors' => $errors
                    ]
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import cases: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export cases to Excel file
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['search', 'status', 'level_of_care', 'group']);
            $filename = 'cases_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            return Excel::download(new CasesExport($filters), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export cases',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get case statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->caseService->getStatistics();

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
            $filename = 'cases_import_template.xlsx';
            return Excel::download(new CasesExport([], true), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get case groups
     */
    public function getGroups(): JsonResponse
    {
        try {
            $groups = CaseGroup::active()
                ->orderBy('name')
                ->get(['id', 'name', 'description']);

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch case groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

