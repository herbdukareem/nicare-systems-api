<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateNinVerificationCandidate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'match_result' => 'array',
        'keeps_nin' => 'boolean',
        'nin_cleared' => 'boolean',
        'cleared_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(DuplicateNinVerificationItem::class, 'item_id');
    }

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class, 'enrollee_id');
    }
}
