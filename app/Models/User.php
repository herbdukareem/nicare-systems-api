<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 *
 * Represents a system user (admin or agent).
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'agent_reg_number',
        'status',
        'lga_id',
        'ward_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * User belongs to an LGA.
     */
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * User belongs to a ward.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * User has many audit trails.
     */
    public function auditTrails()
    {
        return $this->hasMany(AuditTrail::class);
    }
}
