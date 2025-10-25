<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class DOFacility
 *
 * Represents facility assignments to Desk Officers.
 * Allows admin to assign facilities to users with Desk Officer role.
 */
class DOFacility extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'd_o_facilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'user_id',
        'assigned_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * DOFacility belongs to a facility.
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * DOFacility belongs to a user (Desk Officer).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by desk officer role.
     */
    public function scopeDeskOfficers($query)
    {
        return $query->whereHas('user.roles', function ($q) {
            $q->where('name', 'desk_officer');
        });
    }

    /**
     * Scope to filter by facility.
     */
    public function scopeByFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
