<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PermissionService;
use App\Models\Permission;
use Illuminate\Http\Request;

/**
 * Class PermissionController
 *
 * Handles CRUD operations for permissions.
 */
class PermissionController extends BaseController
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Display a listing of the permissions.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');

        $permissionsQuery = Permission::with('roles');

        if ($search) {
            $permissionsQuery->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('label', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $permissions = $permissionsQuery->paginate($perPage);
        return $this->sendResponse($permissions, 'Permissions retrieved successfully');
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'label' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $permission = $this->permissionService->create($validated);
        return $this->sendResponse($permission, 'Permission created successfully', 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission)
    {
        $permission->load('roles');
        return $this->sendResponse($permission, 'Permission retrieved successfully');
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:permissions,name,' . $permission->id,
            'label' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $permission = $this->permissionService->update($permission, $validated);
        return $this->sendResponse($permission, 'Permission updated successfully');
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroy(Permission $permission)
    {
        $this->permissionService->delete($permission);
        return $this->sendResponse([], 'Permission deleted successfully');
    }
}