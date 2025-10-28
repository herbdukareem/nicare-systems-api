<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean'
    ];

    /**
     * Scope for active case types
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get tariff items for this case type
     */
    public function tariffItems()
    {
        return $this->hasMany(TariffItem::class, 'case_type_id');
    }
}

