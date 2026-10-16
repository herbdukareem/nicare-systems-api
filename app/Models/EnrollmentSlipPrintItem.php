<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentSlipPrintItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function enrollee(): BelongsTo
    {
        return $this->belongsTo(Enrollee::class);
    }
}
