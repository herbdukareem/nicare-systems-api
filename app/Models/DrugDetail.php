<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrugDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'generic_name',
        'brand_name',
        'dosage_form',
        'strength',
        'route_of_administration',
        'manufacturer',
        'drug_class',
        'indications',
        'contraindications',
        'side_effects',
        'storage_conditions',
        'prescription_required',
        'controlled_substance',
        'nafdac_number',
        'expiry_date'
    ];

    protected $casts = [
        'prescription_required' => 'boolean',
        'controlled_substance' => 'boolean',
        'expiry_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'prescription_required' => true,
        'controlled_substance' => false
    ];

    // Dosage form constants
    const FORM_TABLET = 'Tablet';
    const FORM_CAPSULE = 'Capsule';
    const FORM_SYRUP = 'Syrup';
    const FORM_INJECTION = 'Injection';
    const FORM_CREAM = 'Cream';
    const FORM_OINTMENT = 'Ointment';
    const FORM_DROPS = 'Drops';
    const FORM_INHALER = 'Inhaler';

    // Route of administration constants
    const ROUTE_ORAL = 'Oral';
    const ROUTE_IV = 'Intravenous';
    const ROUTE_IM = 'Intramuscular';
    const ROUTE_SC = 'Subcutaneous';
    const ROUTE_TOPICAL = 'Topical';
    const ROUTE_RECTAL = 'Rectal';
    const ROUTE_INHALATION = 'Inhalation';

    /**
     * Get the case record that owns this drug detail (inverse of morphTo)
     */
    public function caseRecord(): MorphOne
    {
        return $this->morphOne(CaseRecord::class, 'detail');
    }

    /**
     * Get available dosage forms
     */
    public static function getDosageForms(): array
    {
        return [
            self::FORM_TABLET,
            self::FORM_CAPSULE,
            self::FORM_SYRUP,
            self::FORM_INJECTION,
            self::FORM_CREAM,
            self::FORM_OINTMENT,
            self::FORM_DROPS,
            self::FORM_INHALER
        ];
    }

    /**
     * Get available routes of administration
     */
    public static function getRoutes(): array
    {
        return [
            self::ROUTE_ORAL,
            self::ROUTE_IV,
            self::ROUTE_IM,
            self::ROUTE_SC,
            self::ROUTE_TOPICAL,
            self::ROUTE_RECTAL,
            self::ROUTE_INHALATION
        ];
    }
}

