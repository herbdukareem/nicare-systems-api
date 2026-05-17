<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitationDetail extends Model
{
    
    protected $table = 'capitation_details';
    protected $primaryKey = 'id';
   protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'reviewed_at' => 'date',
        'approved_at' => 'date',
        'paid_at' => 'date',
    ];

    // capitation
    public function capitation(){
        return $this->belongsTo(Capitation::class, 'capitation_id', 'id');
    }

    // facility
    public function facility(){
        return $this->belongsTo(Facility::class, 'facility_id', 'id');
    }

    // funding type
    public function fundingType(){
        return $this->belongsTo(FundingType::class, 'funding_type_id', 'id');
    }
}
