<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EnrolleeAccountDetail
 *
 * Stores detailed bank/account information specifically for enrollees.
 * This model corresponds to the second account_details table defined in the migrations.
 */
class EnrolleeAccountDetail extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
  protected $guarded = ['id'];

    /**
     * Casts for attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relation to the enrollee that owns this account detail.
     */
    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }
}
