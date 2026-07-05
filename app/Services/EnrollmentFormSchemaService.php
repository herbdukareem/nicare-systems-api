<?php

namespace App\Services;

use App\Models\EnrollmentFormSchema;
use App\Models\InsuranceProgramme;
use App\Models\PremiumPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class EnrollmentFormSchemaService
{
    public const NIN_MODES = ['none', 'deferred', 'live_preferred', 'live_required', 'online_only'];
    public const NIN_OFFLINE_BEHAVIORS = ['allow_capture', 'defer_until_sync', 'block_capture'];
    public const NIN_OVERWRITE_STRATEGIES = ['empty_only', 'always', 'never'];
    public const NIN_CONFLICT_STATUSES = ['requires_review', 'nin_failed'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaultFields(): array
    {
        return [
            ['key' => 'nin', 'label' => 'NIN', 'type' => 'text', 'required' => false, 'rules' => ['string', 'max:255']],
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'required' => true, 'rules' => ['string', 'max:255']],
            ['key' => 'last_name', 'label' => 'Last name', 'type' => 'text', 'required' => true, 'rules' => ['string', 'max:255']],
            ['key' => 'middle_name', 'label' => 'Middle name', 'type' => 'text', 'required' => false, 'rules' => ['string', 'max:255']],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false, 'rules' => ['string', 'max:255']],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'rules' => ['email', 'max:255']],
            ['key' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true, 'rules' => ['date']],
            ['key' => 'sex', 'label' => 'Sex', 'type' => 'select', 'required' => true, 'rules' => ['integer', 'in:1,2'], 'options' => [
                ['label' => 'Male', 'value' => 1],
                ['label' => 'Female', 'value' => 2],
            ]],
            ['key' => 'marital_status', 'label' => 'Marital status', 'type' => 'select', 'required' => false, 'rules' => ['integer', 'in:1,2,3,4']],
            ['key' => 'address', 'label' => 'Address', 'type' => 'textarea', 'required' => false, 'rules' => ['string']],
            ['key' => 'lga_id', 'label' => 'LGA', 'type' => 'select', 'required' => true, 'source' => 'lgas', 'rules' => ['integer', 'exists:lgas,id']],
            ['key' => 'ward_id', 'label' => 'Ward', 'type' => 'select', 'required' => true, 'source' => 'wards', 'rules' => ['integer', 'exists:wards,id']],
            ['key' => 'facility_id', 'label' => 'Facility', 'type' => 'select', 'required' => true, 'source' => 'facilities', 'rules' => ['integer', 'exists:facilities,id']],
            ['key' => 'insurance_programme_id', 'label' => 'Programme', 'type' => 'select', 'required' => true, 'source' => 'insurance_programmes', 'rules' => ['integer', 'exists:insurance_programmes,id']],
            ['key' => 'premium_plan_id', 'label' => 'Premium plan', 'type' => 'select', 'required' => true, 'source' => 'premium_plans', 'rules' => ['integer', 'exists:premium_plans,id']],
            ['key' => 'benefactor_id', 'label' => 'Benefactor', 'type' => 'select', 'required' => false, 'source' => 'benefactors', 'rules' => ['integer', 'exists:benefactors,id']],
            ['key' => 'premium_pin', 'label' => 'Premium PIN', 'type' => 'text', 'required' => false, 'rules' => ['string', 'max:255']],
        ];
    }

    /**
     * @return Collection<int, EnrollmentFormSchema>
     */
    public function publishedForMobile(?string $since = null): Collection
    {
        return EnrollmentFormSchema::with(['programme:id,name,code', 'category:id,name,code', 'plan:id,name,code'])
            ->where('channel', 'mobile')
            ->where('status', EnrollmentFormSchema::STATUS_PUBLISHED)
            ->when($since, fn (Builder $query) => $query->where('updated_at', '>', $since))
            ->orderBy('insurance_programme_id')
            ->orderBy('premium_plan_id')
            ->orderByDesc('version')
            ->get();
    }

    public function resolveForSubmission(array $record): EnrollmentFormSchema
    {
        $schemaId = $record['schema_id'] ?? $record['enrollment_form_schema_id'] ?? null;
        if ($schemaId) {
            $schema = EnrollmentFormSchema::findOrFail($schemaId);
            if ($schema->isRevoked()) {
                throw new RuntimeException('The enrollment form version used for capture has been revoked and cannot be synced.');
            }

            return $schema;
        }

        $planId = (int) data_get($record, 'data.premium_plan_id', $record['premium_plan_id'] ?? 0);
        $programmeId = (int) data_get($record, 'data.insurance_programme_id', $record['insurance_programme_id'] ?? 0);

        $schemaVersion = (int) ($record['schema_version'] ?? 0);
        if ($schemaVersion > 0) {
            $schema = EnrollmentFormSchema::query()
                ->where('channel', 'mobile')
                ->where('version', $schemaVersion)
                ->when($planId, fn (Builder $query) => $query->where('premium_plan_id', $planId))
                ->when(!$planId && $programmeId, fn (Builder $query) => $query->where('insurance_programme_id', $programmeId))
                ->orderByDesc('id')
                ->first();

            if ($schema) {
                if ($schema->isRevoked()) {
                    throw new RuntimeException('The enrollment form version used for capture has been revoked and cannot be synced.');
                }

                return $schema;
            }
        }

        $schema = EnrollmentFormSchema::query()
            ->where('channel', 'mobile')
            ->where('status', EnrollmentFormSchema::STATUS_PUBLISHED)
            ->when($planId, fn (Builder $query) => $query->where('premium_plan_id', $planId))
            ->when(!$planId && $programmeId, fn (Builder $query) => $query->where('insurance_programme_id', $programmeId))
            ->orderByDesc('version')
            ->first();

        if ($schema) {
            return $schema;
        }

        return $this->makeVirtualDefaultSchema($planId, $programmeId);
    }

    public function makeVirtualDefaultSchema(?int $planId = null, ?int $programmeId = null): EnrollmentFormSchema
    {
        $plan = $planId ? PremiumPlan::with('programme')->find($planId) : null;
        $programme = $programmeId ? InsuranceProgramme::find($programmeId) : $plan?->programme;

        $schema = new EnrollmentFormSchema([
            'uuid' => 'default-mobile-schema',
            'name' => 'Default mobile enrollment form',
            'channel' => 'mobile',
            'insurance_programme_id' => $programme?->id,
            'premium_plan_id' => $plan?->id,
            'benefactor_ids' => [],
            'version' => 1,
            'status' => EnrollmentFormSchema::STATUS_PUBLISHED,
            'requires_nin_verification' => false,
            'nin_verification_policy' => $this->defaultNinVerificationPolicy(false),
            'allow_offline_capture' => true,
            'fields' => $this->defaultFields(),
            'rules' => [],
            'ui_schema' => ['sections' => ['personal', 'location', 'coverage']],
        ]);

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?int $userId): EnrollmentFormSchema
    {
        return EnrollmentFormSchema::create($this->normalizePayload($attributes) + [
            'uuid' => (string) Str::uuid(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(EnrollmentFormSchema $schema, array $attributes, ?int $userId): EnrollmentFormSchema
    {
        $schema->fill($this->normalizePayload($attributes));
        $schema->updated_by = $userId;
        $schema->save();

        return $schema->fresh(['programme', 'category', 'plan']);
    }

    public function publish(EnrollmentFormSchema $schema, ?int $userId): EnrollmentFormSchema
    {
        $schema->forceFill([
            'status' => EnrollmentFormSchema::STATUS_PUBLISHED,
            'published_at' => now(),
            'revoked_at' => null,
            'updated_by' => $userId,
        ])->save();

        return $schema->fresh(['programme', 'category', 'plan']);
    }

    public function revoke(EnrollmentFormSchema $schema, ?int $userId): EnrollmentFormSchema
    {
        $schema->forceFill([
            'status' => EnrollmentFormSchema::STATUS_REVOKED,
            'revoked_at' => now(),
            'updated_by' => $userId,
        ])->save();

        return $schema->fresh(['programme', 'category', 'plan']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizePayload(array $attributes): array
    {
        return [
            'name' => $attributes['name'] ?? 'Mobile enrollment form',
            'channel' => $attributes['channel'] ?? 'mobile',
            'insurance_programme_id' => $attributes['insurance_programme_id'] ?? null,
            'enrollee_category_id' => $attributes['enrollee_category_id'] ?? null,
            'premium_plan_id' => $attributes['premium_plan_id'] ?? null,
            'benefactor_ids' => array_values(array_unique(array_map('intval', $attributes['benefactor_ids'] ?? []))),
            'version' => max(1, (int) ($attributes['version'] ?? 1)),
            'status' => $attributes['status'] ?? EnrollmentFormSchema::STATUS_DRAFT,
            'requires_nin_verification' => (bool) ($attributes['requires_nin_verification'] ?? false),
            'nin_verification_policy' => $this->normalizeNinVerificationPolicy(
                (array) ($attributes['nin_verification_policy'] ?? []),
                (bool) ($attributes['requires_nin_verification'] ?? false)
            ),
            'allow_offline_capture' => (bool) ($attributes['allow_offline_capture'] ?? true),
            'fields' => $attributes['fields'] ?? $this->defaultFields(),
            'rules' => $attributes['rules'] ?? [],
            'ui_schema' => $attributes['ui_schema'] ?? [],
            'migration_hints' => $attributes['migration_hints'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultNinVerificationPolicy(bool $requiresNin = false): array
    {
        return [
            'mode' => $requiresNin ? 'live_required' : 'none',
            'offline_behavior' => $requiresNin ? 'defer_until_sync' : 'allow_capture',
            'conflict_status' => 'requires_review',
            'autofill' => [
                'enabled' => $requiresNin,
                'overwrite_strategy' => 'empty_only',
                'lock_verified_fields' => true,
                'editable_fields' => [],
                'fields' => [
                    'first_name' => 'first_name',
                    'middle_name' => 'middle_name',
                    'last_name' => 'last_name',
                    'date_of_birth' => 'date_of_birth',
                    'sex' => 'gender',
                    'phone' => 'phone',
                    'photo' => 'photo',
                    'address' => 'address',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function normalizeNinVerificationPolicy(array $policy, bool $requiresNin = false): array
    {
        $default = $this->defaultNinVerificationPolicy($requiresNin);
        $autofill = is_array($policy['autofill'] ?? null) ? $policy['autofill'] : [];

        $mode = (string) ($policy['mode'] ?? $default['mode']);
        $offlineBehavior = (string) ($policy['offline_behavior'] ?? $default['offline_behavior']);
        $overwriteStrategy = (string) ($autofill['overwrite_strategy'] ?? data_get($default, 'autofill.overwrite_strategy'));
        $conflictStatus = (string) ($policy['conflict_status'] ?? $default['conflict_status']);
        $fields = is_array($autofill['fields'] ?? null) ? $autofill['fields'] : data_get($default, 'autofill.fields', []);
        $editableFields = is_array($autofill['editable_fields'] ?? null) ? $autofill['editable_fields'] : data_get($default, 'autofill.editable_fields', []);

        return [
            'mode' => in_array($mode, self::NIN_MODES, true) ? $mode : $default['mode'],
            'offline_behavior' => in_array($offlineBehavior, self::NIN_OFFLINE_BEHAVIORS, true) ? $offlineBehavior : $default['offline_behavior'],
            'conflict_status' => in_array($conflictStatus, self::NIN_CONFLICT_STATUSES, true) ? $conflictStatus : $default['conflict_status'],
            'autofill' => [
                'enabled' => (bool) ($autofill['enabled'] ?? data_get($default, 'autofill.enabled')),
                'overwrite_strategy' => in_array($overwriteStrategy, self::NIN_OVERWRITE_STRATEGIES, true) ? $overwriteStrategy : data_get($default, 'autofill.overwrite_strategy'),
                'lock_verified_fields' => (bool) ($autofill['lock_verified_fields'] ?? data_get($default, 'autofill.lock_verified_fields')),
                'editable_fields' => collect($editableFields)
                    ->filter(fn ($field) => is_string($field) && trim($field) !== '')
                    ->map(fn ($field) => trim($field))
                    ->unique()
                    ->values()
                    ->all(),
                'fields' => collect($fields)
                    ->filter(fn ($providerField, $enrolleeField) => is_string($enrolleeField) && is_string($providerField) && $enrolleeField !== '' && $providerField !== '')
                    ->mapWithKeys(fn ($providerField, $enrolleeField) => [$enrolleeField => trim($providerField)])
                    ->all(),
            ],
        ];
    }
}
