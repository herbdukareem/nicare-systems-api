<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PACode extends Model
{
    protected $guarded = ['id'];
    protected $table = 'pa_codes';

    protected $casts = [
        'requested_services' => 'array',
        'case_record_ids' => 'array',
    ];

    // Constants for PA Types
    const TYPE_BUNDLE = 'BUNDLE';
    const TYPE_FFS_TOP_UP = 'FFS_TOP_UP';

    /**
     * PA Code belongs to an Enrollee.
     */
    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    /**
     * PA Code belongs to the requesting Facility.
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }
    
    /**
     * PA Code has many Claim Lines.
     */
    public function claimLines()
    {
        return $this->hasMany(ClaimLine::class);
    }

    /**
     * PA Code belongs to a Referral (The Referral PA).
     */
    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    /**
     * PA Code belongs to an Admission (episode tracking).
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * PA Code belongs to a Service Bundle (CaseRecord where is_bundle = true).
     * service_bundle_id references case_records.id
     */
    public function serviceBundle()
    {
        return $this->belongsTo(CaseRecord::class, 'service_bundle_id')
                    ->where('is_bundle', true);
    }

    /**
     * PA Code has many Case Records (for FFS service selection).
     * This is a custom accessor since we store IDs in JSON.
     */
    public function getCaseRecordsAttribute()
    {
        if (empty($this->case_record_ids)) {
            return collect([]);
        }

        return CaseRecord::whereIn('id', $this->case_record_ids)->get();
    }

    /**
     * PA Code has many uploaded documents.
     */
    public function documents()
    {
        return $this->hasMany(PACodeDocument::class, 'pa_code_id', 'id');
    }

     protected $appends = [
        'case_records'
    ];
}