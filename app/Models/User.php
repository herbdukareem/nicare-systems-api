<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
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
    'password',
    'userable_id',
    'userable_type',
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
     * Determine if the user has a given role.
     *
     * @param  string  $roleName
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * The permissions that belong directly to the user's roles.
     */
    public function permissions()
    {
        return $this->roles()->with('permissions')->get()->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    /**
     * Determine if the user has a given permission via roles.
     *
     * @param  string  $permissionName
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->contains(function ($permission) use ($permissionName) {
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
}
