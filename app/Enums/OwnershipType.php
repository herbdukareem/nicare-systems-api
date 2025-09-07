<?php

namespace App\Enums;

/**
 * Enum OwnershipType
 *
 * Defines the numeric ownership_type codes for a facility and provides a label() helper.
 */
enum OwnershipType: int
{
    case PRIMARY = 1;
    case SECONDARY = 2;
    case TERTiARY = 3;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::PRIMARY => 'Primary',
            self::SECONDARY => 'Secondary',
            self::TERTiARY => 'Tertiary'
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::PRIMARY->value => self::PRIMARY->label(),
            self::SECONDARY->value => self::SECONDARY->label(),
            self::TERTiARY->value => self::TERTiARY->label(),
        ];
    }
}
