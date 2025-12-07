<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsumableDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'sterile' => 'boolean',
        'single_use' => 'boolean',
        'latex_free' => 'boolean',
        'requires_cold_chain' => 'boolean',
        'hazardous' => 'boolean',
        'units_per_pack' => 'integer',
        'expiry_date' => 'date',
    ];

    /**
     * Get the case record that owns this consumable detail.
     */
    public function caseRecord(): MorphOne
    {
        return $this->morphOne(CaseRecord::class, 'detail');
    }

    /**
     * Get available categories
     */
    public static function getCategories(): array
    {
        return [
            'Surgical Supplies',
            'Dressings & Bandages',
            'Syringes & Needles',
            'IV Fluids & Sets',
            'Gloves',
            'Catheters',
            'Sutures',
            'Drains & Tubes',
            'Specimen Containers',
            'Personal Protective Equipment',
            'Disinfectants & Antiseptics',
            'Oxygen & Respiratory',
        ];
    }

    /**
     * Get available units of measure
     */
    public static function getUnitsOfMeasure(): array
    {
        return [
            'Piece',
            'Box',
            'Pack',
            'Bottle',
            'Liter',
            'Milliliter',
            'Kilogram',
            'Gram',
            'Meter',
            'Roll',
            'Set',
        ];
    }

    /**
     * Get available sterilization methods
     */
    public static function getSterilizationMethods(): array
    {
        return [
            'Gamma Radiation',
            'Ethylene Oxide (ETO)',
            'Autoclave',
            'Dry Heat',
            'Chemical',
        ];
    }
}

