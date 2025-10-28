<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TariffItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'case_id',
        'service_type_id',
        'tariff_item',
        'price',
        'case_type_id',
        'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'boolean'
    ];

    /**
     * Scope for active tariff items
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get the case category for this tariff item
     */
    public function caseCategory()
    {
        return $this->belongsTo(CaseCategory::class, 'case_id');
    }

    /**
     * Get the service type for this tariff item
     */
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    /**
     * Get the case type for this tariff item
     */
    public function caseType()
    {
        return $this->belongsTo(CaseType::class, 'case_type_id');
    }
}
