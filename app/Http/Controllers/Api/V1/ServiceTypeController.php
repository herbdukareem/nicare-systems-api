<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceTypeController extends Controller
{
    /**
     * Display a listing of service types
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ServiceType::query();

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
            $serviceTypes = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $serviceTypes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service type
     */
    public function show(ServiceType $serviceType): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $serviceType
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

