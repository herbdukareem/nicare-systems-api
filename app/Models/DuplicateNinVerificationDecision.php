<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateNinVerificationDecision extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'provider_data' => 'array',
        'before_values' => 'array',
        'after_values' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(DuplicateNinVerificationBatch::class, 'batch_id');
    }

    public function item()
    {
        return $this->belongsTo(DuplicateNinVerificationItem::class, 'item_id');
    }

    public function selectedEnrollee()
    {
        return $this->belongsTo(Enrollee::class, 'selected_enrollee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
