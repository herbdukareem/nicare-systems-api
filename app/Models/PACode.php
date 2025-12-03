<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PACode extends Model
{
    protected $guarded = ['id'];
    protected $table = 'pa_codes';

    protected $casts = [
        'requested_services' => 'array',
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
        return $this->belongsTo(Referral::class);
    }
}