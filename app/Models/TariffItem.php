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
        'service_category_id',
        'service_code',
        'description',
        'unit_cost',
        'default_qty',
        'position',
        'status'
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'default_qty' => 'integer',
        'position' => 'integer',
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
     * Scope for ordering by position
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Get the case category for this tariff item
     */
    public function caseCategory()
    {
        return $this->belongsTo(CaseCategory::class, 'case_id');
    }

    /**
     * Get the service category for this tariff item
     */
    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * Calculate total cost based on quantity
     */
    public function calculateTotalCost(?int $quantity = null): float
    {
        $qty = $quantity ?? $this->default_qty;
        return $this->unit_cost * $qty;
    }
}
