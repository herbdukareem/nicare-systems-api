<?php

namespace App\Support;

use Illuminate\Support\Str;

final class LegacyReferenceData
{
    /**
     * Legacy table name is misspelled as funidng_types. Keep the source values
     * here so seeders and migrations resolve the same codes every time.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fundingTypes(): array
    {
        return [
            1 => ['legacy_id' => 1, 'name' => 'Basic Healthcare Provision Fund', 'code' => 'BHCPF', 'code2' => 'bhcpf', 'status' => 1],
            2 => ['legacy_id' => 2, 'name' => 'Counterpart Funding', 'code' => 'BHCPF-CF', 'code2' => 'cf', 'status' => 1],
            3 => ['legacy_id' => 3, 'name' => 'Premium', 'code' => 'NiCare', 'code2' => 'premium', 'status' => 1],
            4 => ['legacy_id' => 4, 'name' => 'GAC', 'code' => 'GAC', 'code2' => 'gac', 'status' => 1],
            5 => ['legacy_id' => 5, 'name' => 'UNICEF', 'code' => 'UNICEF', 'code2' => 'unicef', 'status' => 1],
            6 => ['legacy_id' => 6, 'name' => 'Formal Sector Deduction', 'code' => 'formal', 'code2' => 'formal', 'status' => 1],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function benefactors(): array
    {
        return [
            1 => ['legacy_id' => 1, 'funding_type_legacy_id' => 3, 'funding' => null, 'name' => 'Peculiar Cooperative', 'show_on_device' => 0, 'enrollee_type' => 'informal', 'phase' => 7, 'status' => 1, 'type' => 'cooperative'],
            2 => ['legacy_id' => 2, 'funding_type_legacy_id' => 1, 'funding' => 'bhcpf', 'name' => 'BHCPF', 'show_on_device' => 1, 'enrollee_type' => 'bhcpf', 'phase' => 7, 'status' => 1, 'type' => 'government'],
            3 => ['legacy_id' => 3, 'funding_type_legacy_id' => 1, 'funding' => null, 'name' => 'NGSCHA', 'show_on_device' => 0, 'enrollee_type' => 'informal', 'phase' => 7, 'status' => 1, 'type' => 'government'],
            4 => ['legacy_id' => 4, 'funding_type_legacy_id' => 3, 'funding' => 'gac', 'name' => 'BHCPF-GAC', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'government'],
            5 => ['legacy_id' => 5, 'funding_type_legacy_id' => 4, 'funding' => null, 'name' => 'Dr. MM Makusidi', 'show_on_device' => 0, 'enrollee_type' => 'informal', 'phase' => 7, 'status' => 1, 'type' => 'donor'],
            6 => ['legacy_id' => 6, 'funding_type_legacy_id' => 1, 'funding' => null, 'name' => 'Nigeria For Women', 'show_on_device' => 0, 'enrollee_type' => 'informal', 'phase' => 7, 'status' => 1, 'type' => 'donor'],
            7 => ['legacy_id' => 7, 'funding_type_legacy_id' => 3, 'funding' => null, 'name' => 'Self', 'show_on_device' => 1, 'enrollee_type' => 'informal', 'phase' => 7, 'status' => 1, 'type' => 'individual'],
            8 => ['legacy_id' => 8, 'funding_type_legacy_id' => 5, 'funding' => 'unicef', 'name' => 'UNICEF', 'show_on_device' => 0, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'donor'],
            9 => ['legacy_id' => 9, 'funding_type_legacy_id' => 1, 'funding' => 'cf', 'name' => 'BHCPF-COUNTERPART', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'government'],
            10 => ['legacy_id' => 10, 'funding_type_legacy_id' => 1, 'funding' => 'cf', 'name' => 'SACA & PARTNERS', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'donor'],
            11 => ['legacy_id' => 11, 'funding_type_legacy_id' => 1, 'funding' => 'cf', 'name' => 'NS RETIREES', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'government'],
            12 => ['legacy_id' => 12, 'funding_type_legacy_id' => 1, 'funding' => 'cf', 'name' => 'CCCRN', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'donor'],
            13 => ['legacy_id' => 13, 'funding_type_legacy_id' => 1, 'funding' => 'cf', 'name' => 'Hon. Sani BT Tafa', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'donor'],
            14 => ['legacy_id' => 14, 'funding_type_legacy_id' => 1, 'funding' => 'cf', 'name' => 'DG SME', 'show_on_device' => 1, 'enrollee_type' => 'others', 'phase' => 7, 'status' => 1, 'type' => 'government'],
            100 => ['legacy_id' => 100, 'funding_type_legacy_id' => 1, 'funding' => 'formal', 'name' => 'Formal Sector', 'show_on_device' => 0, 'enrollee_type' => 'formal', 'phase' => 7, 'status' => 1, 'type' => 'employer'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function vulnerableGroups(): array
    {
        return [
            'others' => ['name' => 'Others', 'code' => 'others', 'status' => 1],
            'female_reproductive_15_45_years' => ['name' => 'Female Reproductive (15-45 years)', 'code' => 'female_reproductive_15_45_years', 'status' => 1],
            'elderly_85_and_above' => ['name' => 'Elderly (85 and above)', 'code' => 'elderly_85_and_above', 'status' => 1],
            'children_under_5yrs' => ['name' => 'Children under 5yrs', 'code' => 'children_under_5yrs', 'status' => 1],
            'normal' => ['name' => 'Normal', 'code' => 'normal', 'status' => 1],
        ];
    }

    public static function fundingTypeByLegacyValue(mixed $value): ?array
    {
        $key = self::normalize($value);
        if ($key === null) {
            return self::fundingTypes()[3];
        }

        foreach (self::fundingTypes() as $fundingType) {
            $aliases = [
                (string) $fundingType['legacy_id'],
                $fundingType['name'],
                $fundingType['code'],
                $fundingType['code2'],
            ];

            if (in_array($key, array_map([self::class, 'normalize'], $aliases), true)) {
                return $fundingType;
            }
        }

        return null;
    }

    public static function benefactorByLegacyValue(mixed $value): ?array
    {
        $key = self::normalize($value);
        if ($key === null) {
            return null;
        }

        foreach (self::benefactors() as $benefactor) {
            $aliases = [
                (string) $benefactor['legacy_id'],
                $benefactor['name'],
                $benefactor['funding'],
            ];

            if (in_array($key, array_map([self::class, 'normalize'], array_filter($aliases, fn ($alias) => $alias !== null)), true)) {
                return $benefactor;
            }
        }

        return null;
    }

    public static function benefactorByFundingValue(mixed $value): ?array
    {
        $fundingType = self::fundingTypeByLegacyValue($value);
        if (!$fundingType) {
            return null;
        }

        foreach (self::benefactors() as $benefactor) {
            if (($benefactor['funding'] ?? null) === $fundingType['code2']) {
                return $benefactor;
            }
        }

        return null;
    }

    public static function vulnerableGroupByLegacyValue(mixed $value): ?array
    {
        $key = self::normalize($value);
        if ($key === null) {
            return null;
        }

        foreach (self::vulnerableGroups() as $group) {
            if (in_array($key, [self::normalize($group['name']), self::normalize($group['code'])], true)) {
                return $group;
            }
        }

        return null;
    }

    private static function normalize(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || in_array(strtolower($value), ['null', 'none', 'nil', 'n/a'], true)) {
            return null;
        }

        return Str::of($value)->lower()->replace("\xc2\xa0", ' ')->squish()->toString();
    }
}
