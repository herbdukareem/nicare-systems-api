<?php

namespace App\Enums;

use Spatie\Enum\Laravel\Enum;

/**
 * Status enum (Spatie)
 *
 * @method static self PENDING()
 * @method static self ACTIVE()
 * @method static self EXPIRED()
 * @method static self SUSPENDED()
 * @method static self APPROVED()
 * @method static self REJECTED()
 * @method static self CANCELLED()
 * @method static self COMPLETED()
 * @method static self FAILED()
 * @method static self REVIEWED()
 * @method static self PAID()
 * @method static self RECOMMENDED()
 * @method static self REFERRED()
 * @method static self ACTIVATED()
 * @method static self NOTACTIVATED()
 * @method static self DELETED()
 * @method static self USED()
 * @method static self NOTUSED()
 */
final class Status extends Enum
{
    /**
     * Store numeric codes in DB
     */
    protected static function values(): array
    {
        return [
            'PENDING'       => 0,
            'ACTIVE'        => 1,
            'EXPIRED'       => 2,
            'SUSPENDED'     => 3,
            'APPROVED'      => 4,
            'REJECTED'      => 5,
            'CANCELLED'     => 6,
            'COMPLETED'     => 7,
            'FAILED'        => 8,
            'REVIEWED'      => 9,
            'PAID'          => 10,
            'RECOMMENDED'   => 11,
            'REFERRED'      => 12,
            'ACTIVATED'     => 13,
            'NOTACTIVATED'  => 14,
            'DELETED'       => 15,
            'USED'          => 16,
            'NOTUSED'       => 17,
        ];
    }

    /**
     * Human-readable labels
     */
    protected static function labels(): array
    {
        return [
            'PENDING'       => 'pending',
            'ACTIVE'        => 'active',
            'EXPIRED'       => 'expired',
            'SUSPENDED'     => 'suspended',
            'APPROVED'      => 'approved',
            'REJECTED'      => 'rejected',
            'CANCELLED'     => 'cancelled',
            'COMPLETED'     => 'completed',
            'FAILED'        => 'failed',
            'REVIEWED'      => 'reviewed',
            'PAID'          => 'paid',
            'RECOMMENDED'   => 'recommended',
            'REFERRED'      => 'referred',
            'ACTIVATED'     => 'activated',
            'NOTACTIVATED'  => 'not activated',
            'DELETED'       => 'deleted',
            'USED'          => 'used',
            'NOTUSED'       => 'not used',
        ];
    }

    /**
     * Flexible parsing:
     * - integers/strings of the numeric codes (0..17)
     * - exact labels ('active', 'pending', etc.)
     * - enum names/case-insensitive ('ACTIVE', 'active')
     */
    public static function coerce(int|string|null $raw): self
    {
        if ($raw === null) {
            return self::PENDING();
        }

        $v = trim((string) $raw);

        // numeric code
        if (is_numeric($v)) {
            $code = (int) $v;
            // map code to instance by scanning values()
            foreach (self::values() as $name => $value) {
                if ($value === $code) {
                    /** @var self $enum */
                    $enum = \call_user_func([self::class, $name]);
                    return $enum;
                }
            }
            return self::PENDING();
        }

        // match by label or name (case-insensitive)
        $lower = strtolower($v);

        // try labels()
        foreach (self::labels() as $name => $label) {
            if ($lower === strtolower($label)) {
                /** @var self $enum */
                $enum = \call_user_func([self::class, $name]);
                return $enum;
            }
        }

        // try names (e.g. 'ACTIVE', 'notActivated')
        $upper = strtoupper($v);
        if (\array_key_exists($upper, self::values())) {
            /** @var self $enum */
            $enum = \call_user_func([self::class, $upper]);
            return $enum;
        }

        return self::PENDING();
    }

    /**
     * Build a "code => label" array (useful for selects).
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::values() as $name => $value) {
            $out[$value] = self::labels()[$name];
        }
        return $out;
    }

    /**
     * Just the numeric codes (validation helpers)
     */
    public static function toValues(): array
    {
        return array_values(self::values());
    }
}
