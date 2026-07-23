<?php

namespace App\Models;

use App\Services\EnrolleeIdGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class Enrollee
 *
 * Represents an individual registered in the scheme.
 */
class Enrollee extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $hidden = ['password'];

    public const STATUS_PENDING = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_SUSPENDED = 3;
    public const STATUS_EXPIRED = 4;

    public const MARITAL_STATUS_OPTIONS = [
        1 => 'Single',
        2 => 'Married',
        3 => 'Divorced',
        4 => 'Widowed',
        5 => 'Not Stated',
    ];

    public const OCCUPATION_OPTIONS = [
        'Student',
        'Farmer',
        'Trader/Business Owner',
        'Civil Servant',
        'Private Sector Employee',
        'Teacher/Lecturer',
        'Health Worker',
        'Artisan',
        'Driver/Transport Worker',
        'Security Personnel',
        'Religious Leader',
        'Homemaker',
        'Retired',
        'Unemployed',
        'Self-Employed',
        'Other',
        'Not Stated',
    ];

    public const DISABILITY_OPTIONS = [
        'None',
        'Visual Impairment',
        'Hearing Impairment',
        'Speech Impairment',
        'Physical/Mobility Disability',
        'Intellectual Disability',
        'Learning Disability',
        'Mental/Psychosocial Disability',
        'Albinism',
        'Multiple Disabilities',
        'Others',
    ];

    public const NIN_VERIFICATION_NOT_STARTED = 'not_started';
    public const NIN_VERIFICATION_NOT_PROVIDED = 'not_provided';
    public const NIN_VERIFICATION_VERIFIED = 'verified';
    public const NIN_VERIFICATION_FAILED = 'failed';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'enrollees';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $enrollee): void {
            if (blank($enrollee->enrollee_id)) {
                $enrollee->enrollee_id = app(EnrolleeIdGenerator::class)->generate();
            }
        });
    }

    /**
     * Date casting for attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'capitation_start_date' => 'date',
        'coverage_start_date' => 'date',
        'coverage_end_date' => 'date',
        'approval_date' => 'datetime',
        'enrollment_date' => 'datetime',
        'nin_verified_at' => 'datetime',
        'nin_verification_data' => 'array',
        'nin_verification_meta' => 'array',
        'enrollment_extra_fields' => 'array',
        'enrollment_location_audit' => 'array',
        'duplicate_reviewed' => 'boolean',
        'duplicate_reviewed_at' => 'datetime',
        'status' => 'integer',
    ];

    /**
     * Enrollee belongs to a type.
     */
    public function enrolleeType()
    {
        return $this->belongsTo(EnrolleeType::class);
    }

    /**
     * Enrollee belongs to a facility.
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Enrollee belongs to an LGA.
     */
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * Enrollee belongs to a ward.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Enrollee has an employment detail.
     */
    public function employmentDetail()
    {
        return $this->hasOne(EmploymentDetail::class);
    }

    /**
     * Enrollee has a funding type.
     */
    public function fundingType()
    {
        return $this->belongsTo(FundingType::class);
    }

    public function insuranceProgramme()
    {
        return $this->belongsTo(InsuranceProgramme::class, 'insurance_programme_id');
    }

    public function enrolleeCategory()
    {
        return $this->belongsTo(EnrolleeCategory::class, 'enrollee_category_id');
    }

    public function premiumPlan()
    {
        return $this->belongsTo(PremiumPlan::class, 'premium_plan_id');
    }

    public function premiumPin()
    {
        return $this->belongsTo(PremiumPin::class, 'premium_pin_id');
    }

    public function premiumPurchase()
    {
        return $this->belongsTo(PremiumPurchase::class, 'premium_purchase_id');
    }

    public function benefitPackage()
    {
        return $this->belongsTo(BenefitPackage::class, 'benefit_package_id');
    }

    public function vulnerableGroup()
    {
        return $this->belongsTo(VulnerableGroup::class, 'vulnerable_group_id');
    }

    public function enrollmentPhase()
    {
        return $this->belongsTo(EnrollmentPhase::class, 'enrollment_phase_id');
    }

    public function enrollmentFormSchema()
    {
        return $this->belongsTo(EnrollmentFormSchema::class, 'enrollment_form_schema_id');
    }

    public function mobileEnrollmentRecord()
    {
        return $this->belongsTo(MobileEnrollmentRecord::class, 'mobile_enrollment_record_id');
    }

    public function providedEnrollmentPhotoUrl(): ?string
    {
        $mobilePassport = $this->latestMobilePassportAttachment();

        return $mobilePassport?->file_path ?: $this->image_url;
    }

    public function latestMobilePassportAttachment(): ?MobileEnrollmentAttachment
    {
        if (($this->enrollment_source ?? null) !== 'mobile_officer' || !$this->mobile_enrollment_record_id) {
            return null;
        }

        $record = $this->relationLoaded('mobileEnrollmentRecord')
            ? $this->mobileEnrollmentRecord
            : $this->mobileEnrollmentRecord()->with('attachments')->first();

        if (!$record) {
            return null;
        }

        $attachments = $record->relationLoaded('attachments')
            ? $record->attachments
            : $record->attachments()->get();

        return $attachments
            ->filter(fn ($attachment) => $attachment instanceof MobileEnrollmentAttachment
                && $attachment->kind === 'passport'
                && filled($attachment->file_path))
            ->sortByDesc(fn (MobileEnrollmentAttachment $attachment) => $attachment->created_at?->timestamp ?? $attachment->id)
            ->first();
    }

    /**
     * Enrollee has a benefactor.
     */
    public function benefactor()
    {
        return $this->belongsTo(Benefactor::class);
    }

    /**
     * User who created this enrollee.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who approved this enrollee.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ninVerifiedBy()
    {
        return $this->belongsTo(User::class, 'nin_verified_by');
    }

    public function duplicateReviewedBy()
    {
        return $this->belongsTo(User::class, 'duplicate_reviewed_by');
    }

    public function creator()
    {
        return $this->createdBy();
    }

    public function approver()
    {
        return $this->approvedBy();
    }

    /**
     * Enrollee has many audit trails.
     */
    public function auditTrails()
    {
        return $this->hasMany(AuditTrail::class);
    }

    /**
     * Enrollee has one account detail (enrollee-specific).
     */
    public function accountDetail()
    {
        return $this->hasOne(EnrolleeAccountDetail::class);
    }

    /**
     * Enrollee has many relations (NOK, family members, etc.)
     */
    public function relations()
    {
        return $this->hasMany(EnrolleeRelation::class);
    }

    /**
     * Enrollee has many primary encounters
     */
    public function primaryEncounters()
    {
        return $this->hasMany(PrimaryEncounter::class);
    }

    /**
     * Enrollee has many feedback records
     */
    public function feedbackRecords()
    {
        return $this->hasMany(FeedbackRecord::class);
    }

    /**
     * Enrollee has many referrals
     */
 
    public function referrals() {
        return $this->hasMany(Referral::class);
    }

    public function principal()
    {
        return $this->belongsTo(self::class, 'principal_enrollee_id');
    }

    public function dependants()
    {
        return $this->hasMany(self::class, 'principal_enrollee_id');
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'payable');
    }

    public function isActive(): bool
    {
        return (int) $this->status === self::STATUS_ACTIVE;
    }

    public function hasValidCoverage(?\DateTimeInterface $date = null): bool
    {
        $date ??= now();

        return $this->isActive()
            && $this->coverage_start_date
            && $this->coverage_start_date->lte($date)
            && ($this->coverage_end_date === null || $this->coverage_end_date->gte($date));
    }

    public function isEligibleForCare(?\DateTimeInterface $date = null): bool
    {
        return $this->hasValidCoverage($date);
    }

    public function isCoverageActive(?\DateTimeInterface $date = null): bool
    {
        return $this->hasValidCoverage($date);
    }

    public function hasNoExpiryCoverage(): bool
    {
        return $this->coverage_start_date !== null && $this->coverage_end_date === null;
    }

    public function getCoverageLabelAttribute(): string
    {
        if (!$this->coverage_start_date) {
            return 'Pending coverage';
        }

        if ($this->hasNoExpiryCoverage()) {
            return 'No Expiry';
        }

        if ($this->coverage_end_date?->isPast() && !$this->coverage_end_date?->isToday()) {
            return 'Expired';
        }

        return 'Active until ' . $this->coverage_end_date?->format('M d, Y');
    }

    /**
     * Enrollee has many PA codes
     */
    public function paCodes()
    {
        return $this->hasMany(PACode::class);
    }

    /**
     * Get the primary contact relation
     */
    public function primaryContact()
    {
        return $this->hasOne(EnrolleeRelation::class)->where('is_primary_contact', true);
    }

    /**
     * Get the next of kin relation
     */
    public function nextOfKin()
    {
        return $this->hasOne(EnrolleeRelation::class)->where('is_next_of_kin', true);
    }

    /**
     * Get emergency contacts
     */
    public function emergencyContacts()
    {
        return $this->hasMany(EnrolleeRelation::class)->where('is_emergency_contact', true);
    }

    /**
     * Get the enrollee's age
     */
    public function getAgeAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    // full name
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name);
    }

    public function facilityTransfers()
    {
        return $this->hasMany(EnrolleeFacilityTransfer::class, 'enrollee_id');
    }

    public function duplicateFlags()
    {
        return $this->hasMany(EnrolleeDuplicateFlag::class, 'enrollee_id');
    }

    public function matchedDuplicateFlags()
    {
        return $this->hasMany(EnrolleeDuplicateFlag::class, 'matched_enrollee_id');
    }

    protected $appends = ['age', 'full_name', 'coverage_label'];
}
