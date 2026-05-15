<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrolleeFacilityTransfer extends Model
{
    protected $table = 'enrollee_facility_transfers';

    protected $fillable = [
        'enrollee_id',
        'from_facility_id',
        'to_facility_id',
        'transfer_reason',
        'transferred_by',
        'effective_date',
        'approved_by',
        'approved_at',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'approved_at'    => 'datetime',
    ];

    // ---- Relationships -------------------------------------------------------

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class, 'enrollee_id');
    }

    public function fromFacility()
    {
        return $this->belongsTo(Facility::class, 'from_facility_id');
    }

    public function toFacility()
    {
        return $this->belongsTo(Facility::class, 'to_facility_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
