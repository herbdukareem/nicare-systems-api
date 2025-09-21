<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Facility
 *
 * Represents a healthcare facility/provider.
 */
class Facility extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'facilities';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'hcp_code',
        'name',
        'ownership',
        'level_of_care',
        'address',
        'phone',
        'email',
        'lga_id',
        'ward_id',
        'capacity',
        'status',
        'account_detail_id',
    ];

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
     * Facility has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }

    /**
     * Scope to filter by level of care
     */
    public function scopeByLevelOfCare($query, $level)
    {
        return $query->where('level_of_care', $level);
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
}
