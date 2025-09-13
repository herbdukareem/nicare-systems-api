<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Capitation extends Model
{
    use SoftDeletes;

    protected $table = 'capitations';

    protected $guarded = ['id'];

    protected $casts = [
        'capitated_month' => 'integer',
        'capitation_month' => 'integer',
        'year' => 'integer',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function capitationDetails()
    {
        return $this->hasMany(CapitationDetail::class);
    }

    public function capitationPayments()
    {
        return $this->hasMany(CapitationPayment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth($query, $month)
    {
        return $query->where('capitation_month', $month);
    }
}
