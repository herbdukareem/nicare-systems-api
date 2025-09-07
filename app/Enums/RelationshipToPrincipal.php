<?php

namespace App\Enums;

/**
 * Enum RelationshipToPrincipal
 *
 * Defines the numeric status codes for an enrollee and provides a label() helper.
 */
enum RelationshipToPrincipal: int
{
    case PRINCIPAL = 1;
    case SPOUSE = 2;
    case CHILD = 3;
    case OTHER = 4;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::PRINCIPAL => 'Principal',
            self::SPOUSE => 'Spouse',
            self::CHILD => 'Child',
            self::OTHER => 'Other'
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::PRINCIPAL->value => self::PRINCIPAL->label(),
            self::SPOUSE->value => self::SPOUSE->label(),
            self::CHILD->value => self::CHILD->label(),
            self::OTHER->value => self::OTHER->label(),
        ];
    }
}
