<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Role
 *
 * Represents a user role. Roles group permissions and can be assigned to users.
 */
class Role extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'modules' => 'array',
    ];

    /**
     * A role may be assigned to many users.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * A role may have many permissions.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * Check if role has access to a specific module.
     *
     * @param  string  $module
     * @return bool
     */
    public function hasModule(string $module): bool
    {
        if (!$this->modules) {
            return false;
        }
        return in_array($module, $this->modules);
    }
}