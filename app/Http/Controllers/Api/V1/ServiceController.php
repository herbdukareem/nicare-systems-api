<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ServicesImport;
use App\Exports\ServicesExport;

class ServiceController extends Controller
{
    protected ServiceService $serviceService;

    public function __construct(ServiceService $serviceService)
    {
        $this->serviceService = $serviceService;
    }

    /**
     * Display a listing of services
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'level_of_care', 'group', 'pa_required', 'referable',
                'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $services = $this->serviceService->getAll($filters);

            return response()->json([
                'success' => true,
                'data' => $services
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created service
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nicare_code' => 'required|string|max:255|unique:services,nicare_code',
                'service_description' => 'required|string',
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

            $serviceData = $request->all();
            $serviceData['created_by'] = Auth::id();

            $service = Service::create($serviceData);

            return response()->json([
                'success' => true,
                'message' => 'Service created successfully',
                'data' => $service->load(['creator', 'updater'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service
     */
    public function show(Service $service): JsonResponse
    {
        try {
            $service->load(['creator', 'updater']);

            return response()->json([
                'success' => true,
                'data' => $service
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified service
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nicare_code' => 'required|string|max:255|unique:services,nicare_code,' . $service->id,
                'service_description' => 'required|string',
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

            $serviceData = $request->all();
            $serviceData['updated_by'] = Auth::id();

            $service->update($serviceData);

            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully',
                'data' => $service->fresh(['creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified service
     */
    public function destroy(Service $service): JsonResponse
    {
        try {
            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import services from Excel file
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
            $import = new ServicesImport();
            
            Excel::import($import, $file);

            return response()->json([
                'success' => true,
                'message' => 'Services imported successfully',
                'data' => [
                    'imported_count' => $import->getImportedCount(),
                    'errors' => $import->getErrors()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export services to Excel file
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only(['search', 'status', 'level_of_care', 'group']);
            $filename = 'services_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            return Excel::download(new ServicesExport($filters), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->serviceService->getStatistics();

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
            $filename = 'services_import_template.xlsx';
            return Excel::download(new ServicesExport([], true), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service groups
     */
    public function getGroups(): JsonResponse
    {
        try {
            $groups = Service::select('group')
                ->distinct()
                ->where('status', true)
                ->orderBy('group')
                ->pluck('group');

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
