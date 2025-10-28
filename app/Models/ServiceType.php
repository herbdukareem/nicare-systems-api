<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceType extends Model
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
     * Scope for active service types
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get tariff items for this service type
     */
    public function tariffItems()
    {
        return $this->hasMany(TariffItem::class, 'service_type_id');
    }
}

