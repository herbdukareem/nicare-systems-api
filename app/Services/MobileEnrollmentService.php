<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\MobileEnrollmentAttachment;
use App\Models\MobileEnrollmentRecord;
use App\Models\NinVerificationCache;
use App\Models\OfficerDevice;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Models\Ward;
use App\Services\Billing\BillingCheckoutService;
use App\Services\Billing\BillingPaymentVerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MobileEnrollmentService
{
    public function __construct(
        private EnrollmentFormSchemaService $schemaService,
        private EnrollmentValidationService $validationService,
        private EnrolleeDuplicateDetectionService $duplicateDetectionService,
        private PremiumCoverageService $premiumCoverageService,
        private NinVerificationService $ninVerificationService,
        private BillingCheckoutService $billingCheckoutService,
        private BillingPaymentVerificationService $billingVerificationService
    ) {
    }

    public function syncBatch(User $officer, OfficerDevice $device, array $records, array $meta = []): array
    {
        $batchId = (string) Str::uuid();
        $results = [];

        foreach ($records as $recordPayload) {
            $results[] = $this->syncRecord($officer, $device, $batchId, $recordPayload, $meta);
        }

        return [
            'sync_batch_id' => $batchId,
            'total' => count($results),
            'records' => $results,
        ];
    }

    public function syncRecord(User $officer, OfficerDevice $device, string $batchId, array $recordPayload, array $meta = []): array
    {
        $clientRecordId = (string) ($recordPayload['client_record_id'] ?? Str::uuid());

        $record = MobileEnrollmentRecord::firstOrNew([
            'officer_device_id' => $device->id,
            'client_record_id' => $clientRecordId,
        ]);

        if ($record->exists && $record->enrollee_id) {
            return $this->recordResponse($record->fresh(['enrollee', 'duplicateOf']));
        }

        $record->fill([
            'sync_batch_id' => $record->sync_batch_id ?: $batchId,
            'officer_user_id' => $officer->id,
            'payload' => $recordPayload,
            'status' => MobileEnrollmentRecord::STATUS_RECEIVED,
            'status_reason' => null,
            'duplicate_of_enrollee_id' => null,
            'nin_conflicts' => null,
            'captured_at' => $recordPayload['captured_at'] ?? null,
            'received_at' => now(),
            'app_version' => $meta['app_version'] ?? $device->app_version,
            'gps_latitude' => data_get($meta, 'gps.latitude'),
            'gps_longitude' => data_get($meta, 'gps.longitude'),
            'ip_address' => $meta['ip_address'] ?? null,
        ])->save();

        try {
            $schema = $this->schemaService->resolveForSubmission($recordPayload);
            $ninPolicy = $this->schemaService->normalizeNinVerificationPolicy(
                (array) ($schema->nin_verification_policy ?? []),
                (bool) $schema->requires_nin_verification
            );
            $locationPolicy = $this->schemaService->normalizeLocationCapturePolicy(
                (array) ($schema->location_capture_policy ?? [])
            );

            if (($recordPayload['captured_offline'] ?? false) && !$this->policyAllowsOfflineCapture($schema, $ninPolicy)) {
                return $this->failSync($record, 'This enrollment configuration does not allow offline capture when live NIN verification is required.');
            }

            $data = $this->normalizeLocationData($this->normalizeCoreData((array) ($recordPayload['data'] ?? $recordPayload)));
            $data = $this->resolveLockedVerifiedFieldEdits($data, $recordPayload, $ninPolicy);
            if (isset($recordPayload['data']) && is_array($recordPayload['data'])) {
                $recordPayload['data'] = array_merge($recordPayload['data'], $data, [
                    'lga_id' => $data['lga_id'] ?? null,
                    'ward_id' => $data['ward_id'] ?? null,
                    'facility_id' => $data['facility_id'] ?? null,
                ]);
                $record->forceFill(['payload' => $recordPayload])->save();
            }
            $this->ensureOfficerCanUseEnrollment($officer, $schema->exists ? $schema->id : null, (int) ($data['lga_id'] ?? 0));

            $extraFields = (array) ($recordPayload['extra_fields'] ?? []);
            $validated = $this->validationService->validate($schema, $data, $extraFields);
            $this->validateVerifiedFieldEdits($recordPayload, $ninPolicy, $validated['core']);
            $locationPayload = $this->normalizeLocationPayload((array) ($recordPayload['location'] ?? []), $locationPolicy);
            $this->validateLocationRequirement($locationPayload, $locationPolicy, $recordPayload);

            $record->forceFill([
                'enrollment_form_schema_id' => $schema->exists ? $schema->id : null,
                'schema_version' => (int) ($recordPayload['schema_version'] ?? $schema->version ?? 1),
                'core_data' => $validated['core'],
                'extra_fields' => $validated['extra'],
                'migration_hints' => $schema->migration_hints ?? null,
                'nin_verification_policy' => $ninPolicy,
                'verified_field_edit_reasons' => $recordPayload['verified_field_edit_reasons'] ?? null,
                'location_capture_policy' => $locationPolicy,
                'location_payload' => $locationPayload,
            ])->save();

            if (!empty($recordPayload['verified_field_edit_reasons'])) {
                $this->audit($record, 'mobile_enrollment_verified_field_edit_reason', 'Officer supplied reasons for editing verified NIN fields.');
            }

            $duplicate = $this->duplicateDetectionService->check($validated['core'] + ['gender' => $validated['core']['sex'] ?? null]);
            if ($duplicate['is_duplicate'] ?? false) {
                return $this->markDuplicate($record, $duplicate);
            }

            $pin = null;
            if (!blank($validated['core']['premium_pin'] ?? null)) {
                $pin = $this->validatePinForEnrollment((string) $validated['core']['premium_pin'], (int) $validated['core']['premium_plan_id']);
            }

            $enrollee = $this->createPendingEnrollee($record, $officer, $validated['core'], $validated['extra'], $schema, $locationPayload);

            if ($pin) {
                $enrollee = $this->premiumCoverageService->usePinForPendingEnrollment(
                    $pin,
                    $enrollee,
                    PremiumPlan::findOrFail($validated['core']['premium_plan_id'])
                );
            }

            $record->forceFill([
                'enrollee_id' => $enrollee->id,
                'synced_at' => now(),
            ])->save();

            if ($this->policyNeedsNinVerification($ninPolicy)) {
                return $this->processPolicyNinVerification($record->fresh('enrollee'), $officer, $ninPolicy);
            }

            return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_APPROVAL, 'Enrollment received and ready for approval.');
        } catch (ValidationException $exception) {
            return $this->failSync($record, implode('; ', $exception->validator->errors()->all()));
        } catch (Throwable $exception) {
            return $this->failSync($record, $exception->getMessage());
        }
    }

    public function initializePinPurchase(User $officer, OfficerDevice $device, array $data): array
    {
        $plan = PremiumPlan::findOrFail($data['premium_plan_id']);
        if (!$plan->requiresPayment()) {
            throw new RuntimeException('The selected premium plan does not require online PIN purchase.');
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        $reference = $this->uniquePurchaseReference();

        $purchase = $this->premiumCoverageService->createPurchase([
            'premium_plan_id' => $plan->id,
            'payer_type' => 'officer',
            'payer_name' => $data['payer_name'] ?? $officer->name,
            'payer_phone' => $data['payer_phone'] ?? $officer->phone,
            'payer_email' => $data['payer_email'] ?? $officer->email,
            'payer_details' => [
                'channel' => 'mobile_officer_pin_purchase',
                'officer_user_id' => $officer->id,
                'officer_device_id' => $device->id,
            ],
            'payment_method' => 'online_payment',
            'payment_status' => 'pending',
            'payment_reference' => $reference,
            'quantity' => $quantity,
            'amount' => (float) $plan->amount * $quantity,
            'sold_by' => $officer->id,
        ]);

        try {
            $checkout = $this->billingCheckoutService->initializePurchaseCheckout(
                $purchase,
                '/mobile/payment-return?checkout_return=1'
            );
        } catch (Throwable $exception) {
            $purchase->delete();
            throw $exception;
        }

        $purchase->update([
            'gateway_code' => $checkout['provider'] ?? $purchase->gateway_code,
            'gateway_status' => $checkout['status'] ?? 'initialized',
            'authorization_url' => $checkout['authorization_url'] ?? null,
            'gateway_access_code' => $checkout['access_code'] ?? null,
            'gateway_response' => $checkout['raw_response'] ?? null,
        ]);

        return [
            'purchase' => $purchase->fresh(['plan']),
            'checkout' => $checkout,
        ];
    }

    public function verifyPinPurchase(User $officer, OfficerDevice $device, string $reference): array
    {
        $purchase = PremiumPurchase::with(['plan', 'pins.plan'])
            ->where('payment_reference', $reference)
            ->firstOrFail();

        if (
            data_get($purchase->payer_details, 'channel') !== 'mobile_officer_pin_purchase'
            || (int) data_get($purchase->payer_details, 'officer_user_id') !== (int) $officer->id
            || (int) data_get($purchase->payer_details, 'officer_device_id') !== (int) $device->id
        ) {
            throw new RuntimeException('This PIN purchase does not belong to the registered mobile device.');
        }

        $result = $this->billingVerificationService->verifyPurchase($purchase);
        $purchase = $result['purchase'];

        if (($result['verification']['paid'] ?? false) || $purchase->payment_status === 'confirmed') {
            $purchase = DB::transaction(function () use ($purchase): PremiumPurchase {
                $locked = PremiumPurchase::with('plan')->lockForUpdate()->findOrFail($purchase->id);
                $remaining = max(0, (int) $locked->quantity - $locked->pins()->count());
                if ($remaining > 0) {
                    $this->premiumCoverageService->generatePins($locked->plan, $remaining, $locked);
                }

                return $locked->fresh(['plan', 'pins.plan']);
            });
        }

        return [
            ...$result,
            'purchase' => $purchase->fresh(['plan', 'pins.plan']),
            'pins' => $purchase->fresh()->pins()->with('plan')->get(),
        ];
    }

    public function uploadAttachment(MobileEnrollmentRecord $record, User $officer, UploadedFile $file, string $kind = 'passport'): MobileEnrollmentAttachment
    {
        if ((int) $record->officer_user_id !== (int) $officer->id) {
            throw new RuntimeException('You cannot upload attachments for another officer\'s mobile enrollment record.');
        }

        $disk = (string) config('filesystems.enrollee_passport_disk', 'public');
        $path = Storage::disk($disk)->putFile('mobile-enrollments/' . $record->id, $file, 'public');

        $attachment = MobileEnrollmentAttachment::create([
            'mobile_enrollment_record_id' => $record->id,
            'enrollee_id' => $record->enrollee_id,
            'kind' => $kind,
            'file_path' => Storage::disk($disk)->url($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'uploaded',
            'uploaded_by' => $officer->id,
        ]);

        if ($kind === 'passport' && $record->enrollee) {
            $record->enrollee->update(['image_url' => $attachment->file_path]);
        }

        $record->update(['attachment_status' => 'uploaded']);

        return $attachment;
    }

    private function normalizeCoreData(array $data): array
    {
        if (!isset($data['sex']) && isset($data['gender'])) {
            $data['sex'] = match (strtolower((string) $data['gender'])) {
                'male', 'm' => 1,
                'female', 'f' => 2,
                default => $data['gender'],
            };
        }

        return $data;
    }

    private function normalizeLocationData(array $data): array
    {
        if (!empty($data['facility_id'])) {
            $facility = Facility::query()
                ->select(['id', 'lga_id', 'ward_id'])
                ->find($data['facility_id']);

            if ($facility) {
                if (!empty($facility->lga_id)) {
                    $data['lga_id'] = (int) $facility->lga_id;
                }

                if (!empty($facility->ward_id)) {
                    $data['ward_id'] = (int) $facility->ward_id;
                }

                return $data;
            }
        }

        if (!empty($data['ward_id'])) {
            $ward = Ward::query()
                ->select(['id', 'lga_id'])
                ->find($data['ward_id']);

            if ($ward && !empty($ward->lga_id)) {
                $data['lga_id'] = (int) $ward->lga_id;
            }
        }

        return $data;
    }

    private function ensureOfficerCanUseEnrollment(User $officer, ?int $schemaId, int $lgaId): void
    {
        if (!$officer->mobileEnrollmentEnabled()) {
            throw new RuntimeException('Mobile enrollment has been disabled for this officer.');
        }

        $assignments = $officer->activeOfficerEnrollmentAssignments()->get();
        if ($assignments->isEmpty()) {
            if ($officer->hasRole('mobile-enrollment-officer')) {
                throw new RuntimeException('This officer has no active enrollment assignment.');
            }

            return;
        }

        $allowed = $assignments->contains(function ($assignment) use ($schemaId, $lgaId): bool {
            $lgaMatches = $assignment->lga_id === null || (int) $assignment->lga_id === $lgaId;
            $schemaMatches = $assignment->enrollment_form_schema_id === null
                || ($schemaId !== null && (int) $assignment->enrollment_form_schema_id === $schemaId);

            return $lgaMatches && $schemaMatches;
        });

        if (!$allowed) {
            throw new RuntimeException('This officer is not assigned to the selected LGA or enrollment configuration.');
        }
    }

    private function markDuplicate(MobileEnrollmentRecord $record, array $duplicate): array
    {
        $record->forceFill([
            'status' => MobileEnrollmentRecord::STATUS_DUPLICATE_SUSPECTED,
            'duplicate_of_enrollee_id' => $duplicate['matched_enrollee_id'] ?? null,
            'status_reason' => 'Possible duplicate detected: ' . ($duplicate['match_type'] ?? 'matched enrollee'),
            'synced_at' => now(),
        ])->save();

        $this->audit($record, 'mobile_enrollment_duplicate_suspected', $record->status_reason);

        return $this->recordResponse($record->fresh(['duplicateOf']));
    }

    private function validatePinForEnrollment(string $pinValue, int $planId): PremiumPin
    {
        $pin = $this->premiumCoverageService->validatePin($pinValue);
        if ((int) $pin->premium_plan_id !== $planId) {
            throw new RuntimeException('Premium PIN does not belong to the selected premium plan.');
        }

        return $pin;
    }

    private function createPendingEnrollee(MobileEnrollmentRecord $record, User $officer, array $data, array $extra, $schema, array $locationPayload = []): Enrollee
    {
        return DB::transaction(function () use ($record, $officer, $data, $extra, $schema, $locationPayload): Enrollee {
            return Enrollee::create([
                'nin' => $data['nin'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'],
                'sex' => (int) $data['sex'],
                'marital_status' => $data['marital_status'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'disability' => $data['disability'] ?? null,
                'address' => $data['address'] ?? null,
                'facility_id' => $data['facility_id'],
                'lga_id' => $data['lga_id'],
                'ward_id' => $data['ward_id'],
                'insurance_programme_id' => $data['insurance_programme_id'],
                'enrollee_category_id' => $data['enrollee_category_id'] ?? null,
                'premium_plan_id' => $data['premium_plan_id'],
                'benefactor_id' => $data['benefactor_id'] ?? null,
                'relationship_to_principal' => $data['relationship_to_principal'] ?? 1,
                'principal_enrollee_id' => $data['principal_enrollee_id'] ?? null,
                'status' => Enrollee::STATUS_PENDING,
                'created_by' => $officer->id,
                'password' => Hash::make(Str::random(16)),
                'enrollment_date' => now(),
                'enrollment_source' => 'mobile_officer',
                'enrollment_extra_fields' => $extra,
                'enrollment_location_audit' => $locationPayload !== [] ? $this->enrolleeLocationAudit($locationPayload) : null,
                'enrollment_form_schema_id' => $schema->exists ? $schema->id : null,
                'enrollment_schema_version' => $schema->version ?? 1,
                'mobile_enrollment_record_id' => $record->id,
                'nin_verification_status' => blank($data['nin'] ?? null)
                    ? Enrollee::NIN_VERIFICATION_NOT_PROVIDED
                    : Enrollee::NIN_VERIFICATION_NOT_STARTED,
            ]);
        });
    }

    private function processPolicyNinVerification(MobileEnrollmentRecord $record, User $officer, array $policy): array
    {
        $mode = (string) ($policy['mode'] ?? 'none');

        if ($mode === 'none') {
            return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_APPROVAL, 'Enrollment received and ready for approval.');
        }

        if ($mode === 'deferred') {
            return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_NIN, 'NIN verification is deferred until review or retry.');
        }

        $enrollee = $record->enrollee;
        if (!$enrollee || blank($enrollee->nin)) {
            if ($mode === 'live_preferred') {
                return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_APPROVAL, 'No NIN was provided. Enrollment is ready for approval without live NIN verification.');
            }

            return $this->transition($record, MobileEnrollmentRecord::STATUS_NIN_FAILED, 'NIN is required for this programme but was not provided.');
        }

        $record->forceFill([
            'status' => MobileEnrollmentRecord::STATUS_PENDING_NIN,
            'status_reason' => 'NIN verification is required for this programme.',
        ])->save();

        $cachedDeviceVerification = $this->matchingCachedDeviceVerification((array) ($record->payload ?? []), ['nin' => $enrollee->nin], true);
        if ($cachedDeviceVerification !== null) {
            return $this->acceptCachedDeviceVerifiedNin($record->fresh('enrollee'), $officer, $policy, $cachedDeviceVerification);
        }

        try {
            $verification = $this->ninVerificationService->verify($enrollee, $officer, true);
        } catch (Throwable $exception) {
            if ($this->isNinProviderUnavailable($exception) && $this->policyAllowsNinRetry($policy)) {
                return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_NIN, 'NIN verification could not be completed. It will be retried when the provider is available.');
            }

            return $this->transition($record, MobileEnrollmentRecord::STATUS_NIN_FAILED, $exception->getMessage());
        }

        $autofill = $this->applyNinAutofill($record->fresh('enrollee')->enrollee, (array) ($verification['provider_data'] ?? []), $policy);

        $record->forceFill([
            'nin_verified_at' => now(),
            'nin_verified_data' => $verification['provider_data'] ?? [],
            'nin_autofill_changes' => $autofill['changes'],
            'nin_conflicts' => $autofill['conflicts'],
        ])->save();

        if ($autofill['conflicts'] !== []) {
            $status = ($policy['conflict_status'] ?? 'requires_review') === 'nin_failed'
                ? MobileEnrollmentRecord::STATUS_NIN_FAILED
                : MobileEnrollmentRecord::STATUS_REQUIRES_REVIEW;

            return $this->transition($record, $status, 'NIN verification succeeded, but returned biodata conflicts with officer-submitted values.');
        }

        return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_APPROVAL, 'NIN verified. Enrollment is ready for approval.');
    }

    private function acceptCachedDeviceVerifiedNin(MobileEnrollmentRecord $record, User $officer, array $policy, NinVerificationCache $cache): array
    {
        $enrollee = $record->enrollee;
        $providerData = (array) $cache->provider_data;
        $providerName = $cache->provider_name ?: data_get($record->payload, 'nin_verification_provider') ?: 'server_cache';
        $verifiedAt = optional($cache->verified_at)->toIso8601String()
            ?: data_get($record->payload, 'nin_verified_at')
            ?: now()->toIso8601String();

        if ($enrollee) {
            $enrollee->forceFill([
                'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED,
                'nin_verified_at' => $verifiedAt,
                'nin_verified_by' => $officer->id,
                'nin_verification_provider' => $providerName,
                'nin_verification_data' => [
                    'provider_data' => $providerData,
                    'verified_nin' => $providerData['nin'] ?? $enrollee->nin,
                    'source' => 'server_cache_after_mobile_live_verification',
                    'cache_id' => $cache->id,
                ],
                'nin_verification_meta' => [
                    'provider_name' => $providerName,
                    'verified_at' => $verifiedAt,
                    'source' => 'server_cache_after_mobile_live_verification',
                    'cache_id' => $cache->id,
                    'cache_hit_count' => $cache->hit_count,
                ],
            ])->save();
        }

        $autofill = $this->applyNinAutofill($record->fresh('enrollee')->enrollee, $providerData, $policy);

        $record->forceFill([
            'nin_verified_at' => $verifiedAt,
            'nin_verified_data' => $providerData,
            'nin_autofill_changes' => $autofill['changes'],
            'nin_conflicts' => $autofill['conflicts'],
        ])->save();

        if ($autofill['conflicts'] !== []) {
            $status = ($policy['conflict_status'] ?? 'requires_review') === 'nin_failed'
                ? MobileEnrollmentRecord::STATUS_NIN_FAILED
                : MobileEnrollmentRecord::STATUS_REQUIRES_REVIEW;

            return $this->transition($record, $status, 'Mobile NIN verification was accepted, but returned biodata conflicts with officer-submitted values.');
        }

        $this->audit($record, 'mobile_enrollment_device_nin_verified', 'Mobile live NIN verification accepted during sync.');

        return $this->transition($record, MobileEnrollmentRecord::STATUS_PENDING_APPROVAL, 'NIN verified from server cache. Enrollment is ready for approval.');
    }

    private function policyNeedsNinVerification(array $policy): bool
    {
        return in_array((string) ($policy['mode'] ?? 'none'), ['deferred', 'live_preferred', 'live_required', 'online_only'], true);
    }

    private function policyAllowsOfflineCapture($schema, array $policy): bool
    {
        if (!(bool) ($schema->allow_offline_capture ?? true)) {
            return false;
        }

        return ($policy['offline_behavior'] ?? 'allow_capture') !== 'block_capture'
            && ($policy['mode'] ?? 'none') !== 'online_only';
    }

    private function policyAllowsNinRetry(array $policy): bool
    {
        return ($policy['offline_behavior'] ?? 'defer_until_sync') !== 'block_capture'
            && ($policy['mode'] ?? 'none') !== 'online_only';
    }

    private function isNinProviderUnavailable(Throwable $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'could not be reached')
            || str_contains($message, 'try again later')
            || str_contains($message, 'not enabled')
            || str_contains($message, 'incomplete');
    }

    /**
     * @return array{changes: array<int, array<string, mixed>>, conflicts: array<int, array<string, mixed>>}
     */
    private function applyNinAutofill(Enrollee $enrollee, array $providerData, array $policy): array
    {
        $autofill = (array) ($policy['autofill'] ?? []);
        if (!($autofill['enabled'] ?? false)) {
            return ['changes' => [], 'conflicts' => []];
        }

        $strategy = (string) ($autofill['overwrite_strategy'] ?? 'empty_only');
        $fields = (array) ($autofill['fields'] ?? []);
        $editableFields = array_map('strval', (array) ($autofill['editable_fields'] ?? []));
        $updates = [];
        $changes = [];
        $conflicts = [];

        foreach ($fields as $enrolleeField => $providerField) {
            $verifiedValue = data_get($providerData, (string) $providerField);
            if (blank($verifiedValue)) {
                continue;
            }

            $currentValue = $this->enrolleeComparableValue($enrollee, (string) $enrolleeField);
            $normalizedVerified = $this->normalizeNinValue((string) $enrolleeField, $verifiedValue);
            $hasConflict = !blank($currentValue) && !$this->ninValuesMatch($currentValue, $normalizedVerified);

            if ($hasConflict && !in_array((string) $enrolleeField, $editableFields, true)) {
                $conflicts[] = [
                    'field' => (string) $enrolleeField,
                    'officer_value' => $currentValue,
                    'nin_value' => $normalizedVerified,
                    'resolution' => ($policy['conflict_status'] ?? 'requires_review') === 'nin_failed' ? 'nin_failed' : 'review_required',
                ];
            }

            $shouldWrite = match ($strategy) {
                'always' => true,
                'never' => false,
                default => blank($currentValue),
            };

            if ($shouldWrite) {
                $this->writeNinAutofillValue($updates, (string) $enrolleeField, $normalizedVerified);
                $changes[] = [
                    'field' => (string) $enrolleeField,
                    'from' => $currentValue,
                    'to' => $normalizedVerified,
                    'strategy' => $strategy,
                ];
            }
        }

        if ($updates !== []) {
            $meta = is_array($enrollee->nin_verification_meta) ? $enrollee->nin_verification_meta : [];
            $meta['mobile_autofill'] = [
                'changes' => $changes,
                'conflicts' => $conflicts,
                'lock_verified_fields' => (bool) ($autofill['lock_verified_fields'] ?? false),
                'editable_fields' => $editableFields,
                'applied_at' => now()->toIso8601String(),
            ];

            $enrollee->forceFill($updates + ['nin_verification_meta' => $meta])->save();
        }

        return ['changes' => $changes, 'conflicts' => $conflicts];
    }

    private function validateVerifiedFieldEdits(array $recordPayload, array $policy, array $data): void
    {
        if (!data_get($policy, 'autofill.lock_verified_fields')) {
            return;
        }

        $cachedVerification = $this->matchingCachedDeviceVerification($recordPayload, $data);
        if ($cachedVerification === null) {
            return;
        }
        $verifiedValues = (array) $cachedVerification->provider_data;

        $editReasons = (array) ($recordPayload['verified_field_edit_reasons'] ?? []);
        $editableFields = array_map('strval', (array) data_get($policy, 'autofill.editable_fields', []));
        foreach ((array) data_get($policy, 'autofill.fields', []) as $enrolleeField => $providerField) {
            if (in_array((string) $enrolleeField, $editableFields, true)) {
                continue;
            }

            $verifiedValue = data_get($verifiedValues, (string) $providerField);
            if (blank($verifiedValue) || !array_key_exists((string) $enrolleeField, $data)) {
                continue;
            }

            $submittedValue = $this->normalizeNinValue((string) $enrolleeField, $data[(string) $enrolleeField]);
            $normalizedVerified = $this->normalizeNinValue((string) $enrolleeField, $verifiedValue);
            if (!$this->ninValuesMatch($submittedValue, $normalizedVerified) && blank($editReasons[(string) $enrolleeField] ?? null)) {
                throw new RuntimeException("A reason is required before editing verified NIN field {$enrolleeField}.");
            }
        }
    }

    private function resolveLockedVerifiedFieldEdits(array $data, array $recordPayload, array $policy): array
    {
        if (data_get($policy, 'autofill.lock_verified_fields') === false) {
            return $data;
        }

        $cachedVerification = $this->matchingCachedDeviceVerification($recordPayload, $data);
        if ($cachedVerification === null) {
            return $data;
        }
        $verifiedValues = (array) $cachedVerification->provider_data;

        $editReasons = (array) ($recordPayload['verified_field_edit_reasons'] ?? []);
        $editableFields = array_map('strval', (array) data_get($policy, 'autofill.editable_fields', []));

        foreach ((array) data_get($policy, 'autofill.fields', []) as $enrolleeField => $providerField) {
            $enrolleeField = (string) $enrolleeField;
            if (in_array($enrolleeField, $editableFields, true) || !blank($editReasons[$enrolleeField] ?? null)) {
                continue;
            }

            $verifiedValue = data_get($verifiedValues, (string) $providerField);
            if (blank($verifiedValue) || !array_key_exists($enrolleeField, $data)) {
                continue;
            }

            $submittedValue = $this->normalizeNinValue($enrolleeField, $data[$enrolleeField]);
            $normalizedVerified = $this->normalizeNinValue($enrolleeField, $verifiedValue);

            if (!$this->ninValuesMatch($submittedValue, $normalizedVerified)) {
                $replacement = $this->verifiedValueForSubmission($enrolleeField, $verifiedValue);
                if ($replacement !== null && !($replacement === '')) {
                    $data[$enrolleeField] = $replacement;
                }
            }
        }

        return $data;
    }

    private function matchingCachedDeviceVerification(array $recordPayload, array $data, bool $markUsed = false): ?NinVerificationCache
    {
        if ($this->matchingDeviceVerifiedValues($recordPayload, $data) === null) {
            return null;
        }

        $submittedNin = preg_replace('/\D+/', '', (string) ($data['nin'] ?? data_get($recordPayload, 'data.nin', '')));
        if ($submittedNin === '') {
            return null;
        }

        $cache = NinVerificationCache::query()
            ->where('nin', $submittedNin)
            ->first();

        if (!$cache || (array) $cache->provider_data === []) {
            return null;
        }

        $cachedNin = preg_replace('/\D+/', '', (string) data_get($cache->provider_data, 'nin', $cache->nin));
        if ($cachedNin !== '' && $cachedNin !== $submittedNin) {
            return null;
        }

        if ($markUsed) {
            $cache->forceFill([
                'last_used_at' => now(),
                'hit_count' => (int) $cache->hit_count + 1,
            ])->save();

            return $cache->fresh();
        }

        return $cache;
    }

    private function matchingDeviceVerifiedValues(array $recordPayload, array $data): ?array
    {
        $verifiedValues = (array) ($recordPayload['nin_verified_values'] ?? []);
        if ($verifiedValues === []) {
            return null;
        }

        $submittedNin = preg_replace('/\D+/', '', (string) ($data['nin'] ?? data_get($recordPayload, 'data.nin', '')));
        $verifiedNin = preg_replace('/\D+/', '', (string) data_get($verifiedValues, 'nin', ''));

        if ($submittedNin === '' || $verifiedNin === '' || $submittedNin !== $verifiedNin) {
            return null;
        }

        return $verifiedValues;
    }

    private function verifiedValueForSubmission(string $field, mixed $value): mixed
    {
        if ($field === 'sex' || $field === 'gender') {
            return match ($this->normalizeNinValue($field, $value)) {
                'Male' => 1,
                'Female' => 2,
                default => null,
            };
        }

        if ($field === 'photo') {
            return null;
        }

        return $this->normalizeNinValue($field, $value);
    }

    private function enrolleeComparableValue(Enrollee $enrollee, string $field): mixed
    {
        return match ($field) {
            'sex', 'gender' => match ((int) $enrollee->sex) {
                1 => 'Male',
                2 => 'Female',
                default => null,
            },
            'date_of_birth' => $enrollee->date_of_birth?->toDateString(),
            'photo' => $enrollee->image_url,
            default => $enrollee->{$field} ?? null,
        };
    }

    private function normalizeNinValue(string $field, mixed $value): mixed
    {
        if ($field === 'sex' || $field === 'gender') {
            $normalized = Str::lower(trim((string) $value));

            return match ($normalized) {
                '1', 'm', 'male' => 'Male',
                '2', 'f', 'female' => 'Female',
                default => Str::title($normalized),
            };
        }

        if ($field === 'address' && is_array($value)) {
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

        return is_string($value) ? trim($value) : $value;
    }

    private function ninValuesMatch(mixed $left, mixed $right): bool
    {
        return Str::lower(trim((string) $left)) === Str::lower(trim((string) $right));
    }

    private function writeNinAutofillValue(array &$updates, string $field, mixed $value): void
    {
        if (blank($value)) {
            return;
        }

        if ($field === 'sex' || $field === 'gender') {
            $updates['sex'] = match ($this->normalizeNinValue($field, $value)) {
                'Male' => 1,
                'Female' => 2,
                default => $updates['sex'] ?? null,
            };

            if ($updates['sex'] === null) {
                unset($updates['sex']);
            }

            return;
        }

        if ($field === 'photo') {
            $updates['image_url'] = (string) $value;
            return;
        }

        $updates[$field] = $field === 'date_of_birth' ? $value : (string) $value;
    }

    private function transition(MobileEnrollmentRecord $record, string $status, ?string $reason = null): array
    {
        $record->forceFill([
            'status' => $status,
            'status_reason' => $reason,
            'synced_at' => now(),
        ])->save();

        $this->audit($record, 'mobile_enrollment_' . $status, $reason ?: "Mobile enrollment moved to {$status}.");

        return $this->recordResponse($record->fresh(['enrollee', 'duplicateOf']));
    }

    private function failSync(MobileEnrollmentRecord $record, string $reason): array
    {
        $record->forceFill([
            'status' => MobileEnrollmentRecord::STATUS_SYNC_FAILED,
            'status_reason' => $reason,
            'synced_at' => now(),
        ])->save();

        $this->audit($record, 'mobile_enrollment_sync_failed', $reason);

        return $this->recordResponse($record->fresh(['enrollee', 'duplicateOf']));
    }

    private function recordResponse(MobileEnrollmentRecord $record): array
    {
        return [
            'id' => $record->id,
            'client_record_id' => $record->client_record_id,
            'sync_batch_id' => $record->sync_batch_id,
            'status' => $record->status,
            'status_reason' => $record->status_reason,
            'enrollee_id' => $record->enrollee_id,
            'enrollee_code' => $record->enrollee?->enrollee_id,
            'duplicate_of_enrollee_id' => $record->duplicate_of_enrollee_id,
            'migration_hints' => $record->migration_hints,
            'attachment_status' => $record->attachment_status,
            'nin_verified_data' => $record->nin_verified_data,
            'nin_autofill_changes' => $record->nin_autofill_changes,
            'nin_conflicts' => $record->nin_conflicts,
            'location_payload' => $record->location_payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $location
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function normalizeLocationPayload(array $location, array $policy): array
    {
        $normalized = [
            'permission_status' => (string) ($location['permission_status'] ?? 'unknown'),
            'mocked' => (bool) ($location['mocked'] ?? false),
            'error' => $location['error'] ?? null,
        ];

        foreach (['capture_location', 'submit_location'] as $key) {
            $point = is_array($location[$key] ?? null) ? $location[$key] : null;
            if (!$point) {
                continue;
            }

            $latitude = isset($point['latitude']) && is_numeric($point['latitude']) ? round((float) $point['latitude'], 7) : null;
            $longitude = isset($point['longitude']) && is_numeric($point['longitude']) ? round((float) $point['longitude'], 7) : null;
            if ($latitude === null || $longitude === null) {
                continue;
            }

            $normalized[$key] = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => isset($point['accuracy_meters']) && is_numeric($point['accuracy_meters']) ? round((float) $point['accuracy_meters'], 2) : null,
                'captured_at' => $point['captured_at'] ?? now()->toIso8601String(),
                'source' => $point['source'] ?? 'device_gps',
                'mocked' => (bool) ($point['mocked'] ?? $location['mocked'] ?? false),
            ];
        }

        $normalized['policy_mode'] = $policy['mode'] ?? 'disabled';
        $normalized['capture_points'] = $policy['capture_points'] ?? ['start', 'submit'];

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $locationPayload
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>  $recordPayload
     */
    private function validateLocationRequirement(array $locationPayload, array $policy, array $recordPayload): void
    {
        if (!($policy['enabled'] ?? false)) {
            return;
        }

        $mode = (string) ($policy['mode'] ?? 'disabled');
        if ($mode === 'disabled') {
            return;
        }

        $requiredPoint = $mode === 'required_on_submit' ? 'submit_location' : 'capture_location';
        $hasLocation = !empty($locationPayload[$requiredPoint]) || !empty($locationPayload['submit_location']) || !empty($locationPayload['capture_location']);

        if (!$hasLocation && !($policy['allow_submission_without_location'] ?? true)) {
            throw new RuntimeException('Location capture is required before this enrollment can be submitted.');
        }

        $minimumAccuracy = (float) ($policy['minimum_accuracy_meters'] ?? 0);
        if ($minimumAccuracy <= 0) {
            return;
        }

        $point = $locationPayload[$requiredPoint] ?? $locationPayload['submit_location'] ?? $locationPayload['capture_location'] ?? null;
        $accuracy = is_array($point) ? (float) ($point['accuracy_meters'] ?? 0) : 0;

        if ($hasLocation && $accuracy > 0 && $accuracy > $minimumAccuracy && !($policy['allow_submission_without_location'] ?? true)) {
            throw new RuntimeException("Captured location accuracy ({$accuracy}m) is weaker than the required {$minimumAccuracy}m threshold.");
        }
    }

    /**
     * @param  array<string, mixed>  $locationPayload
     * @return array<string, mixed>
     */
    private function enrolleeLocationAudit(array $locationPayload): array
    {
        $mapPoint = function (?array $point): ?array {
            if (!$point || !isset($point['latitude'], $point['longitude'])) {
                return null;
            }

            return [
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'accuracy_meters' => $point['accuracy_meters'] ?? null,
                'recorded_at' => $point['captured_at'] ?? null,
                'source' => $point['source'] ?? 'device_gps',
                'mocked' => (bool) ($point['mocked'] ?? false),
                'google_maps_url' => sprintf('https://maps.google.com/?q=%s,%s', $point['latitude'], $point['longitude']),
            ];
        };

        return [
            'permission_status' => $locationPayload['permission_status'] ?? 'unknown',
            'mocked' => (bool) ($locationPayload['mocked'] ?? false),
            'error' => $locationPayload['error'] ?? null,
            'capture_location' => $mapPoint(is_array($locationPayload['capture_location'] ?? null) ? $locationPayload['capture_location'] : null),
            'submit_location' => $mapPoint(is_array($locationPayload['submit_location'] ?? null) ? $locationPayload['submit_location'] : null),
            'captured_via' => 'mobile_officer_device',
            'captured_offline' => (bool) ($locationPayload['captured_offline'] ?? false),
        ];
    }

    private function audit(MobileEnrollmentRecord $record, string $action, string $description): void
    {
        AuditTrail::create([
            'auditable_type' => MobileEnrollmentRecord::class,
            'auditable_id' => $record->id,
            'action' => $action,
            'description' => $description,
            'user_id' => $record->officer_user_id,
            'new_values' => [
                'status' => $record->status,
                'client_record_id' => $record->client_record_id,
                'device_id' => $record->officer_device_id,
            ],
        ]);
    }

    private function uniquePurchaseReference(): string
    {
        do {
            $reference = 'MPIN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));
        } while (PremiumPurchase::where('payment_reference', $reference)->exists());

        return $reference;
    }
}
