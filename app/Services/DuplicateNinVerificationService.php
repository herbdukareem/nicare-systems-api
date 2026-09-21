<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\DuplicateNinVerificationBatch;
use App\Models\DuplicateNinVerificationCandidate;
use App\Models\DuplicateNinVerificationDecision;
use App\Models\DuplicateNinVerificationItem;
use App\Models\Enrollee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DuplicateNinVerificationService
{
    public function __construct(
        private readonly NinVerificationService $ninVerificationService,
        private readonly EnrolleeDuplicateNinService $duplicateNinService
    ) {
    }

    public function createBatch(int $requestedCount, User $user): DuplicateNinVerificationBatch
    {
        @set_time_limit(0);

        $requestedCount = min(max($requestedCount, 1), 5000);

        return DB::transaction(function () use ($requestedCount, $user): DuplicateNinVerificationBatch {
            $now = now();
            $batch = DuplicateNinVerificationBatch::query()->create([
                'reference' => 'DUP-NIN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5)),
                'requested_count' => $requestedCount,
                'status' => DuplicateNinVerificationBatch::STATUS_DRAFT,
                'created_by' => $user->id,
                'metadata' => [
                    'selection_rule' => 'Duplicate NIN groups with nin_verification_status = not_started.',
                ],
            ]);

            $nins = DB::table('enrollees')
                ->select('nin', DB::raw('COUNT(*) as duplicate_count'))
                ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_NOT_STARTED)
                ->whereNotNull('nin')
                ->where('nin', '!=', '')
                ->groupBy('nin')
                ->havingRaw('COUNT(*) > 1')
                ->orderByDesc('duplicate_count')
                ->orderBy('nin')
                ->limit($requestedCount)
                ->pluck('nin');

            $itemRows = $nins
                ->map(fn ($nin): array => [
                    'batch_id' => $batch->id,
                    'nin' => (string) $nin,
                    'nin_hash' => hash('sha256', (string) $nin),
                    'status' => DuplicateNinVerificationItem::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            foreach (array_chunk($itemRows, 1000) as $chunk) {
                DuplicateNinVerificationItem::query()->insert($chunk);
            }

            $itemIdsByNin = DuplicateNinVerificationItem::query()
                ->where('batch_id', $batch->id)
                ->pluck('id', 'nin');

            $candidateRows = [];
            $candidateCount = 0;

            Enrollee::query()
                ->whereIn('nin', $nins->all())
                ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_NOT_STARTED)
                ->orderBy('nin')
                ->orderBy('id')
                ->get()
                ->each(function (Enrollee $enrollee) use (&$candidateRows, &$candidateCount, $itemIdsByNin, $now): void {
                    $itemId = (int) ($itemIdsByNin[$enrollee->nin] ?? 0);
                    if ($itemId <= 0) {
                        return;
                    }

                    $candidateRows[] = array_merge(
                        $this->candidateSnapshot($itemId, $enrollee),
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                    $candidateCount++;

                    if (count($candidateRows) >= 1000) {
                        DuplicateNinVerificationCandidate::query()->insert($candidateRows);
                        $candidateRows = [];
                    }
                });

            if ($candidateRows !== []) {
                DuplicateNinVerificationCandidate::query()->insert($candidateRows);
            }

            $batch->forceFill([
                'unique_nin_count' => $nins->count(),
                'total_candidate_count' => $candidateCount,
            ])->save();

            return $batch->fresh(['items.candidates']);
        }, 3);
    }

    public function verifyItem(DuplicateNinVerificationItem $item, User $user, bool $autoApply = true): DuplicateNinVerificationItem
    {
        $item->loadMissing('batch', 'candidates');

        $key = trim((string) config('services.duplicate_nin_provider.key'));
        if ($key === '') {
            throw new RuntimeException('Add DUPLICATE_NIN_PROVIDER_KEY to .env before running duplicate NIN verification.');
        }

        $providerName = trim((string) config('services.duplicate_nin_provider.name')) ?: 'Duplicate NIN Verification';
        $providerOverrides = array_merge([
            'api_key' => $key,
            'provider_name' => $providerName,
        ], $this->duplicateProviderUrlOverrides());

        try {
            $verification = $this->ninVerificationService->verifyRaw(
                (string) $item->nin,
                $user,
                true,
                'duplicate_nin',
                $providerOverrides
            );
        } catch (Throwable $exception) {
            $item->forceFill([
                'status' => DuplicateNinVerificationItem::STATUS_FAILED,
                'failure_message' => $exception->getMessage(),
            ])->save();

            $this->recordDecision($item, 'verify_failed', null, $user, $exception->getMessage(), [], [], []);
            $this->refreshBatchStatus($item->batch);

            throw $exception;
        }

        $providerData = (array) ($verification['provider_data'] ?? []);
        $matches = $this->scoreCandidates($item, $providerData);
        $selectedCandidate = $this->bestConfidentCandidate($matches);

        $item->forceFill([
            'status' => $selectedCandidate
                ? DuplicateNinVerificationItem::STATUS_VERIFIED
                : DuplicateNinVerificationItem::STATUS_NEEDS_REVIEW,
            'provider_name' => (string) ($verification['provider_name'] ?? $providerName),
            'provider_data' => $providerData,
            'comparison_summary' => $matches,
            'matched_enrollee_id' => $selectedCandidate?->enrollee_id,
            'verified_at' => now(),
            'failure_message' => null,
        ])->save();

        if ($autoApply && $selectedCandidate) {
            return $this->applyDecision(
                $item->fresh(['batch', 'candidates']),
                (int) $selectedCandidate->enrollee_id,
                $user,
                'auto',
                $this->autoDecisionNote($matches, (int) $selectedCandidate->enrollee_id)
            );
        }

        $this->refreshBatchStatus($item->batch);

        return $item->fresh(['candidates']);
    }

    public function verifyBatch(DuplicateNinVerificationBatch $batch, User $user): DuplicateNinVerificationBatch
    {
        @set_time_limit(0);

        $batch->forceFill([
            'status' => DuplicateNinVerificationBatch::STATUS_PROCESSING,
            'started_at' => $batch->started_at ?: now(),
        ])->save();

        $batch->items()
            ->whereIn('status', [
                DuplicateNinVerificationItem::STATUS_PENDING,
                DuplicateNinVerificationItem::STATUS_FAILED,
                DuplicateNinVerificationItem::STATUS_NEEDS_REVIEW,
            ])
            ->orderBy('id')
            ->get()
            ->each(function (DuplicateNinVerificationItem $item) use ($user): void {
                try {
                    $this->verifyItem($item, $user, true);
                } catch (Throwable) {
                    // Item failure is already recorded; continue with the rest of the batch.
                }
            });

        $this->refreshBatchStatus($batch->fresh());

        return $batch->fresh(['items.candidates']);
    }

    public function applyDecision(
        DuplicateNinVerificationItem $item,
        int $selectedEnrolleeId,
        User $user,
        string $source = 'manual',
        ?string $note = null
    ): DuplicateNinVerificationItem {
        $item->loadMissing('batch', 'candidates');

        if (!$item->candidates->contains('enrollee_id', $selectedEnrolleeId)) {
            throw new RuntimeException('Select one of the affected enrollees for this duplicate NIN.');
        }

        return DB::transaction(function () use ($item, $selectedEnrolleeId, $user, $source, $note): DuplicateNinVerificationItem {
            $lockedCandidates = DuplicateNinVerificationCandidate::query()
                ->where('item_id', $item->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $enrollees = Enrollee::query()
                ->whereIn('id', $lockedCandidates->pluck('enrollee_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $beforeValues = [];
            $afterValues = [];
            $providerData = (array) ($item->provider_data ?: []);
            $verifiedAt = now();
            $previousSelected = $item->matched_enrollee_id;

            foreach ($lockedCandidates as $candidate) {
                $enrollee = $enrollees->get($candidate->enrollee_id);
                if (!$enrollee) {
                    continue;
                }

                $beforeValues[$enrollee->id] = $this->enrolleeDecisionSnapshot($enrollee);
                $keepsNin = (int) $enrollee->id === $selectedEnrolleeId;
                $meta = is_array($enrollee->nin_verification_meta) ? $enrollee->nin_verification_meta : [];
                $meta['duplicate_nin_verification'] = [
                    'batch_id' => $item->batch_id,
                    'item_id' => $item->id,
                    'decision_source' => $source,
                    'decided_at' => $verifiedAt->toIso8601String(),
                    'decided_by' => $user->id,
                    'selected_enrollee_id' => $selectedEnrolleeId,
                    'original_nin' => $candidate->original_nin,
                    'note' => $note,
                ];

                if ($keepsNin) {
                    $enrollee->forceFill([
                        'nin' => (string) ($providerData['nin'] ?? $candidate->original_nin ?? $item->nin),
                        'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED,
                        'nin_verified_at' => $verifiedAt,
                        'nin_verified_by' => $user->id,
                        'nin_verification_provider' => $item->provider_name,
                        'nin_verification_data' => [
                            'provider_data' => $providerData,
                            'comparison' => $this->ninVerificationService->comparisonFor($enrollee, $providerData),
                            'verified_nin' => $providerData['nin'] ?? $candidate->original_nin ?? $item->nin,
                            'duplicate_nin_verification_item_id' => $item->id,
                        ],
                        'nin_verification_meta' => $meta,
                        'has_duplicate_nin' => false,
                    ])->save();
                } else {
                    $enrollee->forceFill([
                        'nin' => null,
                        'nin_verification_status' => Enrollee::NIN_VERIFICATION_NOT_PROVIDED,
                        'nin_verification_meta' => $meta,
                        'has_duplicate_nin' => false,
                    ])->save();
                }

                $candidate->forceFill([
                    'keeps_nin' => $keepsNin,
                    'nin_cleared' => !$keepsNin,
                    'cleared_at' => $keepsNin ? null : $verifiedAt,
                ])->save();

                $afterValues[$enrollee->id] = $this->enrolleeDecisionSnapshot($enrollee->fresh());

                AuditTrail::query()->create([
                    'auditable_type' => Enrollee::class,
                    'auditable_id' => $enrollee->id,
                    'action' => $keepsNin ? 'duplicate_nin_verified' : 'duplicate_nin_cleared',
                    'description' => $keepsNin
                        ? 'Duplicate NIN verification selected this enrollee to keep the NIN.'
                        : 'Duplicate NIN verification cleared this duplicate NIN from the enrollee.',
                    'user_id' => $user->id,
                    'old_values' => $beforeValues[$enrollee->id],
                    'new_values' => $afterValues[$enrollee->id],
                ]);
            }

            $item->forceFill([
                'status' => DuplicateNinVerificationItem::STATUS_APPLIED,
                'matched_enrollee_id' => $selectedEnrolleeId,
                'decision_source' => $source,
                'decided_by' => $user->id,
                'decided_at' => $verifiedAt,
                'applied_at' => $verifiedAt,
                'decision_note' => $note,
                'failure_message' => null,
            ])->save();

            $this->recordDecision(
                $item,
                $source === 'auto' ? 'auto_apply' : 'manual_apply',
                $selectedEnrolleeId,
                $user,
                $note,
                $providerData,
                $beforeValues,
                $afterValues,
                $previousSelected ? (int) $previousSelected : null
            );

            $this->duplicateNinService->refreshForNins([(string) $item->nin]);
            $this->refreshBatchStatus($item->batch);

            return $item->fresh(['candidates']);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateSnapshot(int $itemId, Enrollee $enrollee): array
    {
        return [
            'item_id' => $itemId,
            'enrollee_id' => $enrollee->id,
            'enrollee_code' => $enrollee->enrollee_id,
            'full_name' => $enrollee->full_name,
            'first_name' => $enrollee->first_name,
            'middle_name' => $enrollee->middle_name,
            'last_name' => $enrollee->last_name,
            'date_of_birth' => $enrollee->date_of_birth?->toDateString(),
            'gender' => $this->genderLabel($enrollee->sex),
            'phone' => $enrollee->phone,
            'original_nin' => $enrollee->nin,
            'status_before' => $enrollee->nin_verification_status,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function duplicateProviderUrlOverrides(): array
    {
        $url = trim((string) config('services.duplicate_nin_provider.url'));
        if ($url === '') {
            return [];
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return ['base_url' => $url];
        }

        $baseUrl = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }

        $path = trim((string) ($parts['path'] ?? ''));
        if ($path === '' || $path === '/') {
            return ['base_url' => $baseUrl];
        }

        return [
            'base_url' => $baseUrl,
            'verify_endpoint' => $path,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scoreCandidates(DuplicateNinVerificationItem $item, array $providerData): array
    {
        $item->loadMissing('candidates');

        return $item->candidates
            ->map(function (DuplicateNinVerificationCandidate $candidate) use ($providerData): array {
                $checks = [
                    'first_name' => $this->fieldMatches($candidate->first_name, $providerData['first_name'] ?? null),
                    'middle_name' => $this->fieldMatches($candidate->middle_name, $providerData['middle_name'] ?? null, true),
                    'last_name' => $this->fieldMatches($candidate->last_name, $providerData['last_name'] ?? null),
                    'date_of_birth' => $this->fieldMatches($candidate->date_of_birth?->toDateString(), $providerData['date_of_birth'] ?? null),
                    'gender' => $this->fieldMatches($candidate->gender, $providerData['gender'] ?? null, true),
                    'phone' => $this->fieldMatches($candidate->phone, $providerData['phone'] ?? null, true),
                ];

                $score = 0;
                $score += $checks['first_name'] ? 20 : 0;
                $score += $checks['middle_name'] ? 10 : 0;
                $score += $checks['last_name'] ? 25 : 0;
                $score += $checks['date_of_birth'] ? 30 : 0;
                $score += $checks['gender'] ? 10 : 0;
                $score += $checks['phone'] ? 5 : 0;

                $confident = ($checks['first_name'] && $checks['last_name'] && $checks['date_of_birth'])
                    || $score >= 85;

                $candidate->forceFill([
                    'match_score' => $score,
                    'match_result' => [
                        'checks' => $checks,
                        'confident' => $confident,
                    ],
                ])->save();

                return [
                    'candidate_id' => $candidate->id,
                    'enrollee_id' => $candidate->enrollee_id,
                    'enrollee_code' => $candidate->enrollee_code,
                    'full_name' => $candidate->full_name,
                    'score' => $score,
                    'checks' => $checks,
                    'confident' => $confident,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    private function bestConfidentCandidate(array $matches): ?DuplicateNinVerificationCandidate
    {
        $best = collect($matches)
            ->filter(fn (array $match): bool => (bool) ($match['confident'] ?? false))
            ->sortBy([
                ['score', 'desc'],
                ['enrollee_id', 'asc'],
            ])
            ->first();

        if (!$best) {
            return null;
        }

        return DuplicateNinVerificationCandidate::query()->find((int) $best['candidate_id']);
    }

    private function fieldMatches(mixed $provided, mixed $verified, bool $optional = false): bool
    {
        if (blank($provided) || blank($verified)) {
            return $optional && blank($provided) && blank($verified);
        }

        return $this->normalizeComparable($provided) === $this->normalizeComparable($verified);
    }

    private function normalizeComparable(mixed $value): string
    {
        $value = Str::lower(trim((string) $value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
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
     * @return array<string, mixed>
     */
    private function enrolleeDecisionSnapshot(Enrollee $enrollee): array
    {
        return [
            'id' => $enrollee->id,
            'enrollee_id' => $enrollee->enrollee_id,
            'nin' => $enrollee->nin,
            'nin_verification_status' => $enrollee->nin_verification_status,
            'nin_verification_provider' => $enrollee->nin_verification_provider,
            'nin_verified_at' => $enrollee->nin_verified_at?->toIso8601String(),
        ];
    }

    private function recordDecision(
        DuplicateNinVerificationItem $item,
        string $action,
        ?int $selectedEnrolleeId,
        ?User $user,
        ?string $note,
        array $providerData,
        array $beforeValues,
        array $afterValues,
        ?int $previousSelectedEnrolleeId = null
    ): DuplicateNinVerificationDecision {
        return DuplicateNinVerificationDecision::query()->create([
            'batch_id' => $item->batch_id,
            'item_id' => $item->id,
            'action' => $action,
            'selected_enrollee_id' => $selectedEnrolleeId,
            'previous_selected_enrollee_id' => $previousSelectedEnrolleeId,
            'user_id' => $user?->id,
            'note' => $note,
            'provider_data' => $providerData,
            'before_values' => $beforeValues,
            'after_values' => $afterValues,
        ]);
    }

    private function refreshBatchStatus(DuplicateNinVerificationBatch $batch): void
    {
        $counts = $batch->items()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $counts->sum();
        $completed = (int) ($counts[DuplicateNinVerificationItem::STATUS_APPLIED] ?? 0)
            + (int) ($counts[DuplicateNinVerificationItem::STATUS_FAILED] ?? 0);

        $status = match (true) {
            $total === 0 => DuplicateNinVerificationBatch::STATUS_COMPLETED,
            $completed === 0 => $batch->started_at ? DuplicateNinVerificationBatch::STATUS_PROCESSING : DuplicateNinVerificationBatch::STATUS_DRAFT,
            $completed >= $total => DuplicateNinVerificationBatch::STATUS_COMPLETED,
            default => DuplicateNinVerificationBatch::STATUS_PARTIAL,
        };

        $batch->forceFill([
            'status' => $status,
            'completed_at' => $status === DuplicateNinVerificationBatch::STATUS_COMPLETED ? now() : null,
        ])->save();
    }

    private function autoDecisionNote(array $matches, int $selectedEnrolleeId): string
    {
        $confidentCount = collect($matches)->where('confident', true)->count();

        if ($confidentCount > 1) {
            return 'Auto-selected highest scoring enrollee among multiple provider-data matches.';
        }

        return 'Auto-selected the only confident provider-data match.';
    }
}
