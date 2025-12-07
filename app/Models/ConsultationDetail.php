<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsultationDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'diagnostic_tests_included' => 'boolean',
        'prescription_included' => 'boolean',
        'medical_report_included' => 'boolean',
        'referral_letter_included' => 'boolean',
        'follow_up_required' => 'boolean',
        'emergency_available' => 'boolean',
        'insurance_accepted' => 'boolean',
        'duration_minutes' => 'integer',
        'follow_up_interval_days' => 'integer',
    ];

    /**
     * Get the case record that owns this consultation detail.
     */
    public function caseRecord(): MorphOne
    {
        return $this->morphOne(CaseRecord::class, 'detail');
    }

    /**
     * Get available consultation types
     */
    public static function getConsultationTypes(): array
    {
        return [
            'Initial Consultation',
            'Follow-up Consultation',
            'Emergency Consultation',
            'Second Opinion',
            'Pre-operative Assessment',
            'Post-operative Review',
            'Chronic Disease Management',
        ];
    }

    /**
     * Get available specialties
     */
    public static function getSpecialties(): array
    {
        return [
            'General Practice',
            'Internal Medicine',
            'Pediatrics',
            'Surgery',
            'Obstetrics & Gynecology',
            'Cardiology',
            'Neurology',
            'Orthopedics',
            'Dermatology',
            'Psychiatry',
            'Ophthalmology',
            'ENT',
            'Urology',
            'Nephrology',
            'Endocrinology',
        ];
    }

    /**
     * Get available provider levels
     */
    public static function getProviderLevels(): array
    {
        return [
            'Consultant',
            'Specialist',
            'Registrar',
            'Medical Officer',
            'General Practitioner',
        ];
    }

    /**
     * Get available consultation modes
     */
    public static function getConsultationModes(): array
    {
        return [
            'In-person',
            'Telemedicine',
            'Home Visit',
            'Ward Round',
        ];
    }
}

