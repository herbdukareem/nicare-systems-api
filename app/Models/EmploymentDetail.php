<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentDetail extends Model
{
    protected $table = 'employment_details';

    protected $fillable = [
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
        'employable_id',
        'employable_type',
    ];

    public function employable()
    {
        return $this->morphTo();
    }
}
