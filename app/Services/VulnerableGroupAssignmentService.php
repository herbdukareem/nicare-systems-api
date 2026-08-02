<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\EnrolleeCategory;
use App\Models\InsuranceProgramme;
use App\Models\PremiumPlan;
use App\Models\VulnerableGroup;
use Illuminate\Support\Carbon;

class VulnerableGroupAssignmentService
{
    private const CODE_CHILDREN_UNDER_5 = 'children_under_5yrs';
    private const CODE_ELDERLY = 'elderly_85_and_above';
    private const CODE_FEMALE_REPRODUCTIVE = 'female_reproductive_15_45_years';
    private const CODE_OTHERS = 'others';

    /**
     * @var array<string, int|null>
     */
    private array $groupIds = [];

    public function resolveForEnrollee(Enrollee $enrollee): ?int
    {
        $enrollee->loadMissing(['insuranceProgramme:id,name,code', 'enrolleeCategory:id,insurance_programme_id,name,code', 'premiumPlan:id,insurance_programme_id,name,code']);

        return $this->resolve(
            [
                'insurance_programme_id' => $enrollee->insurance_programme_id,
                'enrollee_category_id' => $enrollee->enrollee_category_id,
                'premium_plan_id' => $enrollee->premium_plan_id,
                'date_of_birth' => $enrollee->date_of_birth,
                'sex' => $enrollee->sex,
            ],
            $enrollee->insuranceProgramme,
            $enrollee->enrolleeCategory,
            $enrollee->premiumPlan
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolveForAttributes(array $attributes, ?InsuranceProgramme $programme = null, ?EnrolleeCategory $category = null, ?PremiumPlan $plan = null): ?int
    {
        return $this->resolve($attributes, $programme, $category, $plan);
    }

    public function syncForEnrollee(Enrollee $enrollee): Enrollee
    {
        $resolvedId = $this->resolveForEnrollee($enrollee);
        if ((int) ($enrollee->vulnerable_group_id ?? 0) === (int) ($resolvedId ?? 0)) {
            return $enrollee;
        }

        $enrollee->forceFill([
            'vulnerable_group_id' => $resolvedId,
        ])->save();

        return $enrollee->fresh(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolve(array $attributes, ?InsuranceProgramme $programme = null, ?EnrolleeCategory $category = null, ?PremiumPlan $plan = null): ?int
    {
        $programme ??= $this->programmeFromAttributes($attributes, $plan);
        $category ??= $this->categoryFromAttributes($attributes);
        $plan ??= $this->planFromAttributes($attributes);

        if (!$this->usesVulnerableGrouping($programme, $category, $plan)) {
            return null;
        }

        $age = $this->ageFromDateOfBirth($attributes['date_of_birth'] ?? null);
        $sex = $this->normalizeSex($attributes['sex'] ?? $attributes['gender'] ?? null);

        if ($age !== null && $age < 5) {
            return $this->groupId(self::CODE_CHILDREN_UNDER_5);
        }

        if ($age !== null && $age >= 85) {
            return $this->groupId(self::CODE_ELDERLY);
        }

        if ($age !== null && $age >= 15 && $age <= 45 && $sex === 2) {
            return $this->groupId(self::CODE_FEMALE_REPRODUCTIVE);
        }

        return $this->groupId(self::CODE_OTHERS);
    }

    private function usesVulnerableGrouping(?InsuranceProgramme $programme, ?EnrolleeCategory $category, ?PremiumPlan $plan): bool
    {
        $search = strtolower(implode(' ', array_filter([
            $programme?->code,
            $programme?->name,
            $category?->code,
            $category?->name,
            $plan?->code,
            $plan?->name,
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        return str_contains($search, 'vulnerable') || str_contains($search, 'bhcpf');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function programmeFromAttributes(array $attributes, ?PremiumPlan $plan = null): ?InsuranceProgramme
    {
        $programmeId = $attributes['insurance_programme_id'] ?? $plan?->insurance_programme_id;
        if (!$programmeId) {
            return null;
        }

        return InsuranceProgramme::query()->select(['id', 'name', 'code'])->find($programmeId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function categoryFromAttributes(array $attributes): ?EnrolleeCategory
    {
        $categoryId = $attributes['enrollee_category_id'] ?? null;
        if (!$categoryId) {
            return null;
        }

        return EnrolleeCategory::query()->select(['id', 'insurance_programme_id', 'name', 'code'])->find($categoryId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function planFromAttributes(array $attributes): ?PremiumPlan
    {
        $planId = $attributes['premium_plan_id'] ?? null;
        if (!$planId) {
            return null;
        }

        return PremiumPlan::query()->select(['id', 'insurance_programme_id', 'name', 'code'])->find($planId);
    }

    private function ageFromDateOfBirth(mixed $dateOfBirth): ?int
    {
        if ($dateOfBirth instanceof Carbon) {
            return $dateOfBirth->age;
        }

        if (!is_string($dateOfBirth) || trim($dateOfBirth) === '') {
            return null;
        }

        try {
            return Carbon::parse($dateOfBirth)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeSex(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $normalized = (int) $value;
            return in_array($normalized, [1, 2], true) ? $normalized : null;
        }

        $text = strtolower(trim((string) $value));

        return match ($text) {
            'male', 'm' => 1,
            'female', 'f' => 2,
            default => null,
        };
    }

    private function groupId(string $code): ?int
    {
        if (array_key_exists($code, $this->groupIds)) {
            return $this->groupIds[$code];
        }

        return $this->groupIds[$code] = VulnerableGroup::query()
            ->where('code', $code)
            ->value('id');
    }
}
