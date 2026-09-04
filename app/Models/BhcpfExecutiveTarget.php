<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BhcpfExecutiveTarget extends Model
{
    protected $guarded = ['id'];

    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    public function enrollmentPhase()
    {
        return $this->belongsTo(EnrollmentPhase::class);
    }
}
