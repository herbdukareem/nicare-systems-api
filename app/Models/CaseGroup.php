<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'case_groups';

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => true
    ];

    /**
     * Get the user who created this case group
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this case group
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all cases in this group
     */
    public function cases()
    {
        return $this->hasMany(\App\Models\CaseRecord::class, 'case_group_id');
    }

    /**
     * Scope to get only active case groups
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to search case groups by name
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}

