<?php

namespace App\Enums;

/**
 * Enum SettleType
 *
 * Defines the numeric status codes for an enrollee and provides a label() helper.
 */
enum Settlement: int
{
    case RURAL = 1;
    case URBAN = 2;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::RURAL => 'Rural',
            self::URBAN => 'Urban'
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::RURAL->value => self::RURAL->label(),
            self::URBAN->value => self::URBAN->label(),
        ];
    }
}
