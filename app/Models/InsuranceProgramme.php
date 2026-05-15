<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceProgramme extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function categories()
    {
        return $this->hasMany(EnrolleeCategory::class);
    }

    public function premiumPlans()
    {
        return $this->hasMany(PremiumPlan::class);
    }

    public function premiumPins()
    {
        return $this->hasMany(PremiumPin::class, 'insurance_programme_id');
    }

    public function enrollees()
    {
        return $this->hasMany(Enrollee::class, 'insurance_programme_id');
    }
}
