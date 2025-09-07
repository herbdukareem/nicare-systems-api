<?php

namespace App\Enums;

/**
 * Enum Ownership
 *
 * Defines the numeric ownership codes for a facility and provides a label() helper
 * for the ownership field.
 */
enum Ownership: int
{
    case PUBLIC = 1;
    case PRIVATE = 2;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::PUBLIC => 'Public',
            self::PRIVATE => 'Private',
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::PUBLIC->value => self::PUBLIC->label(),
            self::PRIVATE->value => self::PRIVATE->label(),
        ];
    }
}
