<?php

namespace App\Services;

use App\Exceptions\CapitationComputationException;
use App\Models\AuditTrail;
use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\CapitationPayment;
use App\Models\Enrollee;
use App\Models\FundingType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CapitationService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Capitation::with(['user', 'fundingType'])
            ->withCount('capitationDetails');

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

        return $query->paginate((int) ($filters['per_page'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function getAllWithoutPagination(): Collection
    {
        return Capitation::with(['user', 'fundingType'])
            ->withCount('capitationDetails')
            ->where('status', 1)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();
    }

    public function findById(int $id): ?Capitation
    {
        return Capitation::with(['user', 'fundingType'])
            ->withCount('capitationDetails')
            ->find($id);
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
        return Capitation::with(['user', 'fundingType'])
            ->where('year', $year)
            ->orderBy('capitation_month')
            ->get();
    }

    public function getByUser(int $userId): Collection
    {
        return Capitation::with(['user', 'fundingType'])
            ->where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();
    }

    public function getByMonthYear(int $month, int $year): Collection
    {
        return Capitation::with(['user', 'fundingType'])
            ->where('capitation_month', $month)
            ->where('year', $year)
            ->get();
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

        $periodStart = \Carbon\Carbon::create((int) $data['year'], (int) $data['capitation_month'], 1)
            ->setDay(min((int) $data['start_day'], \Carbon\Carbon::create((int) $data['year'], (int) $data['capitation_month'], 1)->daysInMonth));
        $fundingType = !empty($data['funding_type_id'])
            ? FundingType::findOrFail((int) $data['funding_type_id'])
            : null;

        return Capitation::create([
            'name' => $data['name'] ?? $periodStart->format('F Y') . ' Capitation',
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
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

    public function eligibleProvidersForPeriod(Capitation $capitation, ?int $fundingTypeId = null): array
    {
        $fundingType = $fundingTypeId
            ? FundingType::find($fundingTypeId)
            : $capitation->fundingType;

        if (!$fundingType) {
            return [];
        }

        return $this->eligibleProviderRows($capitation, $fundingType)
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
    public function computeForPeriod(Capitation $capitation, ?int $fundingTypeId = null, array $facilityIds = []): array
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

        $eligibleProviders = $this->eligibleProviderRows($capitation, $fundingType)
            ->whereIn('facility_id', $facilityIds)
            ->values();

        if ($eligibleProviders->isEmpty()) {
            throw new CapitationComputationException('No eligible providers found for the selected funding type and period.');
        }

        $results = [];

        DB::transaction(function () use ($capitation, $eligibleProviders, $fundingType, $capitationRate, &$results): void {
            foreach ($eligibleProviders as $provider) {
                $count = (int) $provider->enrollee_count;
                $totalAmount = $count * $capitationRate;

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

                CapitationDetail::create([
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
                ]);

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
            ->with(['facility.lga', 'fundingType'])
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

    public function getBreakdown(Capitation $capitation, string $stage = 'generated'): Collection
    {
        $query = $capitation->capitationDetails()->with(['facility', 'fundingType']);

        match ($stage) {
            'reviewed' => $query->whereNotNull('reviewed_at'),
            'approved' => $query->whereNotNull('approved_at'),
            'paid' => $query->whereNotNull('paid_at'),
            default => null,
        };

        return $query->orderBy('facility_id')->get();
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

    private function eligibleProviderRows(Capitation $capitation, FundingType $fundingType)
    {
        $cutoffDate = $capitation->period_start;

        return Enrollee::query()
            ->join('facilities', 'facilities.id', '=', 'enrollees.facility_id')
            ->leftJoin('lgas', 'lgas.id', '=', 'facilities.lga_id')
            ->select(
                'facilities.id as facility_id',
                'facilities.name as facility_name',
                'facilities.hcp_code',
                'lgas.name as lga_name',
                DB::raw('COUNT(DISTINCT enrollees.id) as enrollee_count')
            )
            ->where('enrollees.funding_type_id', $fundingType->id)
            ->where('enrollees.status', Enrollee::STATUS_ACTIVE)
            ->whereNotNull('enrollees.facility_id')
            ->whereNotNull('enrollees.coverage_start_date')
            ->whereDate('enrollees.coverage_start_date', '<=', $cutoffDate)
            ->where(function ($query) use ($cutoffDate): void {
                $query->whereNull('enrollees.coverage_end_date')
                    ->orWhereDate('enrollees.coverage_end_date', '>=', $cutoffDate);
            })
            ->groupBy('facilities.id', 'facilities.name', 'facilities.hcp_code', 'lgas.name')
            ->orderBy('facilities.name')
            ->get();
    }
}
