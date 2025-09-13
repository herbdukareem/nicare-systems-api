<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'id';
    protected $guarded  = [];

    
    protected $casts = [
        'metadata'      => 'array',      
        'payment_date'  => 'date',
        'amount'        => 'decimal:2',
    ];

    public function payable()  { return $this->morphTo(); }
    public function userable() { return $this->morphTo(); }
}
