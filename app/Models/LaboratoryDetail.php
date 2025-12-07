<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaboratoryDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_name',
        'test_code',
        'specimen_type',
        'specimen_volume',
        'collection_method',
        'test_method',
        'test_category',
        'turnaround_time',
        'preparation_instructions',
        'reference_range',
        'reporting_unit',
        'fasting_required',
        'urgent_available',
        'urgent_surcharge'
    ];

    protected $casts = [
        'turnaround_time' => 'integer',
        'urgent_surcharge' => 'decimal:2',
        'fasting_required' => 'boolean',
        'urgent_available' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'fasting_required' => false,
        'urgent_available' => false
    ];

    // Specimen type constants
    const SPECIMEN_BLOOD = 'Blood';
    const SPECIMEN_URINE = 'Urine';
    const SPECIMEN_STOOL = 'Stool';
    const SPECIMEN_SPUTUM = 'Sputum';
    const SPECIMEN_CSF = 'Cerebrospinal Fluid';
    const SPECIMEN_SWAB = 'Swab';
    const SPECIMEN_TISSUE = 'Tissue';

    // Test category constants
    const CATEGORY_HEMATOLOGY = 'Hematology';
    const CATEGORY_CHEMISTRY = 'Chemistry';
    const CATEGORY_MICROBIOLOGY = 'Microbiology';
    const CATEGORY_IMMUNOLOGY = 'Immunology';
    const CATEGORY_PATHOLOGY = 'Pathology';
    const CATEGORY_RADIOLOGY = 'Radiology';

    /**
     * Get the case record that owns this laboratory detail (inverse of morphTo)
     */
    public function caseRecord(): MorphOne
    {
        return $this->morphOne(CaseRecord::class, 'detail');
    }

    /**
     * Get available specimen types
     */
    public static function getSpecimenTypes(): array
    {
        return [
            self::SPECIMEN_BLOOD,
            self::SPECIMEN_URINE,
            self::SPECIMEN_STOOL,
            self::SPECIMEN_SPUTUM,
            self::SPECIMEN_CSF,
            self::SPECIMEN_SWAB,
            self::SPECIMEN_TISSUE
        ];
    }

    /**
     * Get available test categories
     */
    public static function getTestCategories(): array
    {
        return [
            self::CATEGORY_HEMATOLOGY,
            self::CATEGORY_CHEMISTRY,
            self::CATEGORY_MICROBIOLOGY,
            self::CATEGORY_IMMUNOLOGY,
            self::CATEGORY_PATHOLOGY,
            self::CATEGORY_RADIOLOGY
        ];
    }
}

