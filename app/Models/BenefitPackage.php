<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitPackage extends Model
{
   

    protected $table = 'benefit_packages';
    protected $primaryKey = 'id';

  protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class, 'benefit_package_id', 'id');
    }

    public function premiums()
    {
        return $this->hasMany(Premium::class, 'benefit_package_id', 'id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }
}
