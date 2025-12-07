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
        'requested_services' => 'array',
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
     * A Referral PA can have multiple Follow-up PA Codes.
     */
    public function paCodes()
    {
        return $this->hasMany(PACode::class);
    }
}
