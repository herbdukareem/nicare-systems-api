<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
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
     * Scope for active service categories
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get tariff items for this service category
     */
    public function tariffItems()
    {
        return $this->hasMany(TariffItem::class, 'service_category_id');
    }

    /**
     * Get services for this category
     */
    public function services()
    {
        return $this->hasMany(Service::class, 'service_category_id');
    }
}
