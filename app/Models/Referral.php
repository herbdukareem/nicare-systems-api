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
        'requested_services' => 'array',
        'case_record_ids' => 'array',
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
     * Referral may have a selected service bundle.
     */
    public function serviceBundle()
    {
        return $this->belongsTo(ServiceBundle::class, 'service_bundle_id');
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
    public function caseRecords()
    {
        if (empty($this->case_record_ids)) {
            return collect([]);
        }

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
     * Check if the referral UTN has passed its validity period.
     */
    public function isExpired(): bool
    {
        if (!$this->valid_until) {
            return false;
        }

        return now()->greaterThan($this->valid_until);
    }
}
