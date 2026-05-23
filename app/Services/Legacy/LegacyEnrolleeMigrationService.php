<?php

namespace App\Services\Legacy;

use App\Models\Enrollee;
use App\Models\LegacyMigrationLog;
use App\Models\PremiumPin;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Services\PremiumCoverageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegacyEnrolleeMigrationService
{
    private ?int $systemUserId = null;

    public function __construct(
        private LegacyEnrolleeMapper $mapper,
        private PremiumCoverageService $coverageService
    ) {
    }

    public function migrate(object $legacy, string $sourceTable, bool $dryRun = false): array
    {
        if ($dryRun) {
            DB::beginTransaction();
            try {
                $mapped = $this->mapper->map($legacy, $sourceTable);
                $existing = $this->findExistingEnrollee($mapped);
                if (!$existing) {
                    $this->ensureUniqueEnrolleeId($mapped);
                }
                DB::rollBack();

                return [
                    'status' => 'dry-run',
                    'message' => ($existing ? 'Would update matched enrollee ' . $existing->id : 'Would create enrollee') . ' for ' . $mapped['legacy_enrolment_number'],
                    'mapped' => $mapped,
                    'duplicate_matched' => (bool) $existing,
                ];
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return DB::transaction(function () use ($legacy, $sourceTable) {
            $mapped = $this->mapper->map($legacy, $sourceTable);
            $existing = $this->findExistingEnrollee($mapped);
            $systemUserId = $this->systemUserId();

            if (!$existing) {
                $this->ensureUniqueEnrolleeId($mapped);
            }

            $enrolleeData = $mapped['enrollee'] + ['created_by' => $systemUserId];
            $enrolleeData['created_by'] = $existing?->created_by ?: $systemUserId;

            if ($existing) {
                $existing->fill($enrolleeData);
                $existing->save();
                $enrollee = $existing->fresh();
            } else {
                $enrollee = Enrollee::create($enrolleeData);
            }

            $purchase = $this->createOrUpdatePurchase($mapped);
            $enrollee = $this->applyCoverageToEnrollee($enrollee, $mapped);
            $this->linkBenefactor($enrollee, $mapped, $purchase);
            $this->log($mapped, $enrollee, 'migrated', $existing ? 'Matched existing enrollee and updated coverage.' : 'Created enrollee and coverage.', $legacy);

            return [
                'status' => 'migrated',
                'message' => $existing ? 'Updated existing enrollee.' : 'Created enrollee.',
                'enrollee' => $enrollee,
                'coverage' => [
                    'coverage_start_date' => $enrollee->coverage_start_date,
                    'coverage_end_date' => $enrollee->coverage_end_date,
                ],
                'purchase' => $purchase,
                'mapped' => $mapped,
                'duplicate_matched' => (bool) $existing,
            ];
        });
    }

    public function logFailure(object $legacy, string $sourceTable, Throwable $exception): void
    {
        LegacyMigrationLog::updateOrCreate(
            ['source_table' => $sourceTable, 'legacy_id' => (int) ($legacy->id ?? 0)],
            [
                'legacy_enrolment_number' => $legacy->enrolment_number ?? null,
                'migration_status' => 'failed',
                'message' => $exception->getMessage(),
                'legacy_payload' => json_decode(json_encode($legacy), true),
            ]
        );
    }

    private function createOrUpdatePurchase(array $mapped): ?PremiumPurchase
    {
        if (!$mapped['purchase']) {
            return null;
        }

        $existing = PremiumPurchase::where('payment_reference', $mapped['purchase']['payment_reference'])->first();
        if ($existing) {
            $existing->fill($mapped['purchase']);
            $existing->save();

            return $existing->fresh();
        }

        return $this->coverageService->createPurchase($mapped['purchase']);
    }

    private function applyCoverageToEnrollee(Enrollee $enrollee, array $mapped): Enrollee
    {
        $coverageData = $mapped['coverage'];

        $enrollee->update([
            'insurance_programme_id' => $coverageData['insurance_programme_id'] ?? $enrollee->insurance_programme_id,
            'enrollee_category_id' => $coverageData['enrollee_category_id'] ?? $enrollee->enrollee_category_id,
            'premium_plan_id' => $coverageData['premium_plan_id'] ?? $enrollee->premium_plan_id,
            'premium_pin_id' => $coverageData['premium_pin_id'] ?? $enrollee->premium_pin_id,
            'benefit_package_id' => $coverageData['benefit_package_id'] ?? $enrollee->benefit_package_id,
            'facility_id' => $coverageData['facility_id'] ?? $enrollee->facility_id,
            'benefactor_id' => $coverageData['benefactor_id'] ?? $enrollee->benefactor_id,
            'funding_type_id' => $coverageData['funding_type_id'] ?? $enrollee->funding_type_id,
            'coverage_start_date' => $coverageData['coverage_start_date'],
            'coverage_end_date' => $coverageData['coverage_end_date'],
            'status' => 1,
            'approval_date' => $enrollee->approval_date ?? now(),
            'approved_by' => $enrollee->approved_by ?? $this->systemUserId(),
        ]);

        if (!empty($coverageData['premium_pin_id'])) {
            PremiumPin::whereKey($coverageData['premium_pin_id'])->update([
                'status' => PremiumPin::STATUS_USED,
                'used_at' => $enrollee->coverage_start_date ?? now(),
                'used_by_enrollee_id' => $enrollee->id,
            ]);
        }

        return $enrollee->fresh();
    }

    private function linkBenefactor(Enrollee $enrollee, array $mapped, ?PremiumPurchase $purchase): void
    {
        if (empty($mapped['coverage']['benefactor_id']) || !Schema::hasTable('benefactor_enrollees')) {
            return;
        }

        DB::table('benefactor_enrollees')->updateOrInsert(
            [
                'benefactor_id' => $mapped['coverage']['benefactor_id'],
                'enrollee_id' => $enrollee->id,
            ],
            [
                'premium_purchase_id' => $purchase?->id,
                'relationship' => 'legacy_funding',
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function findExistingEnrollee(array $mapped): ?Enrollee
    {
        $log = LegacyMigrationLog::where('source_table', $mapped['source_table'])
            ->where('legacy_id', $mapped['legacy_id'])
            ->whereNotNull('new_enrollee_id')
            ->first();
        if ($log?->enrollee) {
            return $log->enrollee;
        }

        if ($mapped['dedupe']['shin']) {
            $match = Enrollee::where('enrollee_id', $mapped['dedupe']['shin'])
                ->orWhere('legacy_enrollee_id', $mapped['dedupe']['shin'])
                ->first();
            if ($match) {
                return $this->hasSameLegacyId($match, $mapped) ? $match : null;
            }
        }

        if ($mapped['dedupe']['nin']) {
            $match = Enrollee::where('nin', $mapped['dedupe']['nin'])->first();
            if ($match) {
                return $match;
            }
        }

        if ($mapped['dedupe']['phone'] && $mapped['dedupe']['first_name'] && $mapped['dedupe']['last_name'] && $mapped['dedupe']['date_of_birth']) {
            return Enrollee::where('phone', $mapped['dedupe']['phone'])
                ->where('first_name', $mapped['dedupe']['first_name'])
                ->where('last_name', $mapped['dedupe']['last_name'])
                ->whereDate('date_of_birth', $mapped['dedupe']['date_of_birth'])
                ->first();
        }

        return null;
    }

    private function ensureUniqueEnrolleeId(array &$mapped): void
    {
        $enrolleeId = $mapped['enrollee']['enrollee_id'] ?? null;
        if (!$enrolleeId) {
            return;
        }

        $duplicate = Enrollee::where('enrollee_id', $enrolleeId)->first();
        if (!$duplicate || $this->hasSameLegacyId($duplicate, $mapped)) {
            return;
        }

        $mapped['enrollee']['enrollee_id'] = $this->generateUniqueEnrolleeId($mapped);
    }

    private function hasSameLegacyId(Enrollee $enrollee, array $mapped): bool
    {
        return $enrollee->legacy_id !== null
            && (int) $enrollee->legacy_id === (int) $mapped['legacy_id'];
    }

    private function generateUniqueEnrolleeId(array $mapped): string
    {
        $source = str_contains($mapped['source_table'], 'formal') ? 'F' : 'I';
        $legacyId = (int) $mapped['legacy_id'];

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $suffix = $attempt === 0 ? '' : '-' . $attempt;
            $candidate = substr("LEG-{$source}-{$legacyId}{$suffix}", 0, 20);

            if (!Enrollee::where('enrollee_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to generate a unique enrollee ID for legacy row {$mapped['source_table']}:{$legacyId}.");
    }

    private function log(array $mapped, Enrollee $enrollee, string $status, string $message, object $legacy): void
    {
        LegacyMigrationLog::updateOrCreate(
            ['source_table' => $mapped['source_table'], 'legacy_id' => $mapped['legacy_id']],
            [
                'legacy_enrolment_number' => $mapped['legacy_enrolment_number'],
                'new_enrollee_id' => $enrollee->id,
                'migration_status' => $status,
                'message' => $message,
                'legacy_payload' => json_decode(json_encode($legacy), true),
            ]
        );
    }

    private function systemUserId(): int
    {
        if ($this->systemUserId !== null) {
            return $this->systemUserId;
        }

        $user = User::query()->first();
        if ($user) {
            return $this->systemUserId = $user->id;
        }

        return $this->systemUserId = User::create([
            'name' => 'Legacy Migration',
            'username' => 'legacy-migration',
            'email' => 'legacy-migration@nicare.local',
            'password' => bcrypt(str()->random(32)),
            'userable_type' => User::class,
            'userable_id' => 0,
            'status' => 1,
        ])->id;
    }
}
