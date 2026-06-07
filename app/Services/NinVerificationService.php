<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NinVerificationService
{
    /**
     * @var array<int, string>
     */
    private array $comparableFields = [
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'phone',
    ];

    public function __construct(private readonly NinProviderConfigService $configService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(Enrollee $enrollee, User $verifiedBy, bool $consent = true): array
    {
        if (blank($enrollee->nin)) {
            throw new RuntimeException('This enrollee does not have a NIN to verify.');
        }

        $config = $this->configService->getConfig();

        if (!$config['enabled']) {
            throw new RuntimeException('NIN verification is not enabled. Configure and enable a provider first.');
        }

        if (blank($config['base_url']) || blank($config['verify_endpoint']) || blank($config['api_key'])) {
            throw new RuntimeException('NIN verification is incomplete. Base URL, endpoint, and API key are required.');
        }

        $payload = [
            $config['request_nin_field'] => $enrollee->nin,
            $config['request_consent_field'] => $consent,
        ];

        $request = Http::baseUrl(rtrim((string) $config['base_url'], '/'))
            ->timeout((int) $config['timeout_seconds'])
            ->acceptJson()
            ->asJson();

        if (($config['auth_type'] ?? 'bearer') === 'bearer') {
            $request = $request->withToken((string) $config['api_key']);
        }

        try {
            $response = strtoupper((string) $config['request_method']) === 'GET'
                ? $request->get(ltrim((string) $config['verify_endpoint'], '/'), $payload)
                : $request->post(ltrim((string) $config['verify_endpoint'], '/'), $payload);
        } catch (Throwable $exception) {
            $this->markFailed($enrollee, $verifiedBy, $config, [
                'message' => 'The NIN verification request could not be completed.',
                'http_status' => method_exists($exception, 'response') ? $exception->response()?->status() : null,
            ]);

            throw new RuntimeException('The NIN verification provider could not be reached. Try again later.');
        }

        $body = $response->json() ?? [];
        $success = (bool) data_get($body, (string) $config['success_path'], false);
        $providerData = data_get($body, (string) $config['data_path'], []);

        if (!$response->successful() || !$success || !is_array($providerData)) {
            $this->markFailed($enrollee, $verifiedBy, $config, [
                'message' => data_get($body, 'message', 'The NIN provider did not return a successful verification response.'),
                'http_status' => $response->status(),
            ]);

            throw new RuntimeException((string) data_get($body, 'message', 'Unable to verify NIN with the configured provider.'));
        }

        $normalized = $this->normalizeProviderData($providerData, (array) $config['field_map']);
        $comparison = $this->buildComparison($enrollee, $normalized);

        $enrollee->forceFill([
            'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            'nin_verified_at' => now(),
            'nin_verified_by' => $verifiedBy->id,
            'nin_verification_provider' => $config['provider_name'],
            'nin_verification_data' => [
                'provider_data' => $normalized,
                'comparison' => $comparison,
                'verified_nin' => $normalized['nin'] ?? $enrollee->nin,
            ],
            'nin_verification_meta' => [
                'provider_name' => $config['provider_name'],
                'verified_at' => now()->toIso8601String(),
                'http_status' => $response->status(),
            ],
        ])->save();

        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id' => $enrollee->id,
            'action' => 'nin_verified',
            'description' => 'NIN verification completed for enrollee approval.',
            'user_id' => $verifiedBy->id,
            'new_values' => [
                'provider' => $config['provider_name'],
                'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            ],
        ]);

        return [
            'status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            'provider_name' => $config['provider_name'],
            'verified_at' => $enrollee->fresh()->nin_verified_at,
            'provider_data' => $normalized,
            'comparison' => $comparison,
        ];
    }

    /**
     * @param  array<string, mixed>  $approvalData
     */
    public function applyApprovalSelection(Enrollee $enrollee, array $approvalData, User $approvedBy): Enrollee
    {
        if (blank($enrollee->nin)) {
            $enrollee->forceFill([
                'nin_verification_status' => Enrollee::NIN_VERIFICATION_NOT_PROVIDED,
                'nin_verification_provider' => null,
            ])->save();

            return $enrollee->fresh();
        }

        if ($enrollee->nin_verification_status !== Enrollee::NIN_VERIFICATION_VERIFIED) {
            throw new RuntimeException('Verify this enrollee\'s NIN before approval.');
        }

        $verificationData = $enrollee->nin_verification_data ?? [];
        $providerData = is_array($verificationData['provider_data'] ?? null) ? $verificationData['provider_data'] : [];
        $strategy = (string) ($approvalData['nin_merge_strategy'] ?? 'keep_provided');
        $fieldSelection = is_array($approvalData['nin_field_selection'] ?? null) ? $approvalData['nin_field_selection'] : [];

        $updates = [];
        $resolvedProfile = [];
        $resolvedSelection = [];

        foreach ($this->comparableFields as $field) {
            $selectedSource = $this->selectedSourceForField($strategy, $fieldSelection, $field, $providerData);
            $resolvedSelection[$field] = $selectedSource;

            $resolvedValue = $selectedSource === 'verified'
                ? ($providerData[$field] ?? $this->currentValue($enrollee, $field))
                : $this->currentValue($enrollee, $field);

            $resolvedProfile[$field] = $resolvedValue;

            if ($selectedSource === 'verified') {
                $this->writeResolvedField($updates, $field, $resolvedValue);
            }
        }

        if (!blank($providerData['nin'] ?? null)) {
            $updates['nin'] = (string) $providerData['nin'];
        }

        $meta = is_array($enrollee->nin_verification_meta) ? $enrollee->nin_verification_meta : [];
        $meta['approval_selection'] = [
            'strategy' => $strategy,
            'fields' => $resolvedSelection,
            'resolved_profile' => $resolvedProfile,
            'applied_at' => now()->toIso8601String(),
            'applied_by' => $approvedBy->id,
        ];

        $enrollee->fill($updates);
        $enrollee->nin_verification_meta = $meta;
        $enrollee->save();

        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id' => $enrollee->id,
            'action' => 'nin_verification_applied',
            'description' => 'Approval officer applied NIN verification comparison choices.',
            'user_id' => $approvedBy->id,
            'new_values' => [
                'strategy' => $strategy,
                'fields' => $resolvedSelection,
            ],
        ]);

        return $enrollee->fresh();
    }

    /**
     * @param  array<string, mixed>  $providerData
     * @return array<string, mixed>
     */
    private function normalizeProviderData(array $providerData, array $fieldMap): array
    {
        $normalized = [];

        foreach ($fieldMap as $internalField => $providerPath) {
            $value = data_get($providerData, (string) $providerPath);
            if ($value === null) {
                continue;
            }

            $normalized[$internalField] = $internalField === 'gender'
                ? $this->normalizeGender($value)
                : (is_string($value) ? trim($value) : $value);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $providerData
     * @return array<int, array<string, mixed>>
     */
    private function buildComparison(Enrollee $enrollee, array $providerData): array
    {
        $comparison = [];

        foreach ($this->comparableFields as $field) {
            $provided = $this->currentValue($enrollee, $field);
            $verified = $providerData[$field] ?? null;

            $comparison[] = [
                'field' => $field,
                'label' => Str::of($field)->replace('_', ' ')->title()->toString(),
                'provided' => $provided,
                'verified' => $verified,
                'matches' => $this->valuesMatch($provided, $verified),
                'recommended_source' => blank($verified) ? 'provided' : 'verified',
            ];
        }

        return $comparison;
    }

    private function valuesMatch(mixed $provided, mixed $verified): bool
    {
        if ($provided === null && $verified === null) {
            return true;
        }

        return Str::lower((string) $provided) === Str::lower((string) $verified);
    }

    private function currentValue(Enrollee $enrollee, string $field): mixed
    {
        return match ($field) {
            'gender' => $this->genderLabel($enrollee->sex),
            'date_of_birth' => optional($enrollee->date_of_birth)->toDateString(),
            default => $enrollee->{$field},
        };
    }

    private function writeResolvedField(array &$updates, string $field, mixed $value): void
    {
        if (blank($value)) {
            return;
        }

        if ($field === 'gender') {
            $mapped = $this->mapGenderToSex($value);
            if ($mapped !== null) {
                $updates['sex'] = $mapped;
            }

            return;
        }

        $updates[$field] = $field === 'date_of_birth' ? $value : (string) $value;
    }

    private function selectedSourceForField(string $strategy, array $fieldSelection, string $field, array $providerData): string
    {
        if ($strategy === 'manual' && isset($fieldSelection[$field])) {
            return $fieldSelection[$field] === 'verified' ? 'verified' : 'provided';
        }

        if ($strategy === 'prefer_verified') {
            return blank($providerData[$field] ?? null) ? 'provided' : 'verified';
        }

        return 'provided';
    }

    private function normalizeGender(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::lower(trim((string) $value));

        return match ($normalized) {
            'm', 'male' => 'Male',
            'f', 'female' => 'Female',
            default => Str::title($normalized),
        };
    }

    private function mapGenderToSex(mixed $value): ?int
    {
        return match ($this->normalizeGender($value)) {
            'Male' => 1,
            'Female' => 2,
            default => null,
        };
    }

    private function genderLabel(mixed $sex): ?string
    {
        return match ((int) $sex) {
            1 => 'Male',
            2 => 'Female',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function markFailed(Enrollee $enrollee, User $verifiedBy, array $config, array $meta): void
    {
        $enrollee->forceFill([
            'nin_verification_status' => Enrollee::NIN_VERIFICATION_FAILED,
            'nin_verified_at' => now(),
            'nin_verified_by' => $verifiedBy->id,
            'nin_verification_provider' => $config['provider_name'] ?? null,
            'nin_verification_data' => null,
            'nin_verification_meta' => array_merge([
                'provider_name' => $config['provider_name'] ?? null,
                'verified_at' => now()->toIso8601String(),
            ], $meta),
        ])->save();

        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id' => $enrollee->id,
            'action' => 'nin_verification_failed',
            'description' => 'NIN verification failed during enrollee approval.',
            'user_id' => $verifiedBy->id,
            'new_values' => [
                'provider' => $config['provider_name'] ?? null,
                'nin_verification_status' => Enrollee::NIN_VERIFICATION_FAILED,
            ],
        ]);
    }
}
