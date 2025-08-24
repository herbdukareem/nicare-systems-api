<?php

namespace App\Http\Controllers;

use App\Models\Lga;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LgaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lga::with(['wards']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        if ($request->boolean('paginate', true)) {
            $lgas = $query->paginate($request->get('per_page', 15));
        } else {
            $lgas = $query->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $lgas
        ]);
    }

    public function show(Lga $lga): JsonResponse
    {
        $lga->load(['wards', 'facilities', 'enrollees']);

        return response()->json([
            'status' => 'success',
            'data' => $lga
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:lgas',
            'code' => 'required|string|max:10|unique:lgas',
            'zone' => 'required|integer|min:1|max:6',
            'baseline' => 'required|integer|min:0',
        ]);

        $lga = Lga::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'LGA created successfully',
            'data' => $lga
        ], 201);
    }

    public function update(Request $request, Lga $lga): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:lgas,name,' . $lga->id,
            'code' => 'required|string|max:10|unique:lgas,code,' . $lga->id,
            'zone' => 'required|integer|min:1|max:6',
            'baseline' => 'required|integer|min:0',
        ]);

        $lga->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'LGA updated successfully',
            'data' => $lga
        ]);
    }

    public function destroy(Lga $lga): JsonResponse
    {
        if ($lga->enrollees()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete LGA with existing enrollees'
            ], 422);
        }

        $lga->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'LGA deleted successfully'
        ]);
    }
}