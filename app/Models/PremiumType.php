<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PremiumType extends Model
{

    protected $table = 'premium_types';

    protected $fillable = [
        'name',
        'code',
        'description',
        'amount',
        'duration_months',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'duration_months' => 'integer',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function premiums()
    {
        return $this->hasMany(Premium::class, 'premium_type_id');
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'payable');
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
