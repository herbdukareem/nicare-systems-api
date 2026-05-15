<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollBatch extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function enrollees()
    {
        return $this->hasMany(PayrollBatchEnrollee::class);
    }
}
