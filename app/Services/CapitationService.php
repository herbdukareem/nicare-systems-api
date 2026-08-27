<?php

namespace App\Services;

use App\Exceptions\CapitationComputationException;
use App\Models\AuditTrail;
use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\CapitationDetailEnrollee;
use App\Models\CapitationPayment;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CapitationService
{
    public function __construct(
        private readonly EnrolleeDuplicateNinService $duplicateNinService
    ) {
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->capitationPeriodQuery();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('fundingType', fn ($fundingQuery) => $fundingQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->where('capitation_month', $filters['month']);
        }

        if (!empty($filters['funding_type_id'])) {
            $query->where('funding_type_id', $filters['funding_type_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1));
        $this->decorateCapitationPeriods($paginator->getCollection());

        return $paginator;
    }

    public function getAllWithoutPagination(): Collection
    {
        $periods = $this->capitationPeriodQuery()
            ->where('status', 1)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();

        $this->decorateCapitationPeriods($periods);

        return $periods;
    }

    public function findById(int $id): ?Capitation
    {
        $period = $this->capitationPeriodQuery()
            ->find($id);

        if ($period) {
            $this->decorateCapitationPeriod($period);
        }

        return $period;
    }

    public function create(array $data): Capitation
    {
        return Capitation::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $capitation = Capitation::find($id);

        return $capitation ? $capitation->update($data) : false;
    }

    public function delete(int $id): bool
    {
        $capitation = Capitation::find($id);

        return $capitation ? (bool) $capitation->delete() : false;
    }

    public function getByYear(int $year): Collection
    {
        $periods = $this->capitationPeriodQuery()
            ->where('year', $year)
            ->orderBy('capitation_month')
            ->get();

        $this->decorateCapitationPeriods($periods);

        return $periods;
    }

    public function getByUser(int $userId): Collection
    {
        $periods = $this->capitationPeriodQuery()
            ->where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();

        $this->decorateCapitationPeriods($periods);

        return $periods;
    }

    public function getByMonthYear(int $month, int $year): Collection
    {
        $periods = $this->capitationPeriodQuery()
            ->where('capitation_month', $month)
            ->where('year', $year)
            ->get();

        $this->decorateCapitationPeriods($periods);

        return $periods;
    }

    public function toggleStatus(int $id): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        $capitation->status = !$capitation->status;

        return $capitation->save();
    }

    public function getStatistics(): array
    {
        $currentYear = date('Y');
        $currentMonth = date('n');

        return [
            'total' => Capitation::count(),
            'active' => Capitation::where('status', 1)->count(),
            'current_year' => Capitation::where('year', $currentYear)->count(),
            'current_month' => Capitation::where('year', $currentYear)
                ->where('capitation_month', $currentMonth)
                ->count(),
        ];
    }

    public function getAvailableYears(): Collection
    {
        return Capitation::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
    }

    public function existsForMonthYear(int $month, int $year, int $userId): bool
    {
        return Capitation::where('capitation_month', $month)
            ->where('year', $year)
            ->where('user_id', $userId)
            ->exists();
    }

    public function createPeriod(array $data): Capitation
    {
        $exists = Capitation::where('capitation_month', (int) $data['capitation_month'])
            ->where('year', (int) $data['year'])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('A capitation period already exists for the selected month and year.');
        }

        $periodStart = \Carbon\Carbon::create((int) $data['year'], (int) $data['capitation_month'], 20);
        $fundingType = !empty($data['funding_type_id'])
            ? FundingType::findOrFail((int) $data['funding_type_id'])
            : null;

        return Capitation::create([
            'name' => $data['name'] ?? $periodStart->format('F Y') . ' Capitation',
            'period_start' => $periodStart->toDateString(),
            'period_end' => null,
            'capitated_month' => (int) $data['capitation_month'],
            'capitation_rate' => (float) ($fundingType?->capitation_rate ?? 0),
            'status' => false,
            'funding_type_id' => $fundingType?->id,
            'created_by' => auth()->id(),
            'user_id' => auth()->id(),
            'capitation_month' => (int) $data['capitation_month'],
            'year' => (int) $data['year'],
        ]);
    }

    public function eligibleProvidersForPeriod(
        Capitation $capitation,
        ?int $fundingTypeId = null,
        string $duplicateNinPolicy = Capitation::DUPLICATE_NIN_POLICY_EXCLUDE
    ): array
    {
        $fundingType = $fundingTypeId
            ? FundingType::find($fundingTypeId)
            : $capitation->fundingType;

        if (!$fundingType) {
            return [];
        }

        return $this->eligibleProviderRows($capitation, $fundingType, $duplicateNinPolicy)
            ->map(function ($row) use ($capitation, $fundingType): array {
                $detail = CapitationDetail::where('capitation_id', $capitation->id)
                    ->where('facility_id', $row->facility_id)
                    ->where('funding_type_id', $fundingType->id)
                    ->first();

                return [
                    'facility_id' => (int) $row->facility_id,
                    'facility_name' => $row->facility_name,
                    'hcp_code' => $row->hcp_code,
                    'lga' => $row->lga_name,
                    'total_enrollees' => (int) $row->enrollee_count,
                    'capitation_rate' => (float) $fundingType->capitation_rate,
                    'total_amount' => (int) $row->enrollee_count * (float) $fundingType->capitation_rate,
                    'is_generated' => (bool) $detail,
                    'selectable' => !$detail,
                    'reviewed_at' => $detail?->reviewed_at,
                    'approved_at' => $detail?->approved_at,
                    'paid_at' => $detail?->paid_at,
                    'status' => $detail?->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Generate capitation rows only for selected providers under the period funding type.
     *
     * @throws CapitationComputationException
     */
    public function computeForPeriod(
        Capitation $capitation,
        ?int $fundingTypeId = null,
        string $duplicateNinPolicy = Capitation::DUPLICATE_NIN_POLICY_EXCLUDE,
        array $facilityIds = []
    ): array
    {
        if ($facilityIds === []) {
            throw new CapitationComputationException('Select at least one facility to generate capitation.');
        }

        $fundingType = $fundingTypeId
            ? FundingType::find($fundingTypeId)
            : $capitation->fundingType;

        if (!$fundingType) {
            throw new CapitationComputationException('Select a funding type before generating capitation.');
        }

        $capitationRate = (float) $fundingType->capitation_rate;
        if ($capitationRate <= 0) {
            throw new CapitationComputationException("Funding type {$fundingType->name} does not have a capitation rate.");
        }

        $normalizedDuplicateNinPolicy = $this->normalizeDuplicateNinPolicy($duplicateNinPolicy);

        $eligibleProviders = $this->eligibleProviderRows($capitation, $fundingType, $normalizedDuplicateNinPolicy)
            ->whereIn('facility_id', $facilityIds)
            ->values();

        if ($eligibleProviders->isEmpty()) {
            throw new CapitationComputationException('No eligible providers found for the selected funding type and period.');
        }

        $results = [];

        DB::transaction(function () use ($capitation, $eligibleProviders, $fundingType, $capitationRate, $normalizedDuplicateNinPolicy, &$results): void {
            $capturedAt = now();

            foreach ($eligibleProviders as $provider) {
                $exists = CapitationDetail::where('capitation_id', $capitation->id)
                    ->where('facility_id', (int) $provider->facility_id)
                    ->where('funding_type_id', $fundingType->id)
                    ->exists();

                if ($exists) {
                    $results[] = [
                        'facility_id' => (int) $provider->facility_id,
                        'facility_name' => $provider->facility_name,
                        'enrollee_count' => $count,
                        'total_amount' => $totalAmount,
                        'skipped' => true,
                    ];

                    continue;
                }

                $eligibleEnrollees = $this->eligibleEnrolleesForFacility(
                    $capitation,
                    $fundingType,
                    $normalizedDuplicateNinPolicy,
                    (int) $provider->facility_id
                );

                $count = $eligibleEnrollees->count();
                $totalAmount = $count * $capitationRate;

                $detail = CapitationDetail::create([
                    'capitation_id' => $capitation->id,
                    'facility_id' => (int) $provider->facility_id,
                    'funding_type_id' => $fundingType->id,
                    'capitated_month' => $capitation->capitation_month,
                    'total_enrollees' => $count,
                    'total_enrolled' => $count,
                    'capitation_rate' => $capitationRate,
                    'rate' => $capitationRate,
                    'total_amount' => $totalAmount,
                    'amount' => $totalAmount,
                    'status' => 1,
                    'metadata' => [
                        'duplicate_nin_policy' => $normalizedDuplicateNinPolicy,
                        'generated_for_funding_type_id' => $fundingType->id,
                    ],
                ]);

                $this->storeEnrolleeSnapshotsForDetail(
                    $detail,
                    $capitation,
                    $fundingType,
                    $eligibleEnrollees,
                    $normalizedDuplicateNinPolicy,
                    $capturedAt,
                );

                Log::info("Capitation generated for facility {$provider->facility_id}: {$count} enrollees = {$totalAmount}");

                $results[] = [
                    'facility_id' => (int) $provider->facility_id,
                    'facility_name' => $provider->facility_name,
                    'enrollee_count' => $count,
                    'total_amount' => $totalAmount,
                    'skipped' => false,
                ];
            }

            $capitation->update(['computed_at' => now(), 'computed_by' => auth()->id()]);

            AuditTrail::create([
                'auditable_type' => Capitation::class,
                'auditable_id' => $capitation->id,
                'action' => 'capitation_generated',
                'description' => "Capitation period {$capitation->name} generated for selected providers. Facilities processed: " . count($results),
                'user_id' => auth()->id(),
                'new_values' => ['facilities' => count($results), 'computed_at' => now()],
            ]);
        });

        return $results;
    }

    public function getDetailsForStage(Capitation $capitation, string $stage = 'generated', ?int $fundingTypeId = null): Collection
    {
        $query = $capitation->capitationDetails()
            ->with(['facility.lga', 'facility.accountDetail.bank', 'fundingType'])
            ->when($fundingTypeId, fn ($detailQuery) => $detailQuery->where('funding_type_id', $fundingTypeId));

        match ($stage) {
            'review' => $query->whereNull('reviewed_at'),
            'approval' => $query->whereNotNull('reviewed_at')->whereNull('approved_at'),
            'payment' => $query->whereNotNull('approved_at')->whereNull('paid_at'),
            'paid' => $query->whereNotNull('paid_at'),
            default => null,
        };

        return $query->orderBy('facility_id')->get();
    }

    public function reviewDetails(Capitation $capitation, array $detailIds): int
    {
        return $this->transitionDetails($capitation, $detailIds, 'review');
    }

    public function approveDetails(Capitation $capitation, array $detailIds): int
    {
        return $this->transitionDetails($capitation, $detailIds, 'approval');
    }

    public function payDetails(Capitation $capitation, array $detailIds, array $data): int
    {
        $details = $capitation->capitationDetails()
            ->whereIn('id', $detailIds)
            ->whereNotNull('approved_at')
            ->whereNull('paid_at')
            ->get();

        if ($details->isEmpty()) {
            throw new \InvalidArgumentException('No approved unpaid capitation details were selected for payment.');
        }

        DB::transaction(function () use ($capitation, $details, $data): void {
            foreach ($details->groupBy('funding_type_id') as $fundingTypeId => $groupedDetails) {
                $payment = CapitationPayment::create([
                    'capitation_id' => $capitation->id,
                    'funding_type_id' => $fundingTypeId ?: $capitation->funding_type_id,
                    'amount' => (int) round($groupedDetails->sum('total_amount')),
                    'invoice_number' => substr($data['payment_reference'], 0, 12),
                    'description' => $data['description'] ?? "Capitation payment for {$capitation->name}",
                    'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                    'status' => 1,
                ]);

                CapitationDetail::whereIn('id', $groupedDetails->pluck('id'))->update([
                    'capitation_payment_id' => $payment->id,
                    'paid_by' => auth()->id(),
                    'paid_at' => $data['payment_date'] ?? now()->toDateString(),
                    'status' => 4,
                ]);
            }

            AuditTrail::create([
                'auditable_type' => Capitation::class,
                'auditable_id' => $capitation->id,
                'action' => 'capitation_details_paid',
                'description' => "Selected capitation details paid for {$capitation->name}.",
                'user_id' => auth()->id(),
                'new_values' => [
                    'details' => $details->pluck('id')->values(),
                    'payment_reference' => $data['payment_reference'],
                ],
            ]);
        });

        return $details->count();
    }

    public function finalise(Capitation $capitation): Capitation
    {
        if (auth()->id() === (int) $capitation->created_by) {
            throw new \InvalidArgumentException(
                'BR-06 violation: The officer who created this capitation period cannot also finalise it.'
            );
        }

        $capitation->update([
            'status' => true,
            'finalised_at' => now(),
            'finalised_by' => auth()->id(),
        ]);

        AuditTrail::create([
            'auditable_type' => Capitation::class,
            'auditable_id' => $capitation->id,
            'action' => 'capitation_finalised',
            'description' => "Capitation period {$capitation->name} finalised.",
            'user_id' => auth()->id(),
            'new_values' => ['finalised_at' => now(), 'finalised_by' => auth()->id()],
        ]);

        return $capitation;
    }

    public function markPaid(Capitation $capitation, array $data): Capitation
    {
        if (!$capitation->status) {
            throw new \InvalidArgumentException('Only finalised capitation periods can be marked paid.');
        }

        if (auth()->id() === (int) $capitation->created_by || auth()->id() === (int) $capitation->finalised_by) {
            throw new \InvalidArgumentException('BR-06 violation: A different finance officer must confirm capitation payment.');
        }

        $details = $capitation->capitationDetails()->get();
        if ($details->isEmpty()) {
            throw new \InvalidArgumentException('Cannot pay a capitation period without generated facility breakdown.');
        }

        if ($details->every(fn ($detail) => $detail->paid_at || $detail->capitation_payment_id)) {
            throw new \InvalidArgumentException('This capitation period has already been paid.');
        }

        DB::transaction(function () use ($capitation, $details, $data): void {
            foreach ($details->whereNull('paid_at')->groupBy('funding_type_id') as $fundingTypeId => $groupedDetails) {
                $payment = CapitationPayment::create([
                    'capitation_id' => $capitation->id,
                    'funding_type_id' => $fundingTypeId ?: $capitation->funding_type_id,
                    'amount' => (int) round($groupedDetails->sum('total_amount')),
                    'invoice_number' => substr($data['payment_reference'], 0, 12),
                    'description' => $data['description'] ?? "Capitation payment for {$capitation->name}",
                    'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                    'status' => 1,
                ]);

                CapitationDetail::whereIn('id', $groupedDetails->pluck('id'))->update([
                    'capitation_payment_id' => $payment->id,
                    'paid_by' => auth()->id(),
                    'paid_at' => $data['payment_date'] ?? now()->toDateString(),
                    'status' => 4,
                ]);
            }

            AuditTrail::create([
                'auditable_type' => Capitation::class,
                'auditable_id' => $capitation->id,
                'action' => 'capitation_paid',
                'description' => "Capitation period {$capitation->name} marked paid.",
                'user_id' => auth()->id(),
                'new_values' => [
                    'payment_reference' => $data['payment_reference'],
                    'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                ],
            ]);
        });

        return $capitation->fresh(['capitationDetails', 'capitationPayments']);
    }

    public function getBreakdown(Capitation $capitation, string $stage = 'generated', ?int $fundingTypeId = null): Collection
    {
        $query = $capitation->capitationDetails()->with(['facility.accountDetail.bank', 'fundingType']);

        if ($fundingTypeId !== null) {
            $query->where('funding_type_id', $fundingTypeId);
        }

        match ($stage) {
            'reviewed' => $query->whereNotNull('reviewed_at'),
            'approved' => $query->whereNotNull('approved_at'),
            'paid' => $query->whereNotNull('paid_at'),
            default => null,
        };

        return $query->orderBy('facility_id')->get();
    }

    public function getPaymentReport(Capitation $capitation, string $status): SupportCollection
    {
        $query = $capitation->capitationDetails()->with(['facility.lga', 'facility.ward', 'fundingType']);
        $this->applyPaymentReportStatusFilter($query, $status);

        return $query->orderBy('facility_id')->get()
            ->groupBy('facility_id')
            ->map(function (Collection $details): array {
                $facility = $details->first()?->facility;
                $amounts = $this->emptyPaymentReportAmounts();

                foreach ($details as $detail) {
                    $column = $this->paymentReportFundingColumn($detail->fundingType);
                    if ($column !== null) {
                        $amounts[$column] += (float) ($detail->total_amount ?? $detail->amount ?? 0);
                    }
                }

                return [
                    'provider_name' => $facility?->name ?? 'N/A',
                    'facility_code' => $facility?->hcp_code ?? '',
                    'lga' => $facility?->lga?->name ?? '',
                    'ward' => $facility?->ward?->name ?? '',
                    'total_enrollees' => $details->sum(fn ($detail) => (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0)),
                    ...$amounts,
                    'total_amount' => $details->sum(fn ($detail) => (float) ($detail->total_amount ?? $detail->amount ?? 0)),
                ];
            })
            ->sortBy('provider_name')
            ->values();
    }

    public function getHistoricalPaymentReportPreview(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeHistoricalPaymentReportFilters($filters);
        $details = $this->historicalPaymentReportDetails($normalizedFilters);
        $rows = $this->historicalPaymentReportRows($details, $normalizedFilters['scope']);
        $summary = $this->historicalPaymentReportSummary($details, $rows, $normalizedFilters);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 25)));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );

        return [
            'data' => $paginator,
            'summary' => $summary,
        ];
    }

    public function getHistoricalPaymentReportExport(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeHistoricalPaymentReportFilters($filters);
        $details = $this->historicalPaymentReportDetails($normalizedFilters);
        $rows = $this->historicalPaymentReportRows($details, $normalizedFilters['scope']);

        return [
            'rows' => $rows,
            'summary' => $this->historicalPaymentReportSummary($details, $rows, $normalizedFilters),
        ];
    }

    public function getEnrolleeSnapshotList(Capitation $capitation, array $filters = []): LengthAwarePaginator
    {
        $this->ensureEnrolleeSnapshotsAvailable($capitation, $filters);

        $query = $this->enrolleeSnapshotQuery($capitation, $filters)
            ->orderBy('facility_name')
            ->orderBy('full_name')
            ->orderBy('enrollee_number');

        return $query->paginate(
            (int) ($filters['per_page'] ?? 25),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );
    }

    public function getEnrolleeSnapshotExportQuery(Capitation $capitation, array $filters = []): EloquentBuilder
    {
        $this->ensureEnrolleeSnapshotsAvailable($capitation, $filters);

        return $this->enrolleeSnapshotQuery($capitation, $filters)
            ->orderBy('facility_name')
            ->orderBy('full_name')
            ->orderBy('enrollee_number');
    }

    public function getEnrolleeSnapshotSummary(Capitation $capitation, array $filters = []): array
    {
        $this->ensureEnrolleeSnapshotsAvailable($capitation, $filters);

        $query = $this->enrolleeSnapshotQuery($capitation, $filters);
        $snapshotCount = (clone $query)->count();

        return [
            'total_enrollees' => $snapshotCount,
            'facility_count' => (clone $query)->select('facility_id', 'facility_name')->distinct()->get()->count(),
            'funding_type_count' => (clone $query)->select('funding_type_id', 'funding_type_name')->distinct()->get()->count(),
            'captured_at' => (clone $query)->min('captured_at'),
            'has_generated_details' => $capitation->capitationDetails()->exists(),
            'has_snapshot_rows' => $snapshotCount > 0,
        ];
    }

    public function inspectEnrolleeSnapshotBackfill(Capitation $capitation, array $filters = []): array
    {
        $detailsInScope = $this->enrolleeSnapshotDetailsInScope($capitation, $filters);
        $missingDetails = $this->filterMissingEnrolleeSnapshotDetails($detailsInScope);
        $missingDetailIds = $missingDetails->pluck('id');

        return [
            'capitation_id' => $capitation->id,
            'capitation_name' => $capitation->name,
            'year' => (int) ($capitation->year ?? 0),
            'detail_count_in_scope' => $detailsInScope->count(),
            'missing_detail_count' => $missingDetails->count(),
            'expected_snapshot_row_count' => $missingDetails->sum(
                fn (CapitationDetail $detail) => max(0, (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0))
            ),
            'existing_snapshot_row_count' => $missingDetailIds->isEmpty()
                ? 0
                : CapitationDetailEnrollee::query()->whereIn('capitation_detail_id', $missingDetailIds->all())->count(),
            'is_legacy_import' => $this->isLegacyImportedCapitation($capitation),
        ];
    }

    public function backfillEnrolleeSnapshots(Capitation $capitation, array $filters = []): array
    {
        $report = $this->inspectEnrolleeSnapshotBackfill($capitation, $filters);
        $missingDetails = $this->missingEnrolleeSnapshotDetails($capitation, $filters);

        if ($missingDetails->isEmpty()) {
            return $report + [
                'changed' => false,
                'stored_snapshot_row_count' => 0,
            ];
        }

        if ($this->isLegacyImportedCapitation($capitation)) {
            $this->backfillLegacyEnrolleeSnapshots($capitation, $missingDetails);
        } else {
            $this->backfillCurrentEnrolleeSnapshots($capitation, $missingDetails);
        }

        return $report + [
            'changed' => true,
            'stored_snapshot_row_count' => CapitationDetailEnrollee::query()
                ->whereIn('capitation_detail_id', $missingDetails->pluck('id')->all())
                ->count(),
        ];
    }

    private function ensureEnrolleeSnapshotsAvailable(Capitation $capitation, array $filters = []): void
    {
        $missingDetails = $this->missingEnrolleeSnapshotDetails($capitation, $filters);

        if ($missingDetails->isEmpty()) {
            return;
        }

        if ($this->isLegacyImportedCapitation($capitation)) {
            $this->backfillLegacyEnrolleeSnapshots($capitation, $missingDetails);
            return;
        }

        $this->backfillCurrentEnrolleeSnapshots($capitation, $missingDetails);
    }

    private function missingEnrolleeSnapshotDetails(Capitation $capitation, array $filters = []): Collection
    {
        return $this->filterMissingEnrolleeSnapshotDetails(
            $this->enrolleeSnapshotDetailsInScope($capitation, $filters)
        );
    }

    private function enrolleeSnapshotDetailsInScope(Capitation $capitation, array $filters = []): Collection
    {
        return $capitation->capitationDetails()
            ->with(['facility.lga', 'fundingType'])
            ->withCount('enrolleeSnapshots')
            ->when(
                !empty($filters['funding_type_id']),
                fn ($query) => $query->where('funding_type_id', (int) $filters['funding_type_id'])
            )
            ->when(
                !empty($filters['facility_id']),
                fn ($query) => $query->where('facility_id', (int) $filters['facility_id'])
            )
            ->get()
            ->values();
    }

    private function filterMissingEnrolleeSnapshotDetails(Collection $details): Collection
    {
        return $details
            ->filter(function (CapitationDetail $detail): bool {
                $expectedCount = max(0, (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0));
                return (int) $detail->enrollee_snapshots_count < $expectedCount;
            })
            ->values();
    }

    private function isLegacyImportedCapitation(Capitation $capitation): bool
    {
        return data_get($capitation->metadata, 'source_table') === 'capitation_grouping'
            && (int) data_get($capitation->metadata, 'legacy_id') > 0;
    }

    private function backfillLegacyEnrolleeSnapshots(Capitation $capitation, Collection $details): void
    {
        $legacyGroupId = (int) data_get($capitation->metadata, 'legacy_id');
        if ($legacyGroupId <= 0) {
            return;
        }

        $detailDescriptors = [];
        $programmeTypesBySource = [];

        foreach ($details as $detail) {
            if (!$detail instanceof CapitationDetail) {
                continue;
            }

            $programmeType = $this->legacyProgrammeTypeForDetail($detail);
            $legacyProviderId = $this->legacyProviderIdForDetail($detail);
            if (!$programmeType || !$legacyProviderId) {
                continue;
            }

            $sourceTable = $this->legacySourceTableForProgramme($programmeType);
            $detailDescriptors[] = [
                'detail' => $detail,
                'programme_type' => $programmeType,
                'legacy_provider_id' => $legacyProviderId,
                'source_table' => $sourceTable,
                'bucket_key' => $this->legacySnapshotBucketKey($sourceTable, $programmeType, $legacyProviderId),
            ];

            $programmeTypesBySource[$sourceTable][$programmeType] = true;
        }

        if ($detailDescriptors === []) {
            return;
        }

        $legacyPools = [];

        foreach ($programmeTypesBySource as $sourceTable => $programmeTypes) {
            $legacyRows = DB::connection('legacy')
                ->table('capitation_enrollee_list as capitation_roster')
                ->join($sourceTable . ' as legacy_enrollees', 'legacy_enrollees.id', '=', 'capitation_roster.enrollee_id')
                ->select(
                    'capitation_roster.programme_type',
                    'legacy_enrollees.id as legacy_id',
                    'legacy_enrollees.enrolment_number',
                    'legacy_enrollees.surname',
                    'legacy_enrollees.first_name',
                    'legacy_enrollees.other_name',
                    'legacy_enrollees.nin',
                    'legacy_enrollees.national_identification_number',
                    'legacy_enrollees.phone_number',
                    'legacy_enrollees.sex',
                    'legacy_enrollees.date_of_birth',
                    'legacy_enrollees.provider_id',
                    'legacy_enrollees.lga as lga_reference',
                    'legacy_enrollees.ward as ward_reference',
                    'legacy_enrollees.cap_date_month',
                    'legacy_enrollees.date_expired',
                    'legacy_enrollees.status'
                )
                ->where('capitation_roster.group_id', $legacyGroupId)
                ->whereIn('capitation_roster.programme_type', array_keys($programmeTypes))
                ->orderBy('capitation_roster.programme_type')
                ->orderBy('legacy_enrollees.provider_id')
                ->orderBy('legacy_enrollees.id')
                ->get();

            foreach ($legacyRows as $legacyRow) {
                $bucketKey = $this->legacySnapshotBucketKey(
                    $sourceTable,
                    (string) $legacyRow->programme_type,
                    (int) $legacyRow->provider_id
                );

                $legacyPools[$bucketKey][] = $legacyRow;
            }
        }

        $duplicateNinPolicy = $this->normalizeDuplicateNinPolicy($capitation->duplicate_nin_policy ?? null);

        foreach ($detailDescriptors as $descriptor) {
            /** @var CapitationDetail $detail */
            $detail = $descriptor['detail'];
            $expectedCount = max(0, (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0));
            if ($expectedCount === 0) {
                continue;
            }

            $bucketKey = $descriptor['bucket_key'];
            $bucket = $legacyPools[$bucketKey] ?? [];
            $selectedLegacyRows = $this->takeLegacySnapshotRows($bucket, $expectedCount);
            $legacyPools[$bucketKey] = $bucket;

            if ($selectedLegacyRows === []) {
                continue;
            }

            $sourceTable = $descriptor['source_table'];
            $legacyIds = array_values(array_unique(array_map(
                static fn (object $legacyRow): int => (int) $legacyRow->legacy_id,
                $selectedLegacyRows
            )));

            $mappedEnrollees = Enrollee::query()
                ->with(['facility.lga', 'lga', 'ward'])
                ->where('legacy_source_table', $sourceTable)
                ->whereIn('legacy_id', $legacyIds)
                ->get()
                ->keyBy('legacy_id');

            $capturedAt = $this->legacySnapshotCapturedAt($detail);
            $rows = [];
            CapitationDetailEnrollee::query()
                ->where('capitation_detail_id', $detail->id)
                ->delete();

            foreach ($selectedLegacyRows as $legacyRow) {
                /** @var Enrollee|null $mappedEnrollee */
                $mappedEnrollee = $mappedEnrollees->get((int) $legacyRow->legacy_id);
                $rows[] = $this->legacySnapshotRow(
                    $detail,
                    $capitation,
                    $mappedEnrollee,
                    $legacyRow,
                    $sourceTable,
                    $duplicateNinPolicy,
                    $capturedAt,
                );
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                CapitationDetailEnrollee::insert($chunk);
            }

            if (count($selectedLegacyRows) < $expectedCount) {
                Log::warning('Legacy capitation enrollee snapshot backfill was short for a detail.', [
                    'capitation_id' => $capitation->id,
                    'capitation_detail_id' => $detail->id,
                    'legacy_group_id' => $legacyGroupId,
                    'legacy_programme_type' => $descriptor['programme_type'],
                    'legacy_provider_id' => $descriptor['legacy_provider_id'],
                    'expected_count' => $expectedCount,
                    'stored_count' => count($selectedLegacyRows),
                ]);
            }
        }
    }

    private function backfillCurrentEnrolleeSnapshots(Capitation $capitation, Collection $details): void
    {
        foreach ($details as $detail) {
            if (!$detail instanceof CapitationDetail) {
                continue;
            }

            $fundingType = $detail->fundingType;
            if (!$fundingType instanceof FundingType) {
                continue;
            }

            $expectedCount = max(0, (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0));
            if ($expectedCount === 0) {
                continue;
            }

            $duplicateNinPolicy = $this->normalizeDuplicateNinPolicy(
                data_get($detail->metadata, 'duplicate_nin_policy') ?? $capitation->duplicate_nin_policy
            );

            $eligibleEnrollees = $this->eligibleEnrolleesForFacility(
                $capitation,
                $fundingType,
                $duplicateNinPolicy,
                (int) $detail->facility_id
            );

            $availableCount = $eligibleEnrollees->count();
            $enrolleesToStore = $availableCount > $expectedCount
                ? $eligibleEnrollees->take($expectedCount)->values()
                : $eligibleEnrollees;

            $capturedAt = $detail->created_at
                ?? $capitation->computed_at
                ?? $capitation->created_at
                ?? now();
            CapitationDetailEnrollee::query()
                ->where('capitation_detail_id', $detail->id)
                ->delete();

            $this->storeEnrolleeSnapshotsForDetail(
                $detail,
                $capitation,
                $fundingType,
                $enrolleesToStore,
                $duplicateNinPolicy,
                $capturedAt,
                'capitation_generation_backfill',
                [
                    'generated_for_funding_type_id' => $fundingType->id,
                    'backfill_method' => 'reconstructed_from_current_enrollees',
                ],
            );

            if ($availableCount !== $expectedCount) {
                Log::warning('Current capitation enrollee snapshot backfill count mismatch.', [
                    'capitation_id' => $capitation->id,
                    'capitation_detail_id' => $detail->id,
                    'facility_id' => $detail->facility_id,
                    'funding_type_id' => $fundingType->id,
                    'expected_count' => $expectedCount,
                    'available_count' => $availableCount,
                    'stored_count' => $enrolleesToStore->count(),
                ]);
            }
        }
    }

    private function takeLegacySnapshotRows(array &$bucket, int $limit): array
    {
        if ($limit <= 0 || $bucket === []) {
            return [];
        }

        $taken = [];
        $seenLegacyIds = [];

        while ($bucket !== [] && count($taken) < $limit) {
            $legacyRow = array_shift($bucket);
            $legacyId = (int) data_get($legacyRow, 'legacy_id');
            if ($legacyId > 0 && isset($seenLegacyIds[$legacyId])) {
                continue;
            }

            if ($legacyId > 0) {
                $seenLegacyIds[$legacyId] = true;
            }

            $taken[] = $legacyRow;
        }

        return $taken;
    }

    private function legacySnapshotRow(
        CapitationDetail $detail,
        Capitation $capitation,
        ?Enrollee $mappedEnrollee,
        object $legacyRow,
        string $sourceTable,
        string $duplicateNinPolicy,
        Carbon $capturedAt,
    ): array {
        $facility = $detail->facility;
        $fundingType = $detail->fundingType;
        $fullName = $mappedEnrollee
            ? trim((string) preg_replace('/\s+/', ' ', $mappedEnrollee->full_name))
            : $this->legacySnapshotFullName($legacyRow);

        if ($fullName === '') {
            $fullName = 'Legacy Enrollee #' . (int) $legacyRow->legacy_id;
        }

        return [
            'capitation_id' => $capitation->id,
            'capitation_detail_id' => $detail->id,
            'enrollee_id' => $mappedEnrollee?->id,
            'facility_id' => $facility?->id ?? $detail->facility_id,
            'funding_type_id' => $fundingType?->id ?? $detail->funding_type_id,
            'enrollee_number' => $mappedEnrollee?->enrollee_id ?: $this->legacyString($legacyRow->enrolment_number),
            'legacy_id' => (string) (int) $legacyRow->legacy_id,
            'full_name' => $fullName,
            'nin' => $mappedEnrollee?->nin
                ?: Enrollee::normalizeNin(
                    $this->legacyString($legacyRow->nin) ?: $this->legacyString($legacyRow->national_identification_number)
                ),
            'phone' => $mappedEnrollee?->phone ?: $this->legacyString($legacyRow->phone_number),
            'gender' => $mappedEnrollee
                ? match ((int) $mappedEnrollee->sex) {
                    1 => 'Male',
                    2 => 'Female',
                    default => 'Other',
                }
                : $this->legacySnapshotGender($legacyRow->sex),
            'date_of_birth' => $mappedEnrollee?->date_of_birth?->toDateString() ?: $this->legacySnapshotDate($legacyRow->date_of_birth),
            'facility_name' => $facility?->name,
            'facility_code' => $facility?->hcp_code,
            'funding_type_name' => $fundingType?->name,
            'lga_name' => $mappedEnrollee?->lga?->name
                ?: $this->legacyLgaName($legacyRow->lga_reference)
                ?: $facility?->lga?->name,
            'ward_name' => $mappedEnrollee?->ward?->name ?: $this->legacyWardName($legacyRow->ward_reference),
            'coverage_start_date' => $mappedEnrollee?->coverage_start_date?->toDateString()
                ?: $this->legacySnapshotDate($legacyRow->cap_date_month),
            'coverage_end_date' => $mappedEnrollee?->coverage_end_date?->toDateString()
                ?: $this->legacySnapshotDate($legacyRow->date_expired),
            'capitation_start_date' => $mappedEnrollee?->capitation_start_date?->toDateString()
                ?: $this->legacySnapshotDate($legacyRow->cap_date_month),
            'snapshot_status' => $mappedEnrollee ? (int) $mappedEnrollee->status : $this->legacySnapshotStatus($legacyRow->status),
            'duplicate_nin_policy' => $duplicateNinPolicy,
            'has_duplicate_nin' => (bool) ($mappedEnrollee?->has_duplicate_nin ?? false),
            'captured_at' => $capturedAt,
            'metadata' => json_encode([
                'snapshot_source' => 'legacy_capitation_enrollee_list_backfill',
                'legacy_source_table' => $sourceTable,
                'legacy_group_id' => (int) data_get($capitation->metadata, 'legacy_id'),
                'legacy_programme_type' => $this->legacyProgrammeTypeForDetail($detail),
                'legacy_provider_id' => $this->legacyProviderIdForDetail($detail),
                'mapped_enrollee_id' => $mappedEnrollee?->id,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $capturedAt,
            'updated_at' => $capturedAt,
        ];
    }

    private function legacySnapshotBucketKey(string $sourceTable, string $programmeType, int $providerId): string
    {
        return strtolower($sourceTable . '|' . $programmeType . '|' . $providerId);
    }

    private function legacyProgrammeTypeForDetail(CapitationDetail $detail): ?string
    {
        $metadataProgrammeType = $this->legacyString(data_get($detail->metadata, 'legacy_programme_type'));
        if ($metadataProgrammeType !== null) {
            return $metadataProgrammeType;
        }

        $fundingName = strtolower(trim((string) ($detail->fundingType?->name ?? '')));
        if ($fundingName === '') {
            return null;
        }

        return match (true) {
            str_contains($fundingName, 'formal') => 'NiCare-Formal',
            str_contains($fundingName, 'counterpart') || str_contains($fundingName, 'cf') => 'BHCPF-CF',
            str_contains($fundingName, 'bhcpf') => 'BHCPF',
            str_contains($fundingName, 'nicare') || str_contains($fundingName, 'premium') => 'NiCare',
            str_contains($fundingName, 'gac') => 'GAC',
            str_contains($fundingName, 'unicef') => 'UNICEF',
            default => null,
        };
    }

    private function legacyProviderIdForDetail(CapitationDetail $detail): ?int
    {
        $legacyProviderId = (int) data_get($detail->metadata, 'legacy_provider_id');
        if ($legacyProviderId > 0) {
            return $legacyProviderId;
        }

        $facilityLegacyId = (int) ($detail->facility?->legacy_id ?? 0);
        if ($facilityLegacyId > 0) {
            return $facilityLegacyId;
        }

        return null;
    }

    private function legacySourceTableForProgramme(string $programmeType): string
    {
        return str_contains(strtolower($programmeType), 'formal')
            ? 'tbl_enrolee_formal'
            : 'tbl_enrolee';
    }

    private function legacySnapshotCapturedAt(CapitationDetail $detail): Carbon
    {
        $generatedAt = $this->legacyString(data_get($detail->metadata, 'legacy_generated_at'));
        if ($generatedAt !== null) {
            try {
                return Carbon::parse($generatedAt);
            } catch (\Throwable) {
                // Fall back to the detail timestamp if the legacy value is malformed.
            }
        }

        return $detail->created_at instanceof Carbon
            ? $detail->created_at->copy()
            : Carbon::parse($detail->created_at ?? now());
    }

    private function legacySnapshotFullName(object $legacyRow): string
    {
        return trim((string) preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $this->legacyString($legacyRow->first_name),
            $this->legacyString($legacyRow->other_name),
            $this->legacyString($legacyRow->surname),
        ]))));
    }

    private function legacySnapshotGender(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'male', 'm', '1' => 'Male',
            'female', 'f', '2' => 'Female',
            default => null,
        };
    }

    private function legacySnapshotDate(mixed $value): ?string
    {
        $dateValue = $this->legacyString($value);
        if ($dateValue === null || in_array($dateValue, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        try {
            return Carbon::parse($dateValue)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacySnapshotStatus(mixed $value): ?int
    {
        $statusValue = $this->legacyString($value);
        if ($statusValue === null || !is_numeric($statusValue)) {
            return null;
        }

        $status = (int) $statusValue;

        return $status >= 0 ? $status : null;
    }

    private function legacyLgaName(mixed $value): ?string
    {
        $reference = $this->legacyString($value);
        if ($reference === null) {
            return null;
        }

        if (!ctype_digit($reference)) {
            return $reference;
        }

        static $cache = [];
        $legacyId = (int) $reference;

        if (!array_key_exists($legacyId, $cache)) {
            $cache[$legacyId] = Lga::query()
                ->when(
                    DB::getSchemaBuilder()->hasColumn('lgas', 'legacy_id'),
                    fn ($query) => $query->where('legacy_id', $legacyId),
                    fn ($query) => $query->whereKey($legacyId)
                )
                ->value('name')
                ?: Lga::query()->whereKey($legacyId)->value('name');
        }

        return $cache[$legacyId];
    }

    private function legacyWardName(mixed $value): ?string
    {
        $reference = $this->legacyString($value);
        if ($reference === null) {
            return null;
        }

        if (!ctype_digit($reference)) {
            return $reference;
        }

        static $cache = [];
        $legacyId = (int) $reference;

        if (!array_key_exists($legacyId, $cache)) {
            $cache[$legacyId] = Ward::query()
                ->when(
                    DB::getSchemaBuilder()->hasColumn('wards', 'legacy_id'),
                    fn ($query) => $query->where('legacy_id', $legacyId),
                    fn ($query) => $query->whereKey($legacyId)
                )
                ->value('name')
                ?: Ward::query()->whereKey($legacyId)->value('name');
        }

        return $cache[$legacyId];
    }

    private function legacyString(mixed $value): ?string
    {
        $stringValue = trim((string) ($value ?? ''));

        return $stringValue === '' ? null : $stringValue;
    }

    private function paymentReportFundingColumn(?FundingType $fundingType): ?string
    {
        if (!$fundingType) {
            return null;
        }

        $columnsByCanonicalId = [
            1 => 'bhcpf',
            2 => 'bhcpf_cf',
            3 => 'nicare',
            4 => 'gac',
            5 => 'unicef',
            6 => 'nicare_formal',
        ];

        $canonicalColumn = $columnsByCanonicalId[(int) $fundingType->id] ?? null;
        if ($canonicalColumn !== null) {
            return $canonicalColumn;
        }

        $value = $this->normalizePaymentReportFundingValue(
            $fundingType->name . ' ' . ($fundingType->description ?? '')
        );

        if (
            str_contains($value, 'counterpartfunding') ||
            str_contains($value, 'bhcpfcf') ||
            str_contains($value, 'bhcpfcounterpart') ||
            str_contains($value, 'shortcodecf')
        ) {
            return 'bhcpf_cf';
        }

        if (
            str_contains($value, 'formalsectordeduction') ||
            str_contains($value, 'formal') ||
            str_contains($value, 'nicareformal')
        ) {
            return 'nicare_formal';
        }

        if (
            str_contains($value, 'basichealthcareprovisionfund') ||
            str_contains($value, 'bhcpf')
        ) {
            return 'bhcpf';
        }

        if (
            str_contains($value, 'premium') ||
            str_contains($value, 'nicare')
        ) {
            return 'nicare';
        }

        if (str_contains($value, 'gac')) {
            return 'gac';
        }

        if (str_contains($value, 'unicef')) {
            return 'unicef';
        }

        return null;
    }

    private function normalizePaymentReportFundingValue(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', $value));
    }

    private function applyPaymentReportStatusFilter($query, string $status): void
    {
        match ($status) {
            'reviewed' => $query->whereNotNull('reviewed_at'),
            'approved' => $query->whereNotNull('approved_at'),
            'paid' => $query->whereNotNull('paid_at'),
            'generated' => $query->whereNull('reviewed_at'),
            default => null,
        };
    }

    private function emptyPaymentReportAmounts(): array
    {
        return [
            'bhcpf' => 0.0,
            'nicare' => 0.0,
            'bhcpf_cf' => 0.0,
            'gac' => 0.0,
            'nicare_formal' => 0.0,
            'unicef' => 0.0,
        ];
    }

    private function normalizeHistoricalPaymentReportFilters(array $filters): array
    {
        $status = (string) ($filters['status'] ?? 'all');
        if (!in_array($status, ['all', 'generated', 'reviewed', 'approved', 'paid'], true)) {
            $status = 'all';
        }

        $rangeMode = (string) ($filters['range_mode'] ?? 'all_time');
        $rangeMode = $rangeMode === 'custom' ? 'custom' : 'all_time';

        $facilityId = filled($filters['facility_id'] ?? null) ? (int) $filters['facility_id'] : null;
        $fundingTypeId = filled($filters['funding_type_id'] ?? null) ? (int) $filters['funding_type_id'] : null;
        $facility = $facilityId ? Facility::query()->select(['id', 'name', 'hcp_code'])->find($facilityId) : null;
        $fundingType = $fundingTypeId ? FundingType::query()->select(['id', 'name'])->find($fundingTypeId) : null;

        $fromPeriod = null;
        $toPeriod = null;

        if ($rangeMode === 'custom') {
            $fromPeriod = Capitation::query()
                ->select(['id', 'name', 'year', 'capitation_month', 'capitated_month', 'period_start'])
                ->find((int) ($filters['from_period_id'] ?? 0));
            $toPeriod = Capitation::query()
                ->select(['id', 'name', 'year', 'capitation_month', 'capitated_month', 'period_start'])
                ->find((int) ($filters['to_period_id'] ?? 0));

            if (!$fromPeriod || !$toPeriod) {
                throw new \InvalidArgumentException('Select a valid capitation period range for the historical report.');
            }

            if ($this->periodSequenceKey($fromPeriod) > $this->periodSequenceKey($toPeriod)) {
                throw new \InvalidArgumentException('The starting capitation period must not be later than the ending period.');
            }
        }

        return [
            'status' => $status,
            'status_label' => $this->paymentReportStatusLabel($status),
            'range_mode' => $rangeMode,
            'facility_id' => $facilityId,
            'facility_name' => $facility?->name,
            'facility_code' => $facility?->hcp_code,
            'funding_type_id' => $fundingTypeId,
            'funding_type_name' => $fundingType?->name,
            'scope' => $facilityId ? 'facility_history' : 'facility_summary',
            'scope_label' => $facilityId ? 'Facility History' : 'Facilities Summary',
            'from_period' => $fromPeriod,
            'to_period' => $toPeriod,
        ];
    }

    private function historicalPaymentReportDetails(array $filters): Collection
    {
        $query = CapitationDetail::query()->with([
            'capitation',
            'facility.lga',
            'facility.ward',
            'fundingType',
        ]);

        if ($filters['facility_id']) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if ($filters['funding_type_id']) {
            $query->where('funding_type_id', $filters['funding_type_id']);
        }

        $this->applyPaymentReportStatusFilter($query, $filters['status']);

        if ($filters['range_mode'] === 'custom') {
            $fromKey = $this->periodSequenceKey($filters['from_period']);
            $toKey = $this->periodSequenceKey($filters['to_period']);

            $query->whereHas('capitation', function (EloquentBuilder $capitationQuery) use ($fromKey, $toKey): void {
                $capitationQuery
                    ->whereRaw('((year * 100) + capitation_month) >= ?', [$fromKey])
                    ->whereRaw('((year * 100) + capitation_month) <= ?', [$toKey]);
            });
        }

        return $query->get();
    }

    private function historicalPaymentReportRows(Collection $details, string $scope): SupportCollection
    {
        if ($details->isEmpty()) {
            return collect();
        }

        $grouped = $scope === 'facility_history'
            ? $details->groupBy(fn (CapitationDetail $detail) => $detail->capitation_id . ':' . $detail->facility_id)
            : $details->groupBy(fn (CapitationDetail $detail) => (string) $detail->facility_id);

        $rows = $grouped
            ->map(fn (SupportCollection $group) => $this->historicalPaymentReportRow($group, $scope))
            ->values();

        if ($scope === 'facility_history') {
            return $rows->sortByDesc('period_sequence')->values();
        }

        return $rows
            ->sortBy(fn (array $row) => strtolower((string) ($row['provider_name'] ?? '')))
            ->values();
    }

    private function historicalPaymentReportRow(SupportCollection $details, string $scope): array
    {
        $firstDetail = $details->first();
        $facility = $firstDetail?->facility;
        $capitations = $details->map(fn (CapitationDetail $detail) => $detail->capitation)
            ->filter(fn ($capitation) => $capitation instanceof Capitation)
            ->unique('id')
            ->sortBy(fn (Capitation $capitation) => $this->periodSequenceKey($capitation))
            ->values();
        $firstCapitation = $capitations->first();
        $lastCapitation = $capitations->last();
        $amounts = $this->emptyPaymentReportAmounts();
        $fundingTypeNames = [];

        foreach ($details as $detail) {
            $column = $this->paymentReportFundingColumn($detail->fundingType);
            if ($column !== null) {
                $amounts[$column] += (float) ($detail->total_amount ?? $detail->amount ?? 0);
            }

            if ($detail->fundingType?->name) {
                $fundingTypeNames[] = $detail->fundingType->name;
            }
        }

        $fundingTypeSummary = collect($fundingTypeNames)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $baseRow = [
            'provider_name' => $facility?->name ?? 'N/A',
            'facility_id' => (int) ($facility?->id ?? $firstDetail?->facility_id ?? 0),
            'facility_code' => $facility?->hcp_code ?? '',
            'lga' => $facility?->lga?->name ?? '',
            'ward' => $facility?->ward?->name ?? '',
            'processing_status' => $this->historicalPaymentReportProcessingStatus($details),
            'processing_status_tone' => $this->historicalPaymentReportProcessingTone(
                $this->historicalPaymentReportProcessingStatus($details)
            ),
            'funding_type_summary' => $fundingTypeSummary->isNotEmpty() ? $fundingTypeSummary->implode(', ') : 'N/A',
            'funding_types' => $fundingTypeSummary->all(),
            'detail_count' => $details->count(),
            'total_enrollees' => $details->sum(
                fn (CapitationDetail $detail) => (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0)
            ),
            ...$amounts,
            'total_amount' => (float) $details->sum(
                fn (CapitationDetail $detail) => (float) ($detail->total_amount ?? $detail->amount ?? 0)
            ),
        ];

        if ($scope === 'facility_history') {
            $capitation = $firstDetail?->capitation;

            return array_merge($baseRow, [
                'id' => 'capitation-' . (int) ($capitation?->id ?? 0) . '-facility-' . (int) ($facility?->id ?? 0),
                'scope' => 'facility_history',
                'capitation_id' => (int) ($capitation?->id ?? 0),
                'capitation_period' => $capitation?->name ?? 'N/A',
                'cutoff_date' => $capitation ? $this->capitationCutoffDate($capitation) : null,
                'year' => (int) ($capitation?->year ?? 0),
                'capitation_month' => (int) ($capitation?->capitation_month ?: $capitation?->capitated_month ?: 0),
                'period_sequence' => $capitation ? $this->periodSequenceKey($capitation) : 0,
                'period_count' => 1,
                'first_capitation_period' => $capitation?->name ?? null,
                'last_capitation_period' => $capitation?->name ?? null,
            ]);
        }

        return array_merge($baseRow, [
            'id' => 'facility-' . (int) ($facility?->id ?? $firstDetail?->facility_id ?? 0),
            'scope' => 'facility_summary',
            'capitation_id' => null,
            'capitation_period' => null,
            'cutoff_date' => null,
            'year' => (int) ($lastCapitation?->year ?? 0),
            'capitation_month' => (int) ($lastCapitation?->capitation_month ?: $lastCapitation?->capitated_month ?: 0),
            'period_sequence' => $lastCapitation ? $this->periodSequenceKey($lastCapitation) : 0,
            'period_count' => $capitations->count(),
            'first_capitation_period' => $firstCapitation?->name ?? null,
            'last_capitation_period' => $lastCapitation?->name ?? null,
        ]);
    }

    private function historicalPaymentReportSummary(
        Collection $details,
        SupportCollection $rows,
        array $filters
    ): array {
        $periods = $details->map(fn (CapitationDetail $detail) => $detail->capitation)
            ->filter(fn ($capitation) => $capitation instanceof Capitation)
            ->unique('id')
            ->sortBy(fn (Capitation $capitation) => $this->periodSequenceKey($capitation))
            ->values();

        return [
            'scope' => $filters['scope'],
            'scope_label' => $filters['scope_label'],
            'status' => $filters['status'],
            'status_label' => $filters['status_label'],
            'range_mode' => $filters['range_mode'],
            'range_label' => $this->historicalPaymentReportRangeLabel($filters, $periods),
            'facility_id' => $filters['facility_id'],
            'facility_name' => $filters['facility_name'],
            'facility_code' => $filters['facility_code'],
            'funding_type_id' => $filters['funding_type_id'],
            'funding_type_name' => $filters['funding_type_name'],
            'row_count' => $rows->count(),
            'facility_count' => $details->pluck('facility_id')->filter()->unique()->count(),
            'period_count' => $periods->count(),
            'total_enrollees' => (int) $rows->sum(fn (array $row) => (int) ($row['total_enrollees'] ?? 0)),
            'total_amount' => (float) $rows->sum(fn (array $row) => (float) ($row['total_amount'] ?? 0)),
            'from_period' => $filters['from_period']
                ? [
                    'id' => (int) $filters['from_period']->id,
                    'label' => $this->historicalPaymentReportPeriodLabel($filters['from_period']),
                ]
                : null,
            'to_period' => $filters['to_period']
                ? [
                    'id' => (int) $filters['to_period']->id,
                    'label' => $this->historicalPaymentReportPeriodLabel($filters['to_period']),
                ]
                : null,
        ];
    }

    private function historicalPaymentReportRangeLabel(array $filters, SupportCollection $periods): string
    {
        if ($filters['range_mode'] === 'custom') {
            return $this->historicalPaymentReportPeriodLabel($filters['from_period'])
                . ' to '
                . $this->historicalPaymentReportPeriodLabel($filters['to_period']);
        }

        if ($periods->isEmpty()) {
            return 'All time';
        }

        $first = $periods->first();
        $last = $periods->last();

        if (!$first || !$last) {
            return 'All time';
        }

        $firstLabel = $this->historicalPaymentReportPeriodLabel($first);
        $lastLabel = $this->historicalPaymentReportPeriodLabel($last);

        return $firstLabel === $lastLabel ? $firstLabel : $firstLabel . ' to ' . $lastLabel;
    }

    private function historicalPaymentReportPeriodLabel(?Capitation $capitation): string
    {
        if (!$capitation) {
            return 'Unknown period';
        }

        if (filled($capitation->name)) {
            return (string) $capitation->name;
        }

        $month = (int) ($capitation->capitation_month ?: $capitation->capitated_month ?: 0);
        $year = (int) ($capitation->year ?? 0);

        if ($year > 0 && $month >= 1 && $month <= 12) {
            return Carbon::create($year, $month, 1)->format('F Y');
        }

        return 'Capitation #' . (int) $capitation->id;
    }

    private function paymentReportStatusLabel(string $status): string
    {
        return match ($status) {
            'generated' => 'Generated',
            'reviewed' => 'Reviewed',
            'approved' => 'Approved',
            'paid' => 'Paid',
            default => 'All statuses',
        };
    }

    private function historicalPaymentReportProcessingStatus(SupportCollection $details): string
    {
        if ($details->every(fn (CapitationDetail $detail) => filled($detail->paid_at))) {
            return 'Paid';
        }

        if ($details->every(fn (CapitationDetail $detail) => filled($detail->approved_at))) {
            return 'Approved';
        }

        if ($details->every(fn (CapitationDetail $detail) => filled($detail->reviewed_at))) {
            return 'Reviewed';
        }

        if ($details->every(fn (CapitationDetail $detail) => blank($detail->reviewed_at))) {
            return 'Generated';
        }

        return 'Mixed';
    }

    private function historicalPaymentReportProcessingTone(string $status): string
    {
        return match ($status) {
            'Paid' => 'success',
            'Approved' => 'info',
            'Reviewed' => 'warning',
            'Generated' => 'neutral',
            default => 'warning',
        };
    }

    private function periodSequenceKey(?Capitation $capitation): int
    {
        if (!$capitation) {
            return 0;
        }

        $month = (int) ($capitation->capitation_month ?: $capitation->capitated_month ?: 0);

        return ((int) ($capitation->year ?? 0) * 100) + $month;
    }

    public function getFacilityHistory(int $facilityId): Collection
    {
        return CapitationDetail::where('facility_id', $facilityId)
            ->with('capitation')
            ->orderByDesc('created_at')
            ->get();
    }

    private function transitionDetails(Capitation $capitation, array $detailIds, string $stage): int
    {
        $query = $capitation->capitationDetails()->whereIn('id', $detailIds);

        $updates = match ($stage) {
            'review' => [
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now()->toDateString(),
                'status' => 2,
            ],
            'approval' => [
                'approved_by' => auth()->id(),
                'approved_at' => now()->toDateString(),
                'status' => 3,
            ],
            default => [],
        };

        if ($updates === []) {
            throw new \InvalidArgumentException('Unsupported capitation detail action.');
        }

        if ($stage === 'review') {
            $query->whereNull('reviewed_at');
        }

        if ($stage === 'approval') {
            $query->whereNotNull('reviewed_at')->whereNull('approved_at');
        }

        $details = $query->get();
        if ($details->isEmpty()) {
            throw new \InvalidArgumentException('No eligible capitation details were selected.');
        }

        CapitationDetail::whereIn('id', $details->pluck('id'))->update($updates);

        AuditTrail::create([
            'auditable_type' => Capitation::class,
            'auditable_id' => $capitation->id,
            'action' => "capitation_details_{$stage}",
            'description' => "Selected capitation details moved to {$stage} for {$capitation->name}.",
            'user_id' => auth()->id(),
            'new_values' => ['details' => $details->pluck('id')->values()],
        ]);

        return $details->count();
    }

    private function eligibleProviderRows(Capitation $capitation, FundingType $fundingType, string $duplicateNinPolicy)
    {
        return $this->eligibleEnrolleeQuery($capitation, $fundingType, $duplicateNinPolicy)
            ->join('facilities', 'facilities.id', '=', 'enrollees.facility_id')
            ->leftJoin('lgas', 'lgas.id', '=', 'facilities.lga_id')
            ->select(
                'facilities.id as facility_id',
                'facilities.name as facility_name',
                'facilities.hcp_code',
                'lgas.name as lga_name',
                DB::raw('COUNT(DISTINCT enrollees.id) as enrollee_count')
            )
            ->groupBy('facilities.id', 'facilities.name', 'facilities.hcp_code', 'lgas.name')
            ->orderBy('facilities.name')
            ->get();
    }

    private function eligibleEnrolleeQuery(Capitation $capitation, FundingType $fundingType, string $duplicateNinPolicy): EloquentBuilder
    {
        $cutoffDate = $this->capitationCutoffDate($capitation);
        $duplicateNinPolicy = $this->normalizeDuplicateNinPolicy($duplicateNinPolicy);

        $query = Enrollee::query()
            ->where('enrollees.funding_type_id', $fundingType->id)
            ->where('enrollees.status', Enrollee::STATUS_ACTIVE)
            ->whereNotNull('enrollees.facility_id')
            ->whereNotNull('enrollees.coverage_start_date');

        if ($cutoffDate) {
            $query->whereDate('enrollees.coverage_start_date', '<=', $cutoffDate)
                ->where(function ($query) use ($cutoffDate): void {
                $query->whereNull('enrollees.coverage_end_date')
                    ->orWhereDate('enrollees.coverage_end_date', '>=', $cutoffDate);
                });
        }

        return $query
            ->when(
                $duplicateNinPolicy === Capitation::DUPLICATE_NIN_POLICY_EXCLUDE,
                fn (EloquentBuilder $builder) => $this->duplicateNinService->applyUniqueNinOnly($builder, 'enrollees')
            );
    }

    private function capitationCutoffDate(Capitation $capitation): ?string
    {
        if ($capitation->period_start) {
            return Carbon::parse($capitation->period_start)->toDateString();
        }

        $year = (int) ($capitation->year ?? 0);
        $month = (int) ($capitation->capitation_month ?: $capitation->capitated_month ?: 0);

        if ($year > 0 && $month >= 1 && $month <= 12) {
            return Carbon::create($year, $month, 20)->toDateString();
        }

        return $capitation->created_at?->toDateString();
    }

    private function eligibleEnrolleesForFacility(
        Capitation $capitation,
        FundingType $fundingType,
        string $duplicateNinPolicy,
        int $facilityId,
    ): Collection {
        return $this->eligibleEnrolleeQuery($capitation, $fundingType, $duplicateNinPolicy)
            ->with(['facility.lga', 'fundingType', 'lga', 'ward'])
            ->where('enrollees.facility_id', $facilityId)
            ->orderBy('enrollees.last_name')
            ->orderBy('enrollees.first_name')
            ->get();
    }

    private function storeEnrolleeSnapshotsForDetail(
        CapitationDetail $detail,
        Capitation $capitation,
        FundingType $fundingType,
        Collection $enrollees,
        string $duplicateNinPolicy,
        $capturedAt,
        string $snapshotSource = 'capitation_generation',
        array $snapshotMetadata = [],
    ): void {
        if ($enrollees->isEmpty()) {
            return;
        }

        $rows = $enrollees->map(function (Enrollee $enrollee) use ($detail, $capitation, $fundingType, $duplicateNinPolicy, $capturedAt, $snapshotSource, $snapshotMetadata): array {
            $facility = $enrollee->facility;

            return [
                'capitation_id' => $capitation->id,
                'capitation_detail_id' => $detail->id,
                'enrollee_id' => $enrollee->id,
                'facility_id' => $facility?->id ?? $detail->facility_id,
                'funding_type_id' => $fundingType->id,
                'enrollee_number' => $enrollee->enrollee_id,
                'legacy_id' => $enrollee->legacy_id,
                'full_name' => trim((string) preg_replace('/\s+/', ' ', $enrollee->full_name)),
                'nin' => $enrollee->nin,
                'phone' => $enrollee->phone,
                'gender' => match ((int) $enrollee->sex) {
                    1 => 'Male',
                    2 => 'Female',
                    default => 'Other',
                },
                'date_of_birth' => $enrollee->date_of_birth?->toDateString(),
                'facility_name' => $facility?->name,
                'facility_code' => $facility?->hcp_code,
                'funding_type_name' => $fundingType->name,
                'lga_name' => $enrollee->lga?->name ?? $facility?->lga?->name,
                'ward_name' => $enrollee->ward?->name,
                'coverage_start_date' => $enrollee->coverage_start_date?->toDateString(),
                'coverage_end_date' => $enrollee->coverage_end_date?->toDateString(),
                'capitation_start_date' => $enrollee->capitation_start_date?->toDateString(),
                'snapshot_status' => (int) $enrollee->status,
                'duplicate_nin_policy' => $duplicateNinPolicy,
                'has_duplicate_nin' => (bool) $enrollee->has_duplicate_nin,
                'captured_at' => $capturedAt,
                'metadata' => json_encode(array_merge([
                    'snapshot_source' => $snapshotSource,
                    'generated_for_funding_type_id' => $fundingType->id,
                ], $snapshotMetadata), JSON_THROW_ON_ERROR),
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];
        })->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            CapitationDetailEnrollee::insert($chunk);
        }
    }

    private function enrolleeSnapshotQuery(Capitation $capitation, array $filters = []): EloquentBuilder
    {
        $query = CapitationDetailEnrollee::query()
            ->where('capitation_id', $capitation->id);

        if (!empty($filters['funding_type_id'])) {
            $query->where('funding_type_id', (int) $filters['funding_type_id']);
        }

        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', (int) $filters['facility_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (EloquentBuilder $builder) use ($search): void {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('enrollee_number', 'like', "%{$search}%")
                    ->orWhere('legacy_id', 'like', "%{$search}%")
                    ->orWhere('nin', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('facility_name', 'like', "%{$search}%")
                    ->orWhere('funding_type_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function normalizeDuplicateNinPolicy(?string $duplicateNinPolicy): string
    {
        return $duplicateNinPolicy === Capitation::DUPLICATE_NIN_POLICY_INCLUDE
            ? Capitation::DUPLICATE_NIN_POLICY_INCLUDE
            : Capitation::DUPLICATE_NIN_POLICY_EXCLUDE;
    }

    private function capitationPeriodQuery()
    {
        return Capitation::with(['user:id,name', 'fundingType:id,name'])
            ->withCount([
                'capitationDetails',
                'capitationDetails as pending_review_count' => fn ($query) => $query->whereNull('reviewed_at'),
                'capitationDetails as reviewed_count' => fn ($query) => $query->whereNotNull('reviewed_at'),
                'capitationDetails as pending_approval_count' => fn ($query) => $query->whereNotNull('reviewed_at')->whereNull('approved_at'),
                'capitationDetails as approved_count' => fn ($query) => $query->whereNotNull('approved_at'),
                'capitationDetails as pending_payment_count' => fn ($query) => $query->whereNotNull('approved_at')->whereNull('paid_at'),
                'capitationDetails as paid_count' => fn ($query) => $query->whereNotNull('paid_at'),
            ]);
    }

    private function decorateCapitationPeriods(iterable $periods): void
    {
        $capitationPeriods = collect($periods)->filter(fn ($period) => $period instanceof Capitation)->values();

        if ($capitationPeriods->isEmpty()) {
            return;
        }

        $fundingTypesByPeriod = DB::table('capitation_details')
            ->join('funding_types', 'funding_types.id', '=', 'capitation_details.funding_type_id')
            ->select(
                'capitation_details.capitation_id',
                'funding_types.id as funding_type_id',
                'funding_types.name as funding_type_name'
            )
            ->whereIn('capitation_details.capitation_id', $capitationPeriods->pluck('id')->all())
            ->whereNotNull('capitation_details.funding_type_id')
            ->distinct()
            ->orderBy('funding_types.name')
            ->get()
            ->groupBy('capitation_id');

        foreach ($capitationPeriods as $period) {
            if ($period instanceof Capitation) {
                $this->decorateCapitationPeriod($period, collect($fundingTypesByPeriod->get($period->id, [])));
            }
        }
    }

    private function decorateCapitationPeriod(Capitation $period, $detailFundingTypes = null): void
    {
        $detailFundingTypes = collect($detailFundingTypes)
            ->map(fn ($type) => [
                'id' => (int) data_get($type, 'funding_type_id'),
                'name' => data_get($type, 'funding_type_name'),
            ])
            ->filter(fn (array $type) => $type['id'] > 0 && filled($type['name']))
            ->unique('id')
            ->values();

        $period->setAttribute(
            'funding_types',
            $detailFundingTypes->all()
        );

        if ($period->fundingType) {
            $period->setAttribute('funding_type_summary', $period->fundingType->name);
            return;
        }

        if ($detailFundingTypes->count() === 1) {
            $derivedType = $detailFundingTypes->first();
            $period->setAttribute('funding_type_id', $derivedType['id']);
            $period->setAttribute('funding_type_summary', $derivedType['name']);
            return;
        }

        if ($detailFundingTypes->count() > 1) {
            $period->setAttribute(
                'funding_type_summary',
                'Multiple: ' . $detailFundingTypes->pluck('name')->implode(', ')
            );
            return;
        }

        $period->setAttribute('funding_type_summary', null);
    }
}
