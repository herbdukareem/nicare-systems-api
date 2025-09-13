<?php
namespace App\Enums;

use Spatie\Enum\Laravel\Enum;

/**
 * @method static self RURAL()
 * @method static self URBAN()
 */
final class Settlement extends Enum
{
    // store as integers in DB
    protected static function values(): array
    {
        return [
            'RURAL' => 1,
            'URBAN' => 2,
        ];
    }

    // human-readable labels
    protected static function labels(): array
    {
        return [
            'RURAL' => 'Rural',
            'URBAN' => 'Urban',
        ];
    }

    /** Accepts 1/2, "1"/"2", "rural"/"urban", "R"/"U", null (defaults to Rural) */
    public static function coerce(int|string|null $raw): self
    {
        if ($raw === null) return self::RURAL();
        $v = strtolower(trim((string)$raw));

        if (is_numeric($v)) {
            return match ((int)$v) {
                1 => self::RURAL(),
                2 => self::URBAN(),
                default => self::RURAL(),
            };
        }

        return match ($v) {
            'r', 'rural' => self::RURAL(),
            'u', 'urban' => self::URBAN(),
            default => self::RURAL(),
        };
    }
}
