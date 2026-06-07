<?php

namespace App\Services;

use App\Models\Enrollee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class EligibilityService
{
    public function getActiveCoverage(Enrollee|int $enrollee): ?Enrollee
    {
        return $this->findActiveCoverageForDate($enrollee, now());
    }

    public function assertEligibleForCare(Enrollee|int $enrollee): Enrollee
    {
        $coverage = $this->getActiveCoverage($enrollee);

        if (!$coverage) {
            $enrolleeRecord = $enrollee instanceof Enrollee ? $enrollee->fresh() : Enrollee::find($enrollee);
            if ($enrolleeRecord?->coverage_start_date && $enrolleeRecord->coverage_start_date->isFuture()) {
                throw new InvalidArgumentException(
                    'BR-11 violation: Enrollee is still in the waiting period until ' . $enrolleeRecord->coverage_start_date->toDateString() . '.'
                );
            }

            throw new InvalidArgumentException('BR-11 violation: Enrollee is not eligible for care without active coverage.');
        }

        $this->assertNotInWaitingPeriod($enrollee);
        $this->assertCoverageHasBenefitPackage($coverage);

        return $coverage;
    }

    public function assertNotInWaitingPeriod(Enrollee|int $enrollee): void
    {
        $enrolleeRecord = $enrollee instanceof Enrollee ? $enrollee : Enrollee::findOrFail($enrollee);

        if ($enrolleeRecord->coverage_start_date && $enrolleeRecord->coverage_start_date->isFuture()) {
            throw new InvalidArgumentException(
                'BR-11 violation: Enrollee is still in the waiting period until ' . $enrolleeRecord->coverage_start_date->toDateString() . '.'
            );
        }
    }

    public function assertCoverageCoversDate(Enrollee|int $enrollee, CarbonInterface|string $date): Enrollee
    {
        $date = is_string($date) ? Carbon::parse($date) : $date;
        $coverage = $this->findActiveCoverageForDate($enrollee, $date);

        if (!$coverage) {
            throw new InvalidArgumentException('Coverage does not cover the requested date.');
        }

        $this->assertCoverageHasBenefitPackage($coverage);

        return $coverage;
    }

    public function assertFacilityMatchesCoverage(Enrollee|int $enrollee, int $facilityId, CarbonInterface|string|null $date = null): Enrollee
    {
        $coverage = $date
            ? $this->assertCoverageCoversDate($enrollee, $date)
            : $this->assertEligibleForCare($enrollee);

        if ($coverage->facility_id && (int) $coverage->facility_id !== (int) $facilityId) {
            throw new InvalidArgumentException('Coverage is assigned to a different HCP/facility.');
        }

        return $coverage;
    }

    public function assertCoverageHasBenefitPackage(Enrollee $coverage): void
    {
        if (empty($coverage->benefit_package_id)) {
            throw new InvalidArgumentException('Enrollee does not have an assigned benefit package.');
        }

        if ($coverage->relationLoaded('benefitPackage') && $coverage->benefitPackage && !(bool) $coverage->benefitPackage->status) {
            throw new InvalidArgumentException('Enrollee benefit package is inactive.');
        }
    }

    private function findActiveCoverageForDate(Enrollee|int $enrollee, CarbonInterface|string $date): ?Enrollee
    {
        $enrolleeId = $enrollee instanceof Enrollee ? $enrollee->id : $enrollee;
        $date = is_string($date) ? Carbon::parse($date) : $date;

        return Enrollee::with(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage', 'facility', 'fundingType', 'benefactor'])
            ->where('id', $enrolleeId)
            ->where('status', 1)
            ->whereDate('coverage_start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('coverage_end_date')
                    ->orWhereDate('coverage_end_date', '>=', $date);
            })
            ->first();
    }

    public function activateDueWaitingPeriods(): int
    {
        return 0;
    }
}
