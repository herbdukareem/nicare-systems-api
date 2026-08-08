<?php

namespace App\Services\Billing;

/**
 * Calculates a transparent customer checkout surcharge from a gateway's
 * configured commercial terms. Amounts are handled in kobo to avoid rounding
 * drift between the quoted and submitted amount.
 */
class CheckoutFeeQuote
{
    public static function for(float|int|string $baseAmount, array $configuration): array
    {
        $baseKobo = max(0, (int) round(((float) $baseAmount) * 100));
        $policy = (array) ($configuration['checkout_fee_policy'] ?? []);

        if (!(bool) ($policy['customer_bears_processing_fee'] ?? false)) {
            return self::present($baseKobo, $baseKobo);
        }

        $percentage = max(0, (float) ($policy['percentage'] ?? 0));
        $flatKobo = max(0, (int) round(((float) ($policy['flat_amount'] ?? 0)) * 100));
        $maximumKobo = max(0, (int) round(((float) ($policy['maximum_amount'] ?? 0)) * 100));

        // Find the smallest amount whose post-fee settlement meets the plan price.
        // This is robust for capped fee policies and provider rounding to kobo.
        $totalKobo = $baseKobo;
        do {
            $feeKobo = self::feeFor($totalKobo, $percentage, $flatKobo, $maximumKobo);
            if ($totalKobo - $feeKobo >= $baseKobo) {
                return self::present($baseKobo, $totalKobo);
            }
            $totalKobo++;
        } while (true);
    }

    private static function feeFor(int $totalKobo, float $percentage, int $flatKobo, int $maximumKobo): int
    {
        $feeKobo = (int) round(($totalKobo * $percentage) / 100) + $flatKobo;

        return $maximumKobo > 0 ? min($feeKobo, $maximumKobo) : $feeKobo;
    }

    private static function present(int $baseKobo, int $customerTotalKobo): array
    {
        return [
            'base_amount' => round($baseKobo / 100, 2),
            'processing_fee' => round(($customerTotalKobo - $baseKobo) / 100, 2),
            'customer_total' => round($customerTotalKobo / 100, 2),
        ];
    }
}
