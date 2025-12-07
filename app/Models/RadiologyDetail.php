<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadiologyDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'examination_name',
        'examination_code',
        'modality',
        'body_part',
        'view_projection',
        'contrast_required',
        'contrast_type',
        'preparation_instructions',
        'duration_minutes',
        'indications',
        'contraindications',
        'pregnancy_safe',
        'radiation_dose',
        'turnaround_time',
        'urgent_available',
        'urgent_surcharge',
        'special_equipment',
        'sedation_required',
    ];

    protected $casts = [
        'contrast_required' => 'boolean',
        'pregnancy_safe' => 'boolean',
        'urgent_available' => 'boolean',
        'sedation_required' => 'boolean',
        'urgent_surcharge' => 'decimal:2',
        'duration_minutes' => 'integer',
        'turnaround_time' => 'integer',
    ];

    /**
     * Get the case record that owns this radiology detail.
     */
    public function caseRecord(): MorphOne
    {
        return $this->morphOne(CaseRecord::class, 'detail');
    }

    /**
     * Get available modality options
     */
    public static function getModalities(): array
    {
        return [
            'X-Ray',
            'CT Scan',
            'MRI',
            'Ultrasound',
            'Mammography',
            'Fluoroscopy',
            'PET Scan',
            'DEXA Scan',
            'Angiography',
        ];
    }

    /**
     * Get available radiation dose levels
     */
    public static function getRadiationDoseLevels(): array
    {
        return [
            'None',
            'Very Low',
            'Low',
            'Medium',
            'High',
        ];
    }

    /**
     * Get available contrast types
     */
    public static function getContrastTypes(): array
    {
        return [
            'Iodinated',
            'Gadolinium',
            'Barium',
            'Microbubble',
        ];
    }
}

