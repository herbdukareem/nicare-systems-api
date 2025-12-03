<?php
// app/Models/Claim.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $guarded = ['id'];

    /**
     * Claim belongs to an Enrollee.
     */
    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    /**
     * Claim belongs to the submitting Facility.
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Claim has many line items.
     */
    public function lineItems()
    {
        return $this->hasMany(ClaimLine::class);
    }
}