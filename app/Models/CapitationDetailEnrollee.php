<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitationDetailEnrollee extends Model
{
    protected $table = 'capitation_detail_enrollees';

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'coverage_start_date' => 'date',
        'coverage_end_date' => 'date',
        'capitation_start_date' => 'date',
        'captured_at' => 'datetime',
        'snapshot_status' => 'integer',
        'has_duplicate_nin' => 'boolean',
        'metadata' => 'array',
    ];

    public function capitation()
    {
        return $this->belongsTo(Capitation::class);
    }

    public function capitationDetail()
    {
        return $this->belongsTo(CapitationDetail::class, 'capitation_detail_id');
    }

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function fundingType()
    {
        return $this->belongsTo(FundingType::class);
    }
}
