<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DOFacility;
use App\Models\User;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DOFacilityController extends Controller
{
    /**
     * Display a listing of facility assignments.
     */
    public function index(Request $request)
    {
        try {
            $query = DOFacility::with(['user.roles', 'facility.lga', 'facility.ward']);

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('facility', function ($facilityQuery) use ($search) {
                        $facilityQuery->where('name', 'like', "%{$search}%")
                                     ->orWhere('hcp_code', 'like', "%{$search}%");
                    });
                });
            }

            // Filter by user (desk officer)
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by facility
            if ($request->filled('facility_id')) {
                $query->where('facility_id', $request->facility_id);
            }

            // Filter by desk officers only
            $query->deskOfficers();

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $assignments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $assignments,
                'message' => 'Facility assignments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created facility assignment.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'facility_id' => 'required|exists:facilities,id',
            ]);

            // Check if user has permission to be assigned to facilities
            $user = User::with('roles')->find($validated['user_id']);
            if (!$user->hasPermission('facilities.view')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must have facility permissions to be assigned to facilities',
                    'errors' => ['user_id' => ['Selected user does not have required permissions']]
                ], 422);
            }

            // Check if assignment already exists and consider deleted_at
            $existingAssignment = DOFacility::where('user_id', $validated['user_id'])
                                          ->where('facility_id', $validated['facility_id'])
                                          ->first();

            if ($existingAssignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'This facility is already assigned to the selected desk officer',
                    'errors' => ['facility_id' => ['Facility already assigned to this user']]
                ], 422);
            }

            $assignment = DOFacility::create([
                'user_id' => $validated['user_id'],
                'facility_id' => $validated['facility_id'],
                'assigned_at' => now(),
            ]);

            $assignment->load(['user.roles', 'facility.lga', 'facility.ward']);

            return response()->json([
                'success' => true,
                'data' => $assignment,
                'message' => 'Facility assigned to desk officer successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign facility',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified facility assignment.
     */
    public function show(DOFacility $doFacility)
    {
        try {
            $doFacility->load(['user.roles', 'facility.lga', 'facility.ward']);

            return response()->json([
                'success' => true,
                'data' => $doFacility,
                'message' => 'Facility assignment retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified facility assignment.
     */
    public function update(Request $request, DOFacility $doFacility)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'facility_id' => 'required|exists:facilities,id',
            ]);

            // Check if user has permission to be assigned to facilities
            $user = User::with('roles')->find($validated['user_id']);
            if (!$user->hasPermission('facilities.view')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User must have facility permissions to be assigned to facilities',
                    'errors' => ['user_id' => ['Selected user does not have required permissions']]
                ], 422);
            }

            // Check if assignment already exists (excluding current record)
            $existingAssignment = DOFacility::where('user_id', $validated['user_id'])
                                          ->where('facility_id', $validated['facility_id'])
                                          ->where('id', '!=', $doFacility->id)
                                          ->first();

            if ($existingAssignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'This facility is already assigned to the selected desk officer',
                    'errors' => ['facility_id' => ['Facility already assigned to this user']]
                ], 422);
            }

            $doFacility->update($validated);
            $doFacility->load(['user.roles', 'facility.lga', 'facility.ward']);

            return response()->json([
                'success' => true,
                'data' => $doFacility,
                'message' => 'Facility assignment updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update facility assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified facility assignment.
     */
    public function destroy(DOFacility $doFacility)
    {
        try {
            $doFacility->delete();

            return response()->json([
                'success' => true,
                'message' => 'Facility assignment removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove facility assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all desk officers (users with desk_officer, facility_admin, or facility_user roles).
     */
    public function getDeskOfficers()
    {
        try {
            $deskOfficers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['desk_officer', 'facility_admin', 'facility_user']);
            })->with('roles')->get();

            return response()->json([
                'success' => true,
                'data' => $deskOfficers,
                'message' => 'Desk officers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve desk officers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all facilities.
     */
    public function getFacilities()
    {
        try {
            $facilities = Facility::with(['lga', 'ward'])
                                ->where('status', 1)
                                ->orderBy('name')
                                ->get();

            return response()->json([
                'success' => true,
                'data' => $facilities,
                'message' => 'Facilities retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facilities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get facilities assigned to a specific desk officer.
     */
    public function getUserFacilities($userId)
    {
        try {
            $assignments = DOFacility::with(['facility.lga', 'facility.ward'])
                                   ->where('user_id', $userId)
                                   ->get();

            return response()->json([
                'success' => true,
                'data' => $assignments,
                'message' => 'User facilities retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user facilities',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
