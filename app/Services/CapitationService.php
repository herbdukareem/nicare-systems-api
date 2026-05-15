<?php

namespace App\Services;

use App\Exceptions\CapitationComputationException;
use App\Models\AuditTrail;
use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\CapitationPayment;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\FundingType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CapitationService
{
    /**
     * Get all capitations with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Capitation::with('user');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply year filter
        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        // Apply month filter
        if (!empty($filters['month'])) {
            $query->where('capitation_month', $filters['month']);
        }

        // Apply user filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get all capitations without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return Capitation::with('user')
            ->where('status', 1)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();
    }

    /**
     * Find capitation by ID
     */
    public function findById(int $id): ?Capitation
    {
        return Capitation::with('user')->find($id);
    }

    /**
     * Create new capitation
     */
    public function create(array $data): Capitation
    {
        return Capitation::create($data);
    }

    /**
     * Update capitation
     */
    public function update(int $id, array $data): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        return $capitation->update($data);
    }

    /**
     * Delete capitation
     */
    public function delete(int $id): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        return $capitation->delete();
    }

    /**
     * Get capitations by year
     */
    public function getByYear(int $year): Collection
    {
        return Capitation::with('user')
            ->where('year', $year)
            ->orderBy('capitation_month')
            ->get();
    }

    /**
     * Get capitations by user
     */
    public function getByUser(int $userId): Collection
    {
        return Capitation::with('user')
            ->where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();
    }

    /**
     * Get capitation for specific month and year
     */
    public function getByMonthYear(int $month, int $year): Collection
    {
        return Capitation::with('user')
            ->where('capitation_month', $month)
            ->where('year', $year)
            ->get();
    }

    /**
     * Toggle capitation status
     */
    public function toggleStatus(int $id): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        $capitation->status = $capitation->status == 1 ? 0 : 1;
        return $capitation->save();
    }

    /**
     * Get capitation statistics
     */
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

    /**
     * Get available years
     */
    public function getAvailableYears(): Collection
    {
        return Capitation::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
    }

    /**
     * Check if capitation exists for month/year
     */
    public function existsForMonthYear(int $month, int $year, int $userId): bool
    {
        return Capitation::where('capitation_month', $month)
            ->where('year', $year)
            ->where('user_id', $userId)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Phase-2 additions
    // -------------------------------------------------------------------------

    /**
     * Create a new capitation period batch.
     */
    public function createPeriod(array $data): Capitation
    {
        $periodStart = \Carbon\Carbon::create((int) $data['year'], (int) $data['capitation_month'], 1)
            ->setDay(min((int) $data['start_day'], \Carbon\Carbon::create((int) $data['year'], (int) $data['capitation_month'], 1)->daysInMonth));

        return Capitation::create([
            'name'             => $data['name'] ?? $periodStart->format('F Y') . ' Capitation',
            'period_start'     => $periodStart->toDateString(),
            'period_end'       => $periodStart->copy()->endOfMonth()->toDateString(),
            'capitated_month'  => (int) $data['capitation_month'],
            'capitation_rate'  => 0,
            'status'           => false,
            'created_by'       => auth()->id(),
            'user_id'          => auth()->id(),
            'capitation_month' => (int) $data['capitation_month'],
            'year'             => (int) $data['year'],
        ]);
    }

    /**
     * Compute capitation for all facilities for a given period.
     * BR-07: Only active, non-suspended, non-terminated enrollees enrolled before period_start.
     *
     * @throws CapitationComputationException
     */
    public function computeForPeriod(Capitation $capitation): array
    {
        $cutoffDate = $capitation->period_start;

        $facilities = Facility::all();
        $results    = [];

        DB::transaction(function () use ($capitation, $facilities, $cutoffDate, &$results) {
            foreach ($facilities as $facility) {
                $facilityId = $facility->id;

                $eligibleGroups = Enrollee::query()
                    ->leftJoin('premium_plans', 'premium_plans.id', '=', 'enrollees.premium_plan_id')
                    ->select(
                        'enrollees.funding_type_id',
                        'enrollees.benefactor_id',
                        DB::raw('COUNT(DISTINCT enrollees.id) as enrollee_count'),
                        DB::raw('COALESCE(SUM(premium_plans.capitation_rate), 0) as total_amount'),
                        DB::raw('COALESCE(AVG(premium_plans.capitation_rate), 0) as capitation_rate')
                    )
                    ->where('enrollees.facility_id', $facilityId)
                    ->where('enrollees.status', 1)
                    ->whereNotNull('enrollees.benefit_package_id')
                    ->whereDate('enrollees.coverage_start_date', '<=', $cutoffDate)
                    ->where(function ($query) use ($cutoffDate) {
                        $query->whereNull('enrollees.coverage_end_date')
                            ->orWhereDate('enrollees.coverage_end_date', '>=', $cutoffDate);
                    })
                    ->groupBy('enrollees.funding_type_id', 'enrollees.benefactor_id')
                    ->get();

                $count = (int) $eligibleGroups->sum('enrollee_count');

                if ($count === 0) {
                    // Log skip but do NOT throw — we continue to next facility.
                    Log::info("Capitation skip: no eligible enrollees for facility {$facilityId} in period.");
                    $results[] = [
                        'facility_id'     => $facilityId,
                        'facility_name'   => $facility->name,
                        'enrollee_count'  => 0,
                        'total_amount'    => 0,
                        'skipped'         => true,
                    ];
                    continue;
                }

                $totalAmount = (float) $eligibleGroups->sum('total_amount');
                foreach ($eligibleGroups as $group) {
                    $groupCount = (int) $group->enrollee_count;
                    $groupRate = (float) $group->capitation_rate;
                    $fundingTypeId = $group->funding_type_id ?? FundingType::query()->value('id');

                    CapitationDetail::updateOrCreate(
                        [
                            'capitation_id' => $capitation->id,
                            'facility_id' => $facilityId,
                            'funding_type_id' => $fundingTypeId,
                            'benefactor_id' => $group->benefactor_id,
                        ],
                        [
                            'capitated_month'  => $capitation->capitation_month,
                            'total_enrollees'  => $groupCount,
                            'total_enrolled'   => $groupCount,
                            'capitation_rate'  => $groupRate,
                            'rate'             => $groupRate,
                            'total_amount'     => (float) $group->total_amount,
                            'amount'           => (float) $group->total_amount,
                        ]
                    );
                }

                // Log each enrollee_id to capitation_details (one row per enrollee is optional;
                // here we store aggregates by facility, funding type, and benefactor.
                Log::info("Capitation computed for facility {$facilityId}: {$count} enrollees = {$totalAmount}");

                $results[] = [
                    'facility_id'    => $facilityId,
                    'facility_name'  => $facility->name,
                    'enrollee_count' => $count,
                    'total_amount'   => $totalAmount,
                    'skipped'        => false,
                ];
            }

            // If every facility was skipped, throw BR-07 exception
            $computed = collect($results)->where('skipped', false)->count();
            if ($computed === 0) {
                throw new CapitationComputationException(
                    'No eligible enrollees found for any facility in this period.'
                );
            }

            // Mark capitation as computed
            $capitation->update(['computed_at' => now(), 'computed_by' => auth()->id()]);

            // BR-09 Audit trail
            AuditTrail::create([
                'auditable_type' => Capitation::class,
                'auditable_id'   => $capitation->id,
                'action'         => 'capitation_computed',
                'description'    => "Capitation period {$capitation->name} computed. Facilities processed: " . count($results),
                'user_id'        => auth()->id(),
                'new_values'     => ['facilities' => count($results), 'computed_at' => now()],
            ]);
        });

        return $results;
    }

    /**
     * Finalise a capitation period.
     * BR-06: finaliser must differ from creator.
     *
     * @throws \InvalidArgumentException
     */
    public function finalise(Capitation $capitation): Capitation
    {
        // BR-06: four-eyes principle
        if (auth()->id() === (int) $capitation->created_by) {
            throw new \InvalidArgumentException(
                'BR-06 violation: The officer who created this capitation period cannot also finalise it.'
            );
        }

        $capitation->update([
            'status'       => true,
            'finalised_at' => now(),
            'finalised_by' => auth()->id(),
        ]);

        // BR-09 Audit trail
        AuditTrail::create([
            'auditable_type' => Capitation::class,
            'auditable_id'   => $capitation->id,
            'action'         => 'capitation_finalised',
            'description'    => "Capitation period {$capitation->name} finalised.",
            'user_id'        => auth()->id(),
            'new_values'     => ['finalised_at' => now(), 'finalised_by' => auth()->id()],
        ]);

        return $capitation;
    }

    /**
     * Mark a finalised capitation period as paid and attach payment batches to detail rows.
     */
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
            throw new \InvalidArgumentException('Cannot pay a capitation period without computed facility breakdown.');
        }

        if ($details->every(fn ($detail) => $detail->paid_at || $detail->capitation_payment_id)) {
            throw new \InvalidArgumentException('This capitation period has already been paid.');
        }

        DB::transaction(function () use ($capitation, $details, $data) {
            foreach ($details->whereNull('paid_at')->groupBy('funding_type_id') as $fundingTypeId => $groupedDetails) {
                $payment = CapitationPayment::create([
                    'capitation_id' => $capitation->id,
                    'funding_type_id' => $fundingTypeId ?: FundingType::query()->value('id'),
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

    /**
     * Get the breakdown (details) for a capitation period.
     */
    public function getBreakdown(Capitation $capitation): Collection
    {
        return $capitation->capitationDetails()->with('facility')->get();
    }

    /**
     * Get capitation history for a specific facility.
     */
    public function getFacilityHistory(int $facilityId): Collection
    {
        return CapitationDetail::where('facility_id', $facilityId)
            ->with('capitation')
            ->orderByDesc('created_at')
            ->get();
    }
}
