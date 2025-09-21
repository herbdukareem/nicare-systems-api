<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nicare_code',
        'service_description',
        'level_of_care',
        'price',
        'group',
        'service_group_id',
        'pa_required',
        'referable',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'pa_required' => 'boolean',
        'referable' => 'boolean',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => true,
        'pa_required' => false,
        'referable' => true
    ];

    // Level of care constants
    const LEVEL_PRIMARY = 'Primary';
    const LEVEL_SECONDARY = 'Secondary';
    const LEVEL_TERTIARY = 'Tertiary';

    // Service groups constants
    const GROUP_GENERAL_CONSULTATION = 'GENERAL CONSULTATION';
    const GROUP_HEALTH_EDUCATION = 'HEALTH EDUCATION';
    const GROUP_PAEDIATRICS = 'PAEDIATRICS';
    const GROUP_INTERNAL_MEDICINE = 'INTERNAL MEDICINE (PRV)';

    /**
     * Get the user who created this service record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this service record
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the service group this service belongs to
     */
    public function serviceGroup()
    {
        return $this->belongsTo(ServiceGroup::class, 'service_group_id');
    }

    /**
     * Scope to get only active services
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to search services by name or code
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('service_description', 'like', "%{$search}%")
              ->orWhere('nicare_code', 'like', "%{$search}%")
              ->orWhere('group', 'like', "%{$search}%")
              ->orWhere('level_of_care', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to filter by level of care
     */
    public function scopeByLevelOfCare($query, $level)
    {
        return $query->where('level_of_care', $level);
    }

    /**
     * Scope to filter by group
     */
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope to filter by PA requirement
     */
    public function scopeRequiresPA($query, $required = true)
    {
        return $query->where('pa_required', $required);
    }

    /**
     * Scope to filter by referable status
     */
    public function scopeReferable($query, $referable = true)
    {
        return $query->where('referable', $referable);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return '₦' . number_format($this->price, 2);
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Get PA required text
     */
    public function getPaRequiredTextAttribute()
    {
        return $this->pa_required ? 'Yes' : 'No';
    }

    /**
     * Get referable text
     */
    public function getReferableTextAttribute()
    {
        return $this->referable ? 'Yes' : 'No';
    }

    /**
     * Get available levels of care
     */
    public static function getLevelsOfCare()
    {
        return [
            self::LEVEL_PRIMARY,
            self::LEVEL_SECONDARY,
            self::LEVEL_TERTIARY
        ];
    }

    /**
     * Get available service groups
     */
    public static function getServiceGroups()
    {
        return [
            self::GROUP_GENERAL_CONSULTATION,
            self::GROUP_HEALTH_EDUCATION,
            self::GROUP_PAEDIATRICS,
            self::GROUP_INTERNAL_MEDICINE
        ];
    }
}
