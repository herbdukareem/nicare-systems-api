<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyMigrationLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'legacy_payload' => 'array',
    ];

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class, 'new_enrollee_id');
    }
}
