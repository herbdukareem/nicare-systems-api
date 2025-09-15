<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Role;
use App\Services\RoleService;
use App\Http\Resources\RoleResource;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
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
    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();

        // Handle permissions separately
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = $this->roleService->create($data);

        // Assign permissions if provided
        if (!empty($permissions)) {
            $role->permissions()->sync($permissions);
        }

        return $this->sendResponse(new RoleResource($role->load('permissions')), 'Role created successfully', 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->load('permissions', 'users');
        return $this->sendResponse(new RoleResource($role), 'Role retrieved successfully');
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $data = $request->validated();

        // Handle permissions separately
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $role = $this->roleService->update($role, $data);

        // Sync permissions if provided
        if ($permissions !== null) {
            $role->permissions()->sync($permissions);
        }

        return $this->sendResponse(new RoleResource($role->load('permissions')), 'Role updated successfully');
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
        return $this->sendResponse(new RoleResource($role->load('permissions')), 'Permissions synced successfully');
    }

    /**
     * Get roles with user counts
     */
    public function withUserCounts()
    {
        $roles = Role::withCount('users')->with('permissions')->get();
        return $this->sendResponse(RoleResource::collection($roles), 'Roles with user counts retrieved successfully');
    }

    /**
     * Bulk delete roles
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $deleted = Role::whereIn('id', $validated['role_ids'])->delete();

        return $this->sendResponse(
            ['deleted_count' => $deleted],
            "Successfully deleted {$deleted} roles"
        );
    }

    /**
     * Clone a role with its permissions
     */
    public function clone(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'label' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $newRole = Role::create($validated);
        $newRole->permissions()->sync($role->permissions->pluck('id'));

        return $this->sendResponse(
            new RoleResource($newRole->load('permissions')),
            'Role cloned successfully',
            201
        );
    }
}