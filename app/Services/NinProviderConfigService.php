<?php

namespace App\Services;

use App\Models\Configuration;

class NinProviderConfigService
{
    private const KEY_PREFIX = 'NIN_PROVIDER_';

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $fieldMap = array_merge(
            $this->defaultFieldMap(),
            $this->decodeJson(Configuration::getValue(self::KEY_PREFIX . 'FIELD_MAP'), [])
        );

        return [
            'provider_name' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'NAME', 'Ashlab Verify')),
            'enabled' => $this->toBoolean(Configuration::getValue(self::KEY_PREFIX . 'ENABLED', 'false')),
            'base_url' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'BASE_URL', 'https://api.verify.ashlabtech.ng')),
            'verify_endpoint' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'VERIFY_ENDPOINT', '/api/v1/verify/nin')),
            'auth_type' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'AUTH_TYPE', 'bearer')),
            'api_key' => (string) Configuration::getValue(self::KEY_PREFIX . 'API_KEY', ''),
            'request_method' => strtoupper(trim((string) Configuration::getValue(self::KEY_PREFIX . 'REQUEST_METHOD', 'POST'))),
            'request_nin_field' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'REQUEST_NIN_FIELD', 'nin')),
            'request_consent_field' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'REQUEST_CONSENT_FIELD', 'consent')),
            'request_consent_value' => $this->decodeScalar(Configuration::getValue(self::KEY_PREFIX . 'REQUEST_CONSENT_VALUE', 'true')),
            'success_path' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'SUCCESS_PATH', 'success')),
            'data_path' => trim((string) Configuration::getValue(self::KEY_PREFIX . 'DATA_PATH', 'data')),
            'field_map' => is_array($fieldMap) ? $this->normalizeFieldMap($fieldMap) : $this->defaultFieldMap(),
            'timeout_seconds' => max(5, (int) Configuration::getNumericValue(self::KEY_PREFIX . 'TIMEOUT_SECONDS', 15)),
            'public_self_enrollment_enabled' => $this->toBoolean(Configuration::getValue(self::KEY_PREFIX . 'PUBLIC_SELF_ENROLLMENT_ENABLED', 'false')),
            'public_verification_fee_amount' => round((float) Configuration::getNumericValue(self::KEY_PREFIX . 'PUBLIC_VERIFICATION_FEE_AMOUNT', 0), 2),
            'public_pin_fee_required' => $this->toBoolean(Configuration::getValue(self::KEY_PREFIX . 'PUBLIC_PIN_FEE_REQUIRED', 'true')),
            'public_pin_reservation_minutes' => max(5, (int) Configuration::getNumericValue(self::KEY_PREFIX . 'PUBLIC_PIN_RESERVATION_MINUTES', 60)),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function save(array $attributes): array
    {
        $payload = array_merge($this->getConfig(), $attributes);

        Configuration::setValue(self::KEY_PREFIX . 'NAME', trim((string) $payload['provider_name']), 'Display name for the NIN verification provider.');
        Configuration::setValue(self::KEY_PREFIX . 'ENABLED', $payload['enabled'] ? 'true' : 'false', 'Whether NIN verification is enabled for approvals.');
        Configuration::setValue(self::KEY_PREFIX . 'BASE_URL', trim((string) $payload['base_url']), 'Base URL for the active NIN verification provider.');
        Configuration::setValue(self::KEY_PREFIX . 'VERIFY_ENDPOINT', trim((string) $payload['verify_endpoint']), 'Relative verification endpoint for NIN lookups.');
        Configuration::setValue(self::KEY_PREFIX . 'AUTH_TYPE', trim((string) $payload['auth_type']), 'Authentication mechanism used for NIN verification requests.');
        Configuration::setValue(self::KEY_PREFIX . 'API_KEY', (string) ($payload['api_key'] ?? ''), 'API key used for NIN verification requests.');
        Configuration::setValue(self::KEY_PREFIX . 'REQUEST_METHOD', strtoupper(trim((string) $payload['request_method'])), 'HTTP method used for the NIN verification request.');
        Configuration::setValue(self::KEY_PREFIX . 'REQUEST_NIN_FIELD', trim((string) $payload['request_nin_field']), 'JSON field name that carries the NIN value.');
        Configuration::setValue(self::KEY_PREFIX . 'REQUEST_CONSENT_FIELD', trim((string) $payload['request_consent_field']), 'JSON field name that carries the consent flag.');
        Configuration::setValue(self::KEY_PREFIX . 'REQUEST_CONSENT_VALUE', $this->encodeScalar($payload['request_consent_value']), 'Default consent value sent to the provider.');
        Configuration::setValue(self::KEY_PREFIX . 'SUCCESS_PATH', trim((string) $payload['success_path']), 'Dot path to the provider success flag in the response body.');
        Configuration::setValue(self::KEY_PREFIX . 'DATA_PATH', trim((string) $payload['data_path']), 'Dot path to the provider enrollee payload in the response body.');
        Configuration::setValue(self::KEY_PREFIX . 'FIELD_MAP', json_encode($this->normalizeFieldMap((array) $payload['field_map']), JSON_UNESCAPED_SLASHES), 'Field mapping from provider payload keys to the internal enrollee comparison structure.');
        Configuration::setValue(self::KEY_PREFIX . 'TIMEOUT_SECONDS', (string) max(5, (int) $payload['timeout_seconds']), 'Maximum seconds allowed for NIN verification requests.');
        Configuration::setValue(self::KEY_PREFIX . 'PUBLIC_SELF_ENROLLMENT_ENABLED', $payload['public_self_enrollment_enabled'] ? 'true' : 'false', 'Whether live NIN verification is enabled for public self-enrollment.');
        Configuration::setValue(self::KEY_PREFIX . 'PUBLIC_VERIFICATION_FEE_AMOUNT', number_format(max(0, (float) $payload['public_verification_fee_amount']), 2, '.', ''), 'Amount charged for each public self-enrollment NIN verification.');
        Configuration::setValue(self::KEY_PREFIX . 'PUBLIC_PIN_FEE_REQUIRED', $payload['public_pin_fee_required'] ? 'true' : 'false', 'Whether Premium PIN enrollments must still pay the public NIN verification fee.');
        Configuration::setValue(self::KEY_PREFIX . 'PUBLIC_PIN_RESERVATION_MINUTES', (string) max(5, (int) $payload['public_pin_reservation_minutes']), 'How long a Premium PIN stays reserved while public NIN-fee payment is pending.');

        return $this->getConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicEnrollmentConfig(): array
    {
        $config = $this->getConfig();
        $providerConfigured = filled($config['base_url'] ?? null)
            && filled($config['verify_endpoint'] ?? null)
            && filled($config['api_key'] ?? null)
            && (bool) ($config['enabled'] ?? false);

        return [
            'enabled' => (bool) ($config['public_self_enrollment_enabled'] ?? false) && $providerConfigured,
            'configured' => $providerConfigured,
            'fee_amount' => round((float) ($config['public_verification_fee_amount'] ?? 0), 2),
            'charge_for_premium_pin' => (bool) ($config['public_pin_fee_required'] ?? true),
            'pin_reservation_minutes' => max(5, (int) ($config['public_pin_reservation_minutes'] ?? 60)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultFieldMap(): array
    {
        return [
            'nin' => 'nin',
            'first_name' => 'first_name',
            'middle_name' => 'middle_name',
            'last_name' => 'last_name',
            'date_of_birth' => 'date_of_birth',
            'gender' => 'gender',
            'phone' => 'phone',
            'photo' => 'photo',
            'address' => 'address',
        ];
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function decodeScalar(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function encodeScalar(mixed $value): string
    {
        return is_scalar($value) || $value === null
            ? json_encode($value, JSON_UNESCAPED_SLASHES)
            : json_encode((string) $value, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    private function decodeJson(?string $value, array $default = []): array
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * @param  array<string, mixed>  $fieldMap
     * @return array<string, string>
     */
    private function normalizeFieldMap(array $fieldMap): array
    {
        $normalized = [];

        foreach ($fieldMap as $internalField => $providerPath) {
            if (!is_string($internalField)) {
                continue;
            }

            $normalized[$internalField] = is_string($providerPath)
                ? trim($providerPath)
                : '';
        }

        return $normalized;
    }
}
