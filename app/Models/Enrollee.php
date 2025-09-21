<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Status;

/**
 * Class Enrollee
 *
 * Represents an individual registered in the scheme.
 */
class Enrollee extends Model
{


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

    /**
     * Date casting for attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'capitation_start_date' => 'date',
        'approval_date' => 'datetime',
        'status' => Status::class,
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

    /**
     * Enrollee has a benefactor.
     */
    public function benefactor()
    {
        return $this->belongsTo(Benefactor::class);
    }

    /**
     * Enrollee belongs to a premium (pin) if assigned.
     */
    public function premium()
    {
        return $this->belongsTo(Premium::class);
    }

    /**
     * User who created this enrollee.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who approved this enrollee.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
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
    public function referrals()
    {
        return $this->hasMany(Referral::class);
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
}
