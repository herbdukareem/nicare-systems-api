<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EmploymentDetail
 *
 * Stores employment information for enrollees.
 */
class EmploymentDetail extends Model
{
    protected $table = 'employment_details';

    protected $fillable = [
        'enrollee_id',
        'employer_name',
        'employer_address',
        'employer_phone',
        'job_title',
        'employment_type',
        'employment_status',
        'monthly_income',
        'employment_start_date',
        'employment_end_date',
        'industry',
        'job_description',
        'is_verified',
        'verified_at',
        'verification_method',
        'metadata',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Employment detail belongs to an enrollee.
     */
    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }
}
