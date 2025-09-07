<?php

namespace App\Services;

use App\Models\Permission;

/**
 * Class PermissionService
 *
 * Handles business logic related to permissions.
 */
class PermissionService
{
    /**
     * List all permissions.
     *
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    public function all()
    {
        return Permission::all();
    }

    /**
     * Create a new permission.
     *
     * @param  array<string, mixed>  $data
     * @return Permission
     */
    public function create(array $data): Permission
    {
        return Permission::create($data);
    }

    /**
     * Update an existing permission.
     *
     * @param  Permission  $permission
     * @param  array<string, mixed>  $data
     * @return Permission
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        return $permission;
    }

    /**
     * Delete a permission.
     *
     * @param  Permission  $permission
     * @return bool|null
     */
    public function delete(Permission $permission): ?bool
    {
        return $permission->delete();
    }
}