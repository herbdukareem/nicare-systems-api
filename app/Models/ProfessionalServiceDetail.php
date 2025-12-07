<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfessionalServiceDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_name',
        'service_code',
        'specialty',
        'duration_minutes',
        'provider_type',
        'equipment_needed',
        'procedure_description',
        'indications',
        'contraindications',
        'complications',
        'pre_procedure_requirements',
        'post_procedure_care',
        'anesthesia_required',
        'anesthesia_type',
        'admission_required',
        'recovery_time_hours',
        'follow_up_required'
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'recovery_time_hours' => 'integer',
        'anesthesia_required' => 'boolean',
        'admission_required' => 'boolean',
        'follow_up_required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'anesthesia_required' => false,
        'admission_required' => false,
        'follow_up_required' => false
    ];

    // Specialty constants
    const SPECIALTY_GENERAL = 'General Practice';
    const SPECIALTY_CARDIOLOGY = 'Cardiology';
    const SPECIALTY_ORTHOPEDICS = 'Orthopedics';
    const SPECIALTY_PEDIATRICS = 'Pediatrics';
    const SPECIALTY_OBSTETRICS = 'Obstetrics & Gynecology';
    const SPECIALTY_SURGERY = 'Surgery';
    const SPECIALTY_INTERNAL_MEDICINE = 'Internal Medicine';
    const SPECIALTY_DERMATOLOGY = 'Dermatology';

    // Provider type constants
    const PROVIDER_CONSULTANT = 'Consultant';
    const PROVIDER_SPECIALIST = 'Specialist';
    const PROVIDER_GP = 'General Practitioner';
    const PROVIDER_NURSE = 'Nurse Practitioner';

    // Anesthesia type constants
    const ANESTHESIA_LOCAL = 'Local';
    const ANESTHESIA_GENERAL = 'General';
    const ANESTHESIA_SEDATION = 'Sedation';
    const ANESTHESIA_REGIONAL = 'Regional';

    /**
     * Get the case record that owns this professional service detail (inverse of morphTo)
     */
    public function caseRecord(): MorphOne
    {
        return $this->morphOne(CaseRecord::class, 'detail');
    }

    /**
     * Get available specialties
     */
    public static function getSpecialties(): array
    {
        return [
            self::SPECIALTY_GENERAL,
            self::SPECIALTY_CARDIOLOGY,
            self::SPECIALTY_ORTHOPEDICS,
            self::SPECIALTY_PEDIATRICS,
            self::SPECIALTY_OBSTETRICS,
            self::SPECIALTY_SURGERY,
            self::SPECIALTY_INTERNAL_MEDICINE,
            self::SPECIALTY_DERMATOLOGY
        ];
    }

    /**
     * Get available provider types
     */
    public static function getProviderTypes(): array
    {
        return [
            self::PROVIDER_CONSULTANT,
            self::PROVIDER_SPECIALIST,
            self::PROVIDER_GP,
            self::PROVIDER_NURSE
        ];
    }

    /**
     * Get available anesthesia types
     */
    public static function getAnesthesiaTypes(): array
    {
        return [
            self::ANESTHESIA_LOCAL,
            self::ANESTHESIA_GENERAL,
            self::ANESTHESIA_SEDATION,
            self::ANESTHESIA_REGIONAL
        ];
    }
}

