<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentPhase extends Model
{
    protected $table = 'enrollment_phases';
    protected $primaryKey = 'id';
    protected $guarded  = [];

    // enrollees
    public function enrollees(){
        return $this->hasMany(Enrollee::class, 'enrollment_phase_id', 'id');
    }

    // benefactors
    public function benefactor(){
        return $this->belongsTo(Benefactor::class, 'benefactor_id', 'id');
    }
}
