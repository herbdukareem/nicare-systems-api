<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drug extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nicare_code',
        'drug_name',
        'drug_dosage_form',
        'drug_strength',
        'drug_presentation',
        'drug_unit_price',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'drug_unit_price' => 'decimal:2',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => true
    ];

    /**
     * Get the user who created this drug record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this drug record
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get only active drugs
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to search drugs by name or code
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('drug_name', 'like', "%{$search}%")
              ->orWhere('nicare_code', 'like', "%{$search}%")
              ->orWhere('drug_dosage_form', 'like', "%{$search}%")
              ->orWhere('drug_strength', 'like', "%{$search}%");
        });
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return '₦' . number_format($this->drug_unit_price, 2);
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }
}
