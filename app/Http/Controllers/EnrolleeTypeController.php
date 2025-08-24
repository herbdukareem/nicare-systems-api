<?php

namespace App\Http\Controllers;

use App\Models\EnrolleeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnrolleeTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EnrolleeType::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        if ($request->boolean('paginate', true)) {
            $enrolleeTypes = $query->paginate($request->get('per_page', 15));
        } else {
            $enrolleeTypes = $query->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $enrolleeTypes
        ]);
    }

    public function show(EnrolleeType $enrolleeType): JsonResponse
    {
        $enrolleeType->load(['enrollees']);

        return response()->json([
            'status' => 'success',
            'data' => $enrolleeType
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:enrollee_types',
            'code' => 'required|string|max:10|unique:enrollee_types',
            'description' => 'nullable|string',
            'premium_amount' => 'required|numeric|min:0',
            'premium_duration_months' => 'required|integer|min:1|max:24',
        ]);

        $enrolleeType = EnrolleeType::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollee type created successfully',
            'data' => $enrolleeType
        ], 201);
    }

    public function update(Request $request, EnrolleeType $enrolleeType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:enrollee_types,name,' . $enrolleeType->id,
            'code' => 'required|string|max:10|unique:enrollee_types,code,' . $enrolleeType->id,
            'description' => 'nullable|string',
            'premium_amount' => 'required|numeric|min:0',
            'premium_duration_months' => 'required|integer|min:1|max:24',
        ]);

        $enrolleeType->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollee type updated successfully',
            'data' => $enrolleeType
        ]);
    }

    public function destroy(EnrolleeType $enrolleeType): JsonResponse
    {
        if ($enrolleeType->enrollees()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete enrollee type with existing enrollees'
            ], 422);
        }

        $enrolleeType->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollee type deleted successfully'
        ]);
    }
}