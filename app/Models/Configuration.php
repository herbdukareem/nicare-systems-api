<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'variable_name',
        'variable_value',
        'description'
    ];

    /**
     * Get configuration value by variable name
     */
    public static function getValue(string $variableName, $default = null)
    {
        $config = static::where('variable_name', $variableName)->first();
        return $config ? $config->variable_value : $default;
    }

    /**
     * Set configuration value
     */
    public static function setValue(string $variableName, $value, ?string $description = null)
    {
        return static::updateOrCreate(
            ['variable_name' => $variableName],
            [
                'variable_value' => $value,
                'description' => $description
            ]
        );
    }

    /**
     * Get numeric configuration value
     */
    public static function getNumericValue(string $variableName, $default = 0)
    {
        $value = static::getValue($variableName, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Get percentage configuration value (removes % sign if present)
     */
    public static function getPercentageValue(string $variableName, $default = 0)
    {
        $value = static::getValue($variableName, $default);
        if (is_string($value) && str_ends_with($value, '%')) {
            $value = substr($value, 0, -1);
        }
        return is_numeric($value) ? (float) $value : $default;
    }
}
