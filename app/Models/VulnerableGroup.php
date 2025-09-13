<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VulnerableGroup extends Model
{

    protected $table = 'vulnerable_groups';
    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class, 'vulnerable_group_id', 'id');
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

    public function scopeForAgeRange($query, $minAge = null, $maxAge = null)
    {
        if ($minAge !== null) {
            $query->where('min_age', '>=', $minAge);
        }

        if ($maxAge !== null) {
            $query->where('max_age', '<=', $maxAge);
        }

        return $query;
    }
}
