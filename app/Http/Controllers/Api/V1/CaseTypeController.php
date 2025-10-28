<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CaseType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CaseTypeController extends Controller
{
    /**
     * Display a listing of case types
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CaseType::query();

            // Filter by status if provided
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 1000);
            $caseTypes = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $caseTypes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch case types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified case type
     */
    public function show(CaseType $caseType): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $caseType
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch case type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

