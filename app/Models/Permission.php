<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Permission
 *
 * Represents a single permission that can be assigned to roles.
 */
class Permission extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'label',
        'description',
    ];

    /**
     * A permission may belong to many roles.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}