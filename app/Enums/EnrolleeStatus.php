<?php

namespace App\Enums;

/**
 * Enum EnrolleeStatus
 *
 * Defines the numeric status codes for an enrollee and provides a label() helper.
 */
enum EnrolleeStatus: int
{
    case PENDING = 0;
    case ACTIVE = 1;
    case EXPIRED = 2;
    case SUSPENDED = 3;

    /**
     * Get the human‑readable label for the enum value.
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'pending',
            self::ACTIVE => 'active',
            self::EXPIRED => 'expired',
            self::SUSPENDED => 'suspended',
        };
    }

    /**
     * Build an array of available codes to labels (e.g. for form select lists).
     */
    public static function options(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::ACTIVE->value => self::ACTIVE->label(),
            self::EXPIRED->value => self::EXPIRED->label(),
            self::SUSPENDED->value => self::SUSPENDED->label(),
        ];
    }
}
