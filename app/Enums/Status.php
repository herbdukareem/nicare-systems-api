<?php

namespace App\Enums;

/**
 * Enum Status
 *
 * Defines the numeric status codes for a user and provides a label() helper.
 */
enum Status: int
{
    case PENDING = 0;
    case ACTIVE = 1;
    case EXPIRED = 2;
    case SUSPENDED = 3;
    case APPROVED = 4;
    case REJECTED = 5;
    case CANCELLED = 6;
    case COMPLETED = 7;
    case FAILED = 8;
    case REVIEWED = 9;
    case PAID = 10;
    case RECOMMENDED = 11;
    case REFERRED = 12;
    case ACTIVATED = 13;
    case NOTACTIVATED = 14;
    case DELETED = 15;

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
            self::APPROVED => 'approved',
            self::REJECTED => 'rejected',
            self::CANCELLED => 'cancelled',
            self::COMPLETED => 'completed',
            self::FAILED => 'failed',
            self::REVIEWED => 'reviewed',
            self::PAID => 'paid',
            self::RECOMMENDED => 'recommended',
            self::REFERRED => 'referred',
            self::ACTIVATED => 'activated',
            self::NOTACTIVATED => 'not activated',
            self::DELETED => 'deleted',
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
            self::APPROVED->value => self::APPROVED->label(),
            self::REJECTED->value => self::REJECTED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
            self::COMPLETED->value => self::COMPLETED->label(),
            self::FAILED->value => self::FAILED->label(),
            self::REVIEWED->value => self::REVIEWED->label(),
            self::PAID->value => self::PAID->label(),
            self::RECOMMENDED->value => self::RECOMMENDED->label(),
            self::REFERRED->value => self::REFERRED->label(),
            self::ACTIVATED->value => self::ACTIVATED->label(),
            self::NOTACTIVATED->value => self::NOTACTIVATED->label(),
            self::DELETED->value => self::DELETED->label(),
        ];
    }
}
