<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EnrolleeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Enrollee::with(['enrolleeType', 'facility', 'lga', 'ward', 'village']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('lga_id')) {
            $query->where('lga_id', $request->lga_id);
        }
        
        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('enrollee_id', 'like', "%{$search}%")
                  ->orWhere('nin', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $enrollees = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $enrollees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:enrollees,phone',
            'nin' => 'nullable|string|unique:enrollees,nin',
            'email' => 'nullable|email|unique:enrollees,email',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:Male,Female',
            'enrollee_type_id' => 'required|exists:enrollee_types,id',
            'facility_id' => 'required|exists:facilities,id',
            'lga_id' => 'required|exists:lgas,id',
            'ward_id' => 'required|exists:wards,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $enrolleeData = $request->all();
            $enrolleeData['enrollee_id'] = $this->generateEnrolleeId();
            $enrolleeData['created_by'] = auth()->id();
            $enrolleeData['status'] = 'pending';

            $enrollee = Enrollee::create($enrolleeData);

            // Create audit trail
            AuditTrail::create([
                'enrollee_id' => $enrollee->id,
                'action' => 'created',
                'description' => 'Enrollee created',
                'user_id' => auth()->id(),
                'old_values' => null,
                'new_values' => json_encode($enrollee->toArray()),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollee created successfully',
                'data' => $enrollee->load(['enrolleeType', 'facility', 'lga', 'ward']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Enrollee $enrollee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $enrollee->load([
                'enrolleeType', 'facility', 'lga', 'ward', 'village',
                'premium', 'employmentDetail', 'fundingType', 'benefactor'
            ]),
        ]);
    }

    public function update(Request $request, Enrollee $enrollee): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:enrollees,phone,' . $enrollee->id,
            'nin' => 'nullable|string|unique:enrollees,nin,' . $enrollee->id,
            'email' => 'nullable|email|unique:enrollees,email,' . $enrollee->id,
            'date_of_birth' => 'sometimes|date',
            'gender' => 'sometimes|in:Male,Female',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldValues = $enrollee->toArray();
        $enrollee->update($request->all());

        // Create audit trail
        AuditTrail::create([
            'enrollee_id' => $enrollee->id,
            'action' => 'updated',
            'description' => 'Enrollee updated',
            'user_id' => auth()->id(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($enrollee->fresh()->toArray()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enrollee updated successfully',
            'data' => $enrollee->fresh()->load(['enrolleeType', 'facility', 'lga', 'ward']),
        ]);
    }

    public function approve(Request $request, Enrollee $enrollee): JsonResponse
    {
        if ($enrollee->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending enrollees can be approved',
            ], 400);
        }

        $enrollee->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approval_date' => now(),
            'capitation_start_date' => $request->capitation_start_date ?? now(),
        ]);

        // Create audit trail
        AuditTrail::create([
            'enrollee_id' => $enrollee->id,
            'action' => 'approved',
            'description' => 'Enrollee approved',
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enrollee approved successfully',
            'data' => $enrollee->fresh(),
        ]);
    }

    public function auditTrail(Enrollee $enrollee): JsonResponse
    {
        $auditTrails = $enrollee->auditTrails()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auditTrails,
        ]);
    }

    private function generateEnrolleeId(): string
    {
        $prefix = 'NGSCHA';
        $lastEnrollee = Enrollee::where('enrollee_id', 'like', $prefix . '%')
            ->orderBy('enrollee_id', 'desc')
            ->first();

        if ($lastEnrollee) {
            $lastNumber = (int) substr($lastEnrollee->enrollee_id, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 9, '0', STR_PAD_LEFT);
    }
}