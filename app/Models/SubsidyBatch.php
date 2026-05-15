<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubsidyBatch extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'coverage_start_date' => 'date',
        'coverage_end_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function enrollees()
    {
        return $this->hasMany(SubsidyBatchEnrollee::class);
    }
}
