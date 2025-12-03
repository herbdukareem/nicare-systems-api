<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimLine extends Model
{
    protected $guarded = ['id'];
    protected $table = 'claim_lines';

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
}