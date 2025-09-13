<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{


    protected $table = 'sectors';
    protected $primaryKey = 'id';

 protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class, 'sector_id', 'id');
    }

    public function premiums()
    {
        return $this->hasMany(Premium::class, 'sector_id', 'id');
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
