<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DrugDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DrugDetailController extends Controller
{
    /**
     * Display a listing of drug details
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $status = $request->input('status');
            $dosageForm = $request->input('dosage_form');
            $prescriptionRequired = $request->input('prescription_required');
            $controlledDrug = $request->input('controlled_drug');

            $query = DrugDetail::query();

            // Search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('generic_name', 'like', "%{$search}%")
                      ->orWhere('brand_name', 'like', "%{$search}%")
                      ->orWhere('manufacturer', 'like', "%{$search}%")
                      ->orWhere('drug_class', 'like', "%{$search}%");
                });
            }

            // Dosage form filter
            if ($dosageForm) {
                $query->where('dosage_form', $dosageForm);
            }

            // Prescription required filter
            if ($prescriptionRequired !== null) {
                $query->where('prescription_required', $prescriptionRequired);
            }

            // Controlled drug filter
            if ($controlledDrug !== null) {
                $query->where('controlled_substance', $controlledDrug);
            }

            // Status filter (if you add status field)
            if ($status !== null) {
                $query->where('status', $status);
            }

            $drugs = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $drugs->items(),
                'total' => $drugs->total(),
                'current_page' => $drugs->currentPage(),
                'last_page' => $drugs->lastPage(),
                'per_page' => $drugs->perPage()
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
     * Store a newly created drug detail
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'generic_name' => 'required|string|max:255',
                'brand_name' => 'nullable|string|max:255',
                'dosage_form' => 'nullable|string|max:255',
                'strength' => 'nullable|string|max:255',
                'route_of_administration' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'drug_class' => 'nullable|string|max:255',
                'indications' => 'nullable|string',
                'contraindications' => 'nullable|string',
                'side_effects' => 'nullable|string',
                'storage_conditions' => 'nullable|string',
                'prescription_required' => 'boolean',
                'controlled_substance' => 'boolean',
                'nafdac_number' => 'nullable|string|max:255',
                'expiry_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $drug = DrugDetail::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Drug created successfully',
                'data' => $drug
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
     * Display the specified drug detail
     */
    public function show(DrugDetail $drugDetail): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $drugDetail
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
     * Update the specified drug detail
     */
    public function update(Request $request, DrugDetail $drugDetail): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'generic_name' => 'required|string|max:255',
                'brand_name' => 'nullable|string|max:255',
                'dosage_form' => 'nullable|string|max:255',
                'strength' => 'nullable|string|max:255',
                'route_of_administration' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'drug_class' => 'nullable|string|max:255',
                'indications' => 'nullable|string',
                'contraindications' => 'nullable|string',
                'side_effects' => 'nullable|string',
                'storage_conditions' => 'nullable|string',
                'prescription_required' => 'boolean',
                'controlled_substance' => 'boolean',
                'nafdac_number' => 'nullable|string|max:255',
                'expiry_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $drugDetail->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Drug updated successfully',
                'data' => $drugDetail->fresh()
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
     * Remove the specified drug detail
     */
    public function destroy(DrugDetail $drugDetail): JsonResponse
    {
        try {
            $drugDetail->delete();

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
     * Get drug statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => DrugDetail::count(),
                'prescription_required' => DrugDetail::where('prescription_required', true)->count(),
                'controlled_drug' => DrugDetail::where('controlled_substance', true)->count(),
                'nafdac_approved' => DrugDetail::whereNotNull('nafdac_number')->count(),
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
     * Get dosage forms
     */
    public function dosageForms(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DrugDetail::getDosageForms()
        ]);
    }

    /**
     * Get routes of administration
     */
    public function routes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DrugDetail::getRoutes()
        ]);
    }
}

