<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrolleeCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function programme()
    {
        return $this->belongsTo(InsuranceProgramme::class, 'insurance_programme_id');
    }

    public function enrollees()
    {
        return $this->hasMany(Enrollee::class, 'enrollee_category_id');
    }
}
