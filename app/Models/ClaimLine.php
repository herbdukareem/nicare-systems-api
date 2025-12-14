<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimLine extends Model
{
    protected $guarded = ['id'];
    protected $table = 'claim_lines';

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'is_approved' => 'boolean',
    ];

    /**
     * Claim Line belongs to a Claim.
     */
    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * Claim Line belongs to a CaseRecord (service).
     */
    public function caseRecord()
    {
        return $this->belongsTo(CaseRecord::class);
    }

    /**
     * Claim Line is linked to a PACode. (Crucial for validation)
     */
    public function paCode()
    {
        return $this->belongsTo(PACode::class);
    }

    /**
     * Claim Line belongs to a Drug (when service_type is 'drug').
     */
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    /**
     * Claim Line belongs to a ServiceBundle (when tariff_type is 'BUNDLE').
     */
    public function serviceBundle()
    {
        return $this->belongsTo(ServiceBundle::class, 'bundle_id');
    }
}