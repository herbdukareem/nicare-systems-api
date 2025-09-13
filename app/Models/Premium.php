<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Premium
 *
 * Represents a PIN or premium package used for enrolment.
 */
class Premium extends Model
{
    protected $table = 'premiums';

   protected $guarded = ['id'];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'date_generated' => 'datetime',
        'date_used' => 'datetime',
        'date_expired' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Premium may belong to a user who used it (agent or enrollee).
     */
    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Premium may belong to an LGA (when used).
     */
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * Premium may belong to a ward (when used).
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Premium may have many enrollees (if multi-enrolment). Not defined explicitly.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }

     public function userable() { return $this->morphTo(); }
}
