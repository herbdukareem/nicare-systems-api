<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class RoleService
 *
 * Handles business logic related to roles.
 */
class RoleService
{
    /**
     * List all roles.
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function all()
    {
        return Role::all();
    }

    /**
     * Create a new role.
     *
     * @param  array<string, mixed>  $data
     * @return Role
     */
    public function create(array $data): Role
    {
        return Role::create($data);
    }

    /**
     * Update an existing role.
     *
     * @param  Role  $role
     * @param  array<string, mixed>  $data
     * @return Role
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);
        return $role;
    }

    /**
     * Delete a role.
     *
     * @param  Role  $role
     * @return bool|null
     */
    public function delete(Role $role): ?bool
    {
        return $role->delete();
    }

    /**
     * Assign permissions to a role.
     *
     * @param  Role  $role
     * @param  array<int>  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
    }
}