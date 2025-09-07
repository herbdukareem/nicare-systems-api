<?php

namespace App\Enums;

/**
 * Enum MaritalStatus
 *
 * Defines the numeric status codes for an enrollee and provides a label() helper.
 */
enum MaritalStatus: int
{
    case SINGLE = 1;
    case MARRIED = 2;
    case DIVORCED = 3;
    case WIDOWED = 4;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::SINGLE => 'Single',
            self::MARRIED => 'Married',
            self::DIVORCED => 'Divorced',
            self::WIDOWED => 'Widowed'
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::SINGLE->value => self::SINGLE->label(),
            self::MARRIED->value => self::MARRIED->label(),
            self::DIVORCED->value => self::DIVORCED->label(),
            self::WIDOWED->value => self::WIDOWED->label(),
        ];
    }
}
