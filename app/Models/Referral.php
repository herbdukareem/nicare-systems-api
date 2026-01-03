<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Referral
 * Represents a Referral Pre-Authorisation (RR) request.
 */
class Referral extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'referrals';

    protected $casts = [
        'request_date' => 'datetime',
        'approval_date' => 'datetime',
        'valid_until' => 'datetime',
        'claim_submitted_at' => 'datetime',
        'requested_services' => 'array',
        'case_record_ids' => 'array',
        'claim_submitted' => 'boolean',
    ];

    /**
     * Referral belongs to an Enrollee.
     */
    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    /**
     * Referral is from a Referring Facility.
     */
    public function referringFacility()
    {
        return $this->belongsTo(Facility::class, 'referring_facility_id');
    }
    
    /**
     * Referral is to a Receiving Facility.
     */
    public function receivingFacility()
    {
        return $this->belongsTo(Facility::class, 'receiving_facility_id');
    }

    /**
     * Referral may have a selected service bundle (CaseRecord where is_bundle = true).
     * service_bundle_id references case_records.id
     */
    public function serviceBundle()
    {
        return $this->belongsTo(CaseRecord::class, 'service_bundle_id');
    }

    

    /**
     * Referral may have a selected direct service (case record).
     */
    public function caseRecord()
    {
        return $this->belongsTo(CaseRecord::class, 'case_record_id');
    }

    /**
     * Referral can have multiple direct service selections stored as IDs.
     */
    // public function caseRecords()
    // {
    //     if (empty($this->case_record_ids)) {
    //         return collect([]);
    //     }

    //     return CaseRecord::whereIn('id', $this->case_record_ids)->get();
    // }

   public function getCaseRecordsDataAttribute() 
    {
        if (empty($this->case_record_ids)) {
            return collect([]);
        }

        // Use whereIn to retrieve the associated Case Records
        return CaseRecord::whereIn('id', $this->case_record_ids)->get();
    }

    /**
     * A Referral PA can have multiple Follow-up PA Codes.
     */
    public function paCodes()
    {
        return $this->hasMany(PACode::class);
    }

    /**
     * Referral has many uploaded documents.
     */
    public function documents()
    {
        return $this->hasMany(ReferralDocument::class);
    }

    /**
     * Referral has many admissions.
     */
    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    /**
     * Referral has many claims.
     */
    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    /**
     * Referral has many feedback records.
     */
    public function feedbackRecords()
    {
        return $this->hasMany(FeedbackRecord::class);
    }

    /**
     * Check if the referral UTN has passed its validity period.
     */
    public function isExpired(): bool
    {
        if (!$this->valid_until) {
            return false;
        }

        return now()->greaterThan($this->valid_until);
    }

    /**
     * Check if referral has a bundle PA code.
     */
    public function hasBundlePACode(): bool
    {
        return $this->paCodes()
            ->where('type', PACode::TYPE_BUNDLE)
            ->exists();
    }

    /**
     * Get the bundle PA code for this referral.
     */
    public function bundlePACode()
    {
        return $this->paCodes()
            ->where('type', PACode::TYPE_BUNDLE)
            ->first();
    }

    /**
     * Mark claim as submitted for this referral.
     */
    public function markClaimSubmitted(): void
    {
        $this->update([
            'claim_submitted' => true,
            'claim_submitted_at' => now(),
        ]);
    }

    /**
     * Check if this referral is ready for claim submission.
     * Must be approved and UTN validated.
     */
    public function isReadyForClaimSubmission(): bool
    {
        return $this->status === 'APPROVED'
            && $this->utn_validated
            && !$this->claim_submitted;
    }

    /**
     * Scope to get referrals without submitted claims.
     */
    public function scopeWithoutClaim($query)
    {
        return $query->where('claim_submitted', false);
    }

    /**
     * Scope to get referrals ready for claim submission.
     */
    public function scopeReadyForClaim($query)
    {
        return $query->where('status', 'APPROVED')
            ->where('utn_validated', true)
            ->where('claim_submitted', false);
    }

    /**
     * Check if this referral has an active admission.
     */
    public function hasActiveAdmission(): bool
    {
        return $this->admissions()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get the active admission for this referral.
     */
    public function getActiveAdmission()
    {
        return $this->admissions()
            ->where('status', 'active')
            ->first();
    }

    /**
     * Check if the episode is completed (all admissions discharged and claim submitted).
     */
    public function isEpisodeCompleted(): bool
    {
        return !$this->hasActiveAdmission() && $this->claim_submitted;
    }

    /**
     * Check if an enrollee can receive a new referral.
     * Returns array with 'can_refer' boolean and 'reason' string.
     */
    public static function canEnrolleeBeReferred(int $enrolleeId): array
    {
        // Check for active admission first (episode not completed/closed)
        $activeAdmission = \App\Models\Admission::where('enrollee_id', $enrolleeId)
            ->where('status', 'active')
            ->first();

        if ($activeAdmission) {
            return [
                'can_refer' => false,
                'reason' => 'Enrollee has an active admission/episode (Admission Code: ' . $activeAdmission->admission_code . '). The current episode must be closed/completed (patient discharged) before a new referral can be created.',
                'active_admission' => $activeAdmission
            ];
        }

        // Check if enrollee has pending referral without submitted claim
        $pendingReferral = self::where('enrollee_id', $enrolleeId)
            ->where('status', 'APPROVED')
            ->where('claim_submitted', false)
            ->first();

        if ($pendingReferral) {
            return [
                'can_refer' => false,
                'reason' => 'Enrollee has an approved referral (UTN: ' . $pendingReferral->utn . ') without a submitted claim. Please submit a claim for that referral before creating a new one.',
                'pending_referral' => $pendingReferral
            ];
        }

        return [
            'can_refer' => true,
            'reason' => 'Enrollee is eligible for a new referral.'
        ];
    }

    protected $appends = [
        'case_records_data'
    ];
}
