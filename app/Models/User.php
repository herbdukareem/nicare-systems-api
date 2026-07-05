<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class User
 *
 * Represents a system user (admin or agent).
 */
class User extends Authenticatable
{
       use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

   

    protected $fillable = [
    'name',
    'email',
    'phone',
    'username',
    'status',
    'mobile_enrollment_disabled_at',
    'password',
    'userable_id',
    'userable_type',
    'current_role_id',
];

    protected $casts = [
        'mobile_enrollment_disabled_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function userable()
    {
        return $this->morphTo();
    }

    /**
     * The roles that belong to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * The currently active role for the user.
     */
    public function currentRole()
    {
        return $this->belongsTo(Role::class, 'current_role_id');
    }

    /**
     * Determine if the user has a given role.
     *
     * @param  string  $roleName
     * @return bool
     */
    public function hasRole($roleName): bool
    {
        if (is_array($roleName)) {
            return $this->hasAnyRole($roleName);
        }

        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Switch to a different role (must be one of user's assigned roles).
     *
     * @param  int  $roleId
     * @return bool
     */
    public function switchRole(int $roleId): bool
    {
        if ($this->roles()->where('roles.id', $roleId)->exists()) {
            $this->current_role_id = $roleId;
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Backward-compatible category access derived from permissions.
     *
     * @return array
     */
    public function getAvailableModules(): array
    {
        $role = $this->currentRole ?? $this->roles()->first();
        if (!$role) {
            return [];
        }

        return $role->permissions()
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Direct permissions assigned to the user (many-to-many).
     */
    public function directPermissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
                    ->withTimestamps();
    }

    /**
     * The permissions that belong to the user's roles.
     */
    public function rolePermissions()
    {
        return $this->roles()->with('permissions')->get()->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    /**
     * Alias for backward compatibility - returns role permissions.
     */
    public function permissions()
    {
        return $this->rolePermissions();
    }

    /**
     * The facilities assigned to this user (for Desk Officers).
     */
    public function assignedFacilities()
    {
        return $this->hasMany(DOFacility::class);
    }

    /**
     * Get facilities through the DOFacility pivot.
     */
    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'd_o_facilities', 'user_id', 'facility_id')
                    ->withPivot('assigned_at')
                    ->withTimestamps();
    }

    public function officerEnrollmentAssignments()
    {
        return $this->hasMany(OfficerEnrollmentAssignment::class);
    }

    public function activeOfficerEnrollmentAssignments()
    {
        return $this->officerEnrollmentAssignments()->where('enabled', true);
    }

    public function mobileEnrollmentEnabled(): bool
    {
        return $this->mobile_enrollment_disabled_at === null;
    }

    /**
     * Determine if the user has a given permission (via roles or direct assignment).
     *
     * @param  string  $permissionName
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        // Check direct permissions first
        if ($this->directPermissions()->where('name', $permissionName)->exists()) {
            return true;
        }

        // Check role permissions
        return $this->rolePermissions()->contains(function ($permission) use ($permissionName) {
            return $permission->name === $permissionName;
        });
    }

    /**
     * User has many audit trails.
     */
    public function auditTrails()
    {
        return $this->hasMany(AuditTrail::class);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole($roles)
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has a specific permission (alias for hasPermission)
     */
    // public function can($permission)
    // {
    //     return $this->hasPermission($permission);
    // }

    public function can($abilities, $arguments = [])
        {
            // If you pass a plain permission string with no extra args,
            // check your role/permission system first
            if (is_string($abilities) && empty($arguments)) {
                return $this->hasPermission($abilities);
            }

            // Otherwise, defer to Laravel’s Gate/Policies
            return Gate::forUser($this)->check($abilities, $arguments);
        }


    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission($permissions)
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions) {
                $query->whereIn('name', $permissions);
            })
            ->exists();
    }

    /**
     * Get all permissions for this user (both role-based and direct)
     * Uses already-loaded relationships if available to prevent N+1 queries
     */
    public function getAllPermissions()
    {
        // Get permissions from roles - use loaded relationship if available
        if ($this->relationLoaded('roles')) {
            // Use already-loaded roles
            $rolePermissions = $this->roles
                ->flatMap(function ($role) {
                    // Check if permissions are loaded on the role
                    if ($role->relationLoaded('permissions')) {
                        return $role->permissions;
                    }
                    // Fallback: load permissions for this role only
                    return $role->permissions()->get();
                })
                ->unique('id');
        } else {
            // Fallback: query database if roles not loaded
            $rolePermissions = $this->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten();
        }

        // Get direct permissions - use loaded relationship if available
        $directPermissions = $this->relationLoaded('directPermissions')
            ? $this->directPermissions
            : $this->directPermissions()->get();

        // Merge and remove duplicates
        return $rolePermissions->merge($directPermissions)->unique('id');
    }

    /**
     * Get only role-based permissions
     * Uses already-loaded relationships if available to prevent N+1 queries
     */
    public function getRolePermissions()
    {
        if ($this->relationLoaded('roles')) {
            // Use already-loaded roles
            return $this->roles
                ->flatMap(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        return $role->permissions;
                    }
                    return $role->permissions()->get();
                })
                ->unique('id');
        }

        // Fallback: query database if roles not loaded
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    /**
     * Get only direct permissions
     */
    public function getDirectPermissions()
    {
        return $this->directPermissions;
    }

    // determine if the user 
}
