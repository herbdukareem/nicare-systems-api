<?php

namespace App\Enums;

/**
 * Enum Gender
 *
 * Defines the numeric gender codes for a user and provides a label() helper.
 */
enum Gender: int
{
    case MALE = 1;
    case FEMALE = 2;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::MALE => 'Male',
            self::FEMALE => 'Female'
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::MALE->value => self::MALE->label(),
            self::FEMALE->value => self::FEMALE->label(),
        ];
    }
}
