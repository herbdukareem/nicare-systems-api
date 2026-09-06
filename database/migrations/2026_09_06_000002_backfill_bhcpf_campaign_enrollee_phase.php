<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('enrollees')
            || !Schema::hasTable('enrollment_phases')
            || !Schema::hasColumn('enrollees', 'enrollment_phase_id')
        ) {
            return;
        }

        $phase = DB::table('enrollment_phases')
            ->where('name', '65K BHCPF Enrollment')
            ->first(['id', 'start_date', 'end_date']);

        if (!$phase) {
            return;
        }

        $programmeIds = DB::table('insurance_programmes')
            ->where('code', 'vulnerable_groups')
            ->orWhere('name', 'like', '%vulnerable%')
            ->pluck('id');
        $benefactorIds = DB::table('benefactors')
            ->where('name',  'BHCPF')
            ->pluck('id');
        $fundingTypeIds = DB::table('funding_types')
            ->where('name', 'like', '%Basic Healthcare Provision Fund%')
            ->orWhere('name', 'like', '%BHCPF%')
            ->pluck('id');

        $startDate = $phase->start_date ?: '2026-08-03';
        $endDate = $phase->end_date ?: now()->toDateString();

        DB::table('enrollees')
            ->whereNull('enrollment_phase_id')
            ->whereBetween(DB::raw('DATE(COALESCE(enrollment_date, created_at))'), [$startDate, $endDate])
            ->when($programmeIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('insurance_programme_id', $programmeIds))
            ->when($benefactorIds->isNotEmpty() || $fundingTypeIds->isNotEmpty(), function (Builder $query) use ($benefactorIds, $fundingTypeIds): void {
                $query->where(function (Builder $nested) use ($benefactorIds, $fundingTypeIds): void {
                    if ($benefactorIds->isNotEmpty()) {
                        $nested->orWhereIn('benefactor_id', $benefactorIds);
                    }

                    if ($fundingTypeIds->isNotEmpty()) {
                        $nested->orWhereIn('funding_type_id', $fundingTypeIds);
                    }
                });
            })
            ->update([
                'enrollment_phase_id' => $phase->id,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Phase assignment is campaign data and must not be removed on rollback.
    }
};
