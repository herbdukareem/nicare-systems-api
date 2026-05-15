<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Facility
 *
 * Represents a healthcare facility/provider.
 */
class Facility extends Model
{
    use HasFactory, SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'facilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * Facility belongs to an LGA.
     */
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * Facility belongs to a Ward.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Facility may have an account detail.
     */
    public function accountDetail()
    {
        return $this->belongsTo(AccountDetail::class);
    }

    /**
     * The desk officers assigned to this facility.
     */
    public function assignedUsers()
    {
        return $this->hasMany(DOFacility::class);
    }

    // determine if it's primary by the type
    public function getIsPrimaryAttribute()
    {
        return $this->type === 'Primary';
    }
    

    /**
     * Get desk officers through the DOFacility pivot.
     */
    public function deskOfficers()
    {
        return $this->belongsToMany(User::class, 'd_o_facilities', 'facility_id', 'user_id')
                    ->withPivot('assigned_at')
                    ->withTimestamps();
    }

    /**
     * Facility has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }

    public function outgoingFacilityTransfers()
    {
        return $this->hasMany(EnrolleeFacilityTransfer::class, 'from_facility_id');
    }

    public function incomingFacilityTransfers()
    {
        return $this->hasMany(EnrolleeFacilityTransfer::class, 'to_facility_id');
    }

    // facility capacity to count from enrollee model
    public function getFacilityCapacityAttribute()
    {
        return $this->enrollees()->count();
    }

    /**
     * Scope to filter by level of care
     */
    public function scopeByLevelOfCare($query, $level)
    {
        return $query->where('level_of_care', $level);
    }

    /**
     * Scope: only accredited (active) facilities.
     */
    public function scopeAccredited($query)
    {
        return $query->where('accreditation_status', 'active');
    }

    /**
     * Scope: suspended facilities.
     */
    public function scopeSuspended($query)
    {
        return $query->where('accreditation_status', 'suspended');
    }

    /**
     * Check if facility can provide services of a specific level
     */
    public function canProvideServiceLevel($serviceLevel)
    {
        // Primary facilities can only provide Primary services
        // Secondary facilities can provide Primary and Secondary services
        // Tertiary facilities can provide all levels

        $facilityLevel = $this->level_of_care;

        if ($facilityLevel === 'Primary') {
            return $serviceLevel === 'Primary';
        } elseif ($facilityLevel === 'Secondary') {
            return in_array($serviceLevel, ['Primary', 'Secondary']);
        } elseif ($facilityLevel === 'Tertiary') {
            return in_array($serviceLevel, ['Primary', 'Secondary', 'Tertiary']);
        }

        return false;
    }

    /**
     * Get available levels of care
     */
    public static function getLevelsOfCare()
    {
        return ['Primary', 'Secondary', 'Tertiary'];
    }

    // appends
    protected $appends = ['facility_capacity', 'is_primary'];
}
