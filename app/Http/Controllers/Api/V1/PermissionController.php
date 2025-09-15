<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PermissionService;
use App\Models\Permission;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
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
        $category = $request->get('category');

        $permissionsQuery = Permission::withCount('roles');

        if ($search) {
            $permissionsQuery->where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('label', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $permissionsQuery->where('category', $category);
        }

        $permissions = $permissionsQuery->paginate($perPage);
        return $this->sendResponse(PermissionResource::collection($permissions), 'Permissions retrieved successfully');
    }

    /**
     * Store a newly created permission.
     */
    public function store(StorePermissionRequest $request)
    {
        $data = $request->validated();
        $permission = $this->permissionService->create($data);
        return $this->sendResponse(new PermissionResource($permission), 'Permission created successfully', 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission)
    {
        $permission->load('roles');
        return $this->sendResponse(new PermissionResource($permission), 'Permission retrieved successfully');
    }

    /**
     * Update the specified permission.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $data = $request->validated();
        $permission = $this->permissionService->update($permission, $data);
        return $this->sendResponse(new PermissionResource($permission), 'Permission updated successfully');
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroy(Permission $permission)
    {
        $this->permissionService->delete($permission);
        return $this->sendResponse([], 'Permission deleted successfully');
    }

    /**
     * Get permissions grouped by category
     */
    public function byCategory()
    {
        $permissions = Permission::withCount('roles')->get()->groupBy('category');

        $grouped = $permissions->map(function ($categoryPermissions, $category) {
            return [
                'category' => $category ?: 'General',
                'permissions' => PermissionResource::collection($categoryPermissions),
                'count' => $categoryPermissions->count(),
            ];
        });

        return $this->sendResponse($grouped->values(), 'Permissions grouped by category retrieved successfully');
    }

    /**
     * Bulk create permissions
     */
    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.name' => 'required|string|unique:permissions,name',
            'permissions.*.label' => 'required|string',
            'permissions.*.description' => 'nullable|string',
            'permissions.*.category' => 'nullable|string',
        ]);

        $created = [];
        foreach ($validated['permissions'] as $permissionData) {
            $created[] = $this->permissionService->create($permissionData);
        }

        return $this->sendResponse(
            PermissionResource::collection($created),
            "Successfully created {count($created)} permissions",
            201
        );
    }

    /**
     * Bulk delete permissions
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $deleted = Permission::whereIn('id', $validated['permission_ids'])->delete();

        return $this->sendResponse(
            ['deleted_count' => $deleted],
            "Successfully deleted {$deleted} permissions"
        );
    }
}