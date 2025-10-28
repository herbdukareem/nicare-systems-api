<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cases';

    protected $guarded = [ ];

    protected $casts = [
        'price' => 'decimal:2',
        'case_category' => 'integer',
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

    // Case category constants
    const CATEGORY_MAIN_CASE = 1;
    const CATEGORY_SUB_CASE = 2;

    // Case groups constants
    const GROUP_GENERAL_CONSULTATION = 'GENERAL CONSULTATION';
    const GROUP_HEALTH_EDUCATION = 'HEALTH EDUCATION';
    const GROUP_PAEDIATRICS = 'PAEDIATRICS';
    const GROUP_INTERNAL_MEDICINE = 'INTERNAL MEDICINE (PRV)';

    /**
     * Get the user who created this case record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this case record
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the case group this case belongs to
     */
    public function caseGroup()
    {
        return $this->belongsTo(CaseGroup::class, 'case_group_id');
    }

    /**
     * Get the case category this case belongs to
     */
    public function caseCategoryRelation()
    {
        return $this->belongsTo(CaseCategory::class, 'case_category_id');
    }

    /**
     * Scope to get only active cases
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to search cases by name or code
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('case_description', 'like', "%{$search}%")
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
     * Alias 'case_description' attribute to the underlying 'service_description' column
     */
    public function getCaseDescriptionAttribute()
    {
        return $this->attributes['service_description'] ?? null;
    }

    public function setCaseDescriptionAttribute($value): void
    {
        $this->attributes['service_description'] = $value;
    }

    /**
     * Alias 'case_category' attribute to the underlying 'service_category' column
     */
    public function getCaseCategoryAttribute()
    {
        return $this->attributes['service_category'] ?? null;
    }

    public function setCaseCategoryAttribute($value): void
    {
        $this->attributes['service_category'] = (int) $value;
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
     * Get available case groups
     */
    public static function getCaseGroups()
    {
        return [
            self::GROUP_GENERAL_CONSULTATION,
            self::GROUP_HEALTH_EDUCATION,
            self::GROUP_PAEDIATRICS,
            self::GROUP_INTERNAL_MEDICINE
        ];
    }

    /**
     * Get available case categories
     */
    public static function getCaseCategories()
    {
        return [
            self::CATEGORY_MAIN_CASE => 'Main Case',
            self::CATEGORY_SUB_CASE => 'Sub Case'
        ];
    }

    /**
     * Get case category text
     */
    public function getCaseCategoryTextAttribute()
    {
        return $this->case_category === self::CATEGORY_MAIN_CASE ? 'Main Case' : 'Sub Case';
    }

    /**
     * Scope for main cases
     */
    public function scopeMainCases($query)
    {
        return $query->where('case_category', self::CATEGORY_MAIN_CASE);
    }

    /**
     * Scope for sub cases
     */
    public function scopeSubCases($query)
    {
        return $query->where('case_category', self::CATEGORY_SUB_CASE);
    }
}

