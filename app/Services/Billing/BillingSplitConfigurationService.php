<?php

namespace App\Services\Billing;

use App\Models\PremiumPlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

class BillingSplitConfigurationService
{
    public function __construct(
        private PaymentGatewayConfigurationService $configurationService
    ) {
    }

    public function resolveForPlan(PremiumPlan $plan, string $gatewayCode): ?array
    {
        $profileCode = trim((string) ($plan->payment_split_profile_code ?? ''));
        if ($profileCode === '') {
            return null;
        }

        $profile = collect($this->configurationService->getSplitProfiles())
            ->firstWhere('code', $profileCode);

        if (!$profile) {
            throw new RuntimeException("The split profile [{$profileCode}] is not configured.");
        }

        if (($profile['gateway_code'] ?? null) !== $gatewayCode) {
            throw new RuntimeException("The split profile [{$profileCode}] cannot be used with gateway [{$gatewayCode}].");
        }

        $entries = $this->resolveEntries($profile, $gatewayCode);

        if ($entries->isEmpty()) {
            return null;
        }

        return match ($gatewayCode) {
            'paystack' => $this->paystackSplitPayload($profile, $entries),
            'monnify' => $this->monnifySplitPayload($entries),
            'remita' => $this->remitaSplitPayload($entries),
            default => null,
        };
    }

    private function resolveEntries(array $profile, string $gatewayCode): Collection
    {
        $subaccounts = collect($this->configurationService->getSubaccounts())
            ->keyBy('code');

        return collect(Arr::get($profile, 'entries', []))
            ->map(function (array $entry) use ($subaccounts, $gatewayCode): ?array {
                $localCode = trim((string) ($entry['subaccount_code'] ?? ''));
                $subaccount = $localCode !== '' ? $subaccounts->get($localCode) : null;

                if (!$subaccount) {
                    throw new RuntimeException("The split profile references an unknown subaccount [{$localCode}].");
                }

                if (($subaccount['gateway_code'] ?? null) !== $gatewayCode) {
                    throw new RuntimeException("The subaccount [{$localCode}] does not belong to gateway [{$gatewayCode}].");
                }

                if (!($subaccount['active'] ?? true)) {
                    throw new RuntimeException("The subaccount [{$localCode}] is inactive.");
                }

                $shareType = (string) ($entry['share_type'] ?? 'percentage');
                if (!in_array($shareType, ['percentage', 'flat'], true)) {
                    throw new RuntimeException("Unsupported split share type [{$shareType}].");
                }

                $shareValue = (float) ($entry['share_value'] ?? 0);
                if ($shareValue <= 0) {
                    throw new RuntimeException("Split share value must be greater than zero for subaccount [{$localCode}].");
                }

                $externalCode = trim((string) ($subaccount['external_code'] ?? ''));
                if ($externalCode === '') {
                    throw new RuntimeException("The subaccount [{$localCode}] is missing its external provider code.");
                }

                return [
                    'local_code' => $localCode,
                    'external_code' => $externalCode,
                    'share_type' => $shareType,
                    'share_value' => $shareValue,
                    'fee_bearer' => (bool) ($entry['fee_bearer'] ?? false),
                    'fee_percentage' => (float) ($entry['fee_percentage'] ?? 0),
                ];
            })
            ->filter()
            ->values();
    }

    private function paystackSplitPayload(array $profile, Collection $entries): array
    {
        $shareTypes = $entries->pluck('share_type')->unique()->values();
        if ($shareTypes->count() !== 1) {
            throw new RuntimeException('Paystack split profiles must use a single share type across all entries.');
        }

        $shareType = $shareTypes->first() === 'flat' ? 'flat' : 'percentage';
        $paystack = Arr::get($profile, 'settings.paystack', []);
        $bearerType = (string) ($paystack['bearer_type'] ?? 'account');
        $bearerSubaccount = trim((string) ($paystack['bearer_subaccount_code'] ?? ''));

        $split = [
            'type' => $shareType,
            'bearer_type' => $bearerType,
            'subaccounts' => $entries->map(fn (array $entry): array => [
                'subaccount' => $entry['external_code'],
                'share' => $shareType === 'flat'
                    ? (int) round($entry['share_value'])
                    : (float) $entry['share_value'],
            ])->values()->all(),
        ];

        if ($bearerType === 'subaccount' && $bearerSubaccount !== '') {
            $split['bearer_subaccount'] = $this->resolveExternalCode($entries, $bearerSubaccount);
        }

        return ['split' => $split];
    }

    private function monnifySplitPayload(Collection $entries): array
    {
        if ($entries->count() > 5) {
            throw new RuntimeException('Monnify supports a maximum of five split entries per transaction.');
        }

        return [
            'incomeSplitConfig' => $entries->map(function (array $entry): array {
                $payload = [
                    'subAccountCode' => $entry['external_code'],
                    'feePercentage' => $entry['fee_percentage'],
                    'feeBearer' => $entry['fee_bearer'],
                ];

                if ($entry['share_type'] === 'flat') {
                    $payload['splitAmount'] = $entry['share_value'];
                } else {
                    $payload['splitPercentage'] = $entry['share_value'];
                }

                return $payload;
            })->values()->all(),
        ];
    }

    private function remitaSplitPayload(Collection $entries): array
    {
        if ($entries->contains(fn (array $entry) => $entry['share_type'] !== 'flat')) {
            throw new RuntimeException('Remita split profiles currently support flat share amounts only.');
        }

        $feeBearers = $entries->where('fee_bearer', true)->values();
        if ($feeBearers->count() > 1) {
            throw new RuntimeException('Only one Remita split entry can be marked as the fee bearer.');
        }

        $split = [
            'subAccountIds' => $entries->map(fn (array $entry): array => [
                'subAccountId' => $entry['external_code'],
                'share' => (int) round($entry['share_value']),
            ])->values()->all(),
        ];

        if ($feeBearers->isNotEmpty()) {
            $split['bearerSubAccountId'] = $feeBearers->first()['external_code'];
        }

        return ['split' => $split];
    }

    private function resolveExternalCode(Collection $entries, string $localCode): string
    {
        $entry = $entries->firstWhere('local_code', $localCode);
        if (!$entry) {
            throw new RuntimeException("The configured bearer subaccount [{$localCode}] is not part of the split profile.");
        }

        return (string) $entry['external_code'];
    }
}
