<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\NinVerificationCache;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        if ($cached = $this->cachedVerification((string) $enrollee->nin, $config)) {
            return $this->applyCachedVerification($enrollee, $verifiedBy, $cached, $config);
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
        $this->logProviderResponse($enrollee, $config, $payload, $response->status(), $body);

        $success = $this->providerSucceeded($body, $config, $response->status());
        $providerData = $this->extractProviderData($body, $config);

        if (!$response->successful() || !$success || !is_array($providerData)) {
            $message = $this->providerErrorMessage($body, 'The NIN provider did not return a successful verification response.');

            $this->markFailed($enrollee, $verifiedBy, $config, [
                'message' => $message,
                'http_status' => $response->status(),
                'provider_response_excerpt' => $this->excerptProviderResponse($body),
            ]);

            throw new RuntimeException($message);
        }

        $normalized = $this->normalizeProviderData($providerData, (array) $config['field_map']);

        if ($normalized === []) {
            $normalized = $this->normalizeProviderData($providerData, $this->configService->defaultFieldMap());
        }

        if ($normalized === [] && $this->looksLikeProviderPayload($body)) {
            $normalized = $this->normalizeProviderData($body, $this->configService->defaultFieldMap());
        }

        if ($normalized === []) {
            $this->markFailed($enrollee, $verifiedBy, $config, [
                'message' => 'The NIN provider returned a successful response, but no usable enrollee fields were found.',
                'http_status' => $response->status(),
                'provider_response_excerpt' => $this->excerptProviderResponse($body),
            ]);

            throw new RuntimeException('The NIN provider response did not contain the expected enrollee fields. Review the provider configuration and logs.');
        }

        $this->rememberVerification((string) $enrollee->nin, $config, $normalized, $body);

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
                'provider_response_excerpt' => $this->excerptProviderResponse($body),
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
            'cached' => false,
        ];
    }

    /**
     * Verify a NIN before an enrollee record exists, for live mobile capture.
     *
     * @return array<string, mixed>
     */
    public function verifyRaw(string $nin, User $verifiedBy, bool $consent = true): array
    {
        if (blank($nin)) {
            throw new RuntimeException('Enter a NIN to verify.');
        }

        $config = $this->configService->getConfig();

        if (!$config['enabled']) {
            throw new RuntimeException('NIN verification is not enabled. Configure and enable a provider first.');
        }

        if (blank($config['base_url']) || blank($config['verify_endpoint']) || blank($config['api_key'])) {
            throw new RuntimeException('NIN verification is incomplete. Base URL, endpoint, and API key are required.');
        }

        if ($cached = $this->cachedVerification($nin, $config)) {
            AuditTrail::create([
                'auditable_type' => User::class,
                'auditable_id' => $verifiedBy->id,
                'action' => 'mobile_nin_cache_used',
                'description' => 'Mobile officer reused cached live NIN verification data before enrollment sync.',
                'user_id' => $verifiedBy->id,
                'new_values' => [
                    'provider' => $cached->provider_name,
                    'nin' => $nin,
                ],
            ]);

            return [
                'status' => Enrollee::NIN_VERIFICATION_VERIFIED,
                'provider_name' => $cached->provider_name ?: $config['provider_name'],
                'verified_at' => optional($cached->verified_at)->toIso8601String() ?: now()->toIso8601String(),
                'provider_data' => (array) $cached->provider_data,
                'verified_nin' => data_get($cached->provider_data, 'nin', $nin),
                'cached' => true,
            ];
        }

        $payload = [
            $config['request_nin_field'] => $nin,
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
        } catch (Throwable) {
            throw new RuntimeException('The NIN verification provider could not be reached. Try again later.');
        }

        $body = $response->json() ?? [];
        $this->logRawProviderResponse($verifiedBy, $config, $payload, $response->status(), $body);

        $success = $this->providerSucceeded($body, $config, $response->status());
        $providerData = $this->extractProviderData($body, $config);

        if (!$response->successful() || !$success || !is_array($providerData)) {
            throw new RuntimeException($this->providerErrorMessage($body, 'Unable to verify NIN with the configured provider.'));
        }

        $normalized = $this->normalizeProviderData($providerData, (array) $config['field_map']);

        if ($normalized === []) {
            $normalized = $this->normalizeProviderData($providerData, $this->configService->defaultFieldMap());
        }

        if ($normalized === [] && $this->looksLikeProviderPayload($body)) {
            $normalized = $this->normalizeProviderData($body, $this->configService->defaultFieldMap());
        }

        if ($normalized === []) {
            throw new RuntimeException('The NIN provider response did not contain the expected enrollee fields. Review the provider configuration and logs.');
        }

        $this->rememberVerification($nin, $config, $normalized, $body);

        AuditTrail::create([
            'auditable_type' => User::class,
            'auditable_id' => $verifiedBy->id,
            'action' => 'mobile_nin_verified',
            'description' => 'Mobile officer completed live NIN verification before enrollment sync.',
            'user_id' => $verifiedBy->id,
            'new_values' => [
                'provider' => $config['provider_name'],
                'nin' => $nin,
            ],
        ]);

        return [
            'status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            'provider_name' => $config['provider_name'],
            'verified_at' => now()->toIso8601String(),
            'provider_data' => $normalized,
            'verified_nin' => $normalized['nin'] ?? $nin,
            'cached' => false,
        ];
    }

    private function cachedVerification(string $nin, array $config): ?NinVerificationCache
    {
        $normalizedNin = preg_replace('/\D+/', '', $nin) ?: $nin;

        $cache = NinVerificationCache::query()
            ->where('nin', $normalizedNin)
            ->first();

        if (!$cache || (array) $cache->provider_data === []) {
            return null;
        }

        $cache->forceFill([
            'last_used_at' => now(),
            'hit_count' => (int) $cache->hit_count + 1,
        ])->save();

        return $cache->fresh();
    }

    private function rememberVerification(string $nin, array $config, array $providerData, array $rawResponse): NinVerificationCache
    {
        $normalizedNin = preg_replace('/\D+/', '', (string) ($providerData['nin'] ?? $nin)) ?: $nin;

        return NinVerificationCache::updateOrCreate(
            ['nin' => $normalizedNin],
            [
                'provider_name' => $config['provider_name'] ?? null,
                'provider_data' => $providerData,
                'raw_response' => $rawResponse,
                'verified_at' => now(),
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function applyCachedVerification(Enrollee $enrollee, User $verifiedBy, NinVerificationCache $cache, array $config): array
    {
        $providerData = (array) $cache->provider_data;
        $comparison = $this->buildComparison($enrollee, $providerData);

        $enrollee->forceFill([
            'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            'nin_verified_at' => $cache->verified_at ?? now(),
            'nin_verified_by' => $verifiedBy->id,
            'nin_verification_provider' => $cache->provider_name ?: $config['provider_name'],
            'nin_verification_data' => [
                'provider_data' => $providerData,
                'comparison' => $comparison,
                'verified_nin' => $providerData['nin'] ?? $enrollee->nin,
                'cached' => true,
            ],
            'nin_verification_meta' => [
                'provider_name' => $cache->provider_name ?: $config['provider_name'],
                'verified_at' => optional($cache->verified_at)->toIso8601String() ?: now()->toIso8601String(),
                'cache_id' => $cache->id,
                'cache_hit_count' => $cache->hit_count,
            ],
        ])->save();

        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id' => $enrollee->id,
            'action' => 'nin_verification_cache_used',
            'description' => 'Cached NIN verification data reused for enrollee approval.',
            'user_id' => $verifiedBy->id,
            'new_values' => [
                'provider' => $cache->provider_name ?: $config['provider_name'],
                'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            ],
        ]);

        return [
            'status' => Enrollee::NIN_VERIFICATION_VERIFIED,
            'provider_name' => $cache->provider_name ?: $config['provider_name'],
            'verified_at' => $enrollee->fresh()->nin_verified_at,
            'provider_data' => $providerData,
            'comparison' => $comparison,
            'cached' => true,
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
            if (blank($providerPath)) {
                continue;
            }

            $value = data_get($providerData, (string) $providerPath);
            if ($value === null) {
                $value = $this->resolveAliasValue($providerData, $internalField);
            }

            if ($value === null) {
                continue;
            }

            $normalized[$internalField] = $this->normalizeProviderFieldValue($internalField, $value);
        }

        return $normalized;
    }

    private function normalizeProviderFieldValue(string $internalField, mixed $value): mixed
    {
        if ($internalField === 'gender') {
            return $this->normalizeGender($value);
        }

        if ($internalField === 'address') {
            return $this->formatProviderAddress($value);
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function formatProviderAddress(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (!is_array($value)) {
            return null;
        }

        $parts = array_filter([
            $value['addressLine'] ?? null,
            $value['town'] ?? null,
            $value['city'] ?? null,
            $value['lga'] ?? null,
            $value['state'] ?? null,
        ], fn ($part) => is_scalar($part) && trim((string) $part) !== '');

        $parts = array_values(array_unique(array_map(fn ($part) => trim((string) $part), $parts)));

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|mixed
     */
    private function extractProviderData(array $body, array $config): mixed
    {
        $dataPath = trim((string) ($config['data_path'] ?? ''));
        $fallbackCandidate = null;

        if ($dataPath !== '') {
            $configured = data_get($body, $dataPath);
            if (is_array($configured)) {
                if ($this->looksLikeProviderPayload($configured)) {
                    return $configured;
                }

                $fallbackCandidate = $configured;
            }
        }

        $commonFallbacks = [
            'data._raw.data',
            'data.data',
            '_raw.data',
            'response.data',
            'result.data',
            'result',
            'payload',
            'data',
        ];

        foreach ($commonFallbacks as $path) {
            $candidate = data_get($body, $path);
            if (is_array($candidate) && $candidate !== []) {
                if ($this->looksLikeProviderPayload($candidate)) {
                    return $candidate;
                }

                $fallbackCandidate ??= $candidate;
            }
        }

        if (is_array($fallbackCandidate) && $fallbackCandidate !== []) {
            return $fallbackCandidate;
        }

        return $this->looksLikeProviderPayload($body) ? $body : [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $config
     */
    private function providerSucceeded(array $body, array $config, int $httpStatus): bool
    {
        $successPath = trim((string) ($config['success_path'] ?? 'success'));
        $value = $successPath !== '' ? data_get($body, $successPath) : null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1 || (int) $value === 200;
        }

        if (is_string($value)) {
            $normalized = Str::lower(trim($value));

            if (in_array($normalized, ['true', '1', 'success', 'successful', 'ok', '00', '200'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'failed', 'failure', 'error'], true)) {
                return false;
            }
        }

        $statusCode = data_get($body, 'statusCode', data_get($body, 'status_code', data_get($body, 'status')));
        if (is_numeric($statusCode) && (int) $statusCode >= 200 && (int) $statusCode < 300) {
            return $httpStatus >= 200 && $httpStatus < 300;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function providerErrorMessage(array $body, string $default): string
    {
        $message = data_get($body, 'message')
            ?? data_get($body, 'error')
            ?? data_get($body, 'errors.nin.0')
            ?? data_get($body, 'errors.0')
            ?? $default;

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES) ?: $default;
        }

        $message = trim((string) $message);
        if ($message === '') {
            $message = $default;
        }

        $code = data_get($body, 'error_code');
        if (is_scalar($code) && !str_contains($message, (string) $code)) {
            $message .= ' (' . $code . ')';
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function looksLikeProviderPayload(array $body): bool
    {
        return isset($body['nin'])
            || isset($body['first_name'])
            || isset($body['last_name'])
            || isset($body['idNumber'])
            || isset($body['firstName'])
            || isset($body['lastName']);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $body
     */
    private function logProviderResponse(Enrollee $enrollee, array $config, array $payload, int $status, array $body): void
    {
        Log::info('nin_provider_response', [
            'enrollee_id' => $enrollee->id,
            'enrollee_code' => $enrollee->enrollee_id,
            'provider_name' => $config['provider_name'] ?? null,
            'base_url' => $config['base_url'] ?? null,
            'verify_endpoint' => $config['verify_endpoint'] ?? null,
            'success_path' => $config['success_path'] ?? null,
            'data_path' => $config['data_path'] ?? null,
            'field_map' => $config['field_map'] ?? [],
            'request_payload' => $payload,
            'http_status' => $status,
            'response_body' => $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $body
     */
    private function logRawProviderResponse(User $user, array $config, array $payload, int $status, array $body): void
    {
        Log::info('mobile_nin_provider_response', [
            'user_id' => $user->id,
            'provider_name' => $config['provider_name'] ?? null,
            'base_url' => $config['base_url'] ?? null,
            'verify_endpoint' => $config['verify_endpoint'] ?? null,
            'success_path' => $config['success_path'] ?? null,
            'data_path' => $config['data_path'] ?? null,
            'field_map' => $config['field_map'] ?? [],
            'request_payload' => $payload,
            'http_status' => $status,
            'response_body' => $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function excerptProviderResponse(array $body): array
    {
        return [
            'success' => data_get($body, 'success'),
            'message' => data_get($body, 'message'),
            'data_keys' => is_array(data_get($body, 'data')) ? array_keys((array) data_get($body, 'data')) : [],
        ];
    }

    private function resolveAliasValue(array $providerData, string $internalField): mixed
    {
        foreach ($this->fieldAliases($internalField) as $path) {
            $value = data_get($providerData, $path);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function fieldAliases(string $internalField): array
    {
        return match ($internalField) {
            'nin' => ['idNumber'],
            'first_name' => ['firstName'],
            'middle_name' => ['middleName'],
            'last_name' => ['lastName'],
            'date_of_birth' => ['dateOfBirth'],
            'phone' => ['mobile'],
            'photo' => ['image'],
            'address' => ['address'],
            default => [],
        };
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

    /**
     * @param  array<string, mixed>  $providerData
     * @return array<int, array<string, mixed>>
     */
    public function comparisonFor(Enrollee $enrollee, array $providerData): array
    {
        return $this->buildComparison($enrollee, $providerData);
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
            'photo' => $enrollee->providedEnrollmentPhotoUrl(),
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
