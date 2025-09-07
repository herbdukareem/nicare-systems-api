<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Request;

/**
 * Class RoleController
 *
 * Handles CRUD operations for roles and assigning permissions to roles.
 */
class RoleController extends BaseController
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the roles.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');

        $rolesQuery = Role::with('permissions');

        if ($search) {
            $rolesQuery->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('label', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $rolesQuery->paginate($perPage);
        return $this->sendResponse($roles, 'Roles retrieved successfully');
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'label' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $role = $this->roleService->create($validated);
        return $this->sendResponse($role, 'Role created successfully', 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->load('permissions');
        return $this->sendResponse($role, 'Role retrieved successfully');
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $role->id,
            'label' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $role = $this->roleService->update($role, $validated);
        return $this->sendResponse($role, 'Role updated successfully');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        $this->roleService->delete($role);
        return $this->sendResponse([], 'Role deleted successfully');
    }

    /**
     * Assign permissions to the role.
     */
    public function syncPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);
        $this->roleService->syncPermissions($role, $validated['permissions']);
        return $this->sendResponse($role->load('permissions'), 'Permissions synced successfully');
    }
}