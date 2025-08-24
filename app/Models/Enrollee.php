<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Enrollee
 *
 * Represents an individual registered in the scheme.
 */
class Enrollee extends Model
{
    use SoftDeletes;

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
    protected $fillable = [
        'enrollee_id',
        'nin',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'marital_status',
        'address',
        'enrollee_type_id',
        'enrollee_category',
        'facility_id',
        'lga_id',
        'ward_id',
        'village',
        'premium_id',
        'employment_detail_id',
        'funding_type_id',
        'benefactor_id',
        'capitation_start_date',
        'approval_date',
        'status',
        'created_by',
        'approved_by',
    ];

    /**
     * Date casting for attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'capitation_start_date' => 'date',
        'approval_date' => 'datetime',
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
}
