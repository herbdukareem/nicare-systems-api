<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnrolleeDuplicateNinService
{
    public function applyUniqueNinOnly(EloquentBuilder $query, string $table = 'enrollees'): EloquentBuilder
    {
        if (Schema::hasColumn('enrollees', 'has_duplicate_nin')) {
            return $query
                ->whereNotNull("{$table}.nin")
                ->where("{$table}.nin", '!=', '')
                ->where("{$table}.has_duplicate_nin", 0);
        }

        return $query->whereIn("{$table}.nin", $this->uniqueNinSubquery());
    }

    public function refreshAll(): void
    {
        DB::transaction(function (): void {
            DB::table('enrollees')->update(['has_duplicate_nin' => 0]);

            $duplicateNins = $this->duplicateNinSubquery()->pluck('matched_nin');

            $duplicateNins->chunk(500)->each(function (Collection $chunk): void {
                DB::table('enrollees')
                    ->whereIn('nin', $chunk->all())
                    ->update([
                        'has_duplicate_nin' => 1,
                        'is_possible_duplicate' => 1,
                    ]);
            });
        }, 3);
    }

    /**
     * @param iterable<int, string|null> $nins
     */
    public function refreshForNins(iterable $nins): void
    {
        $values = collect($nins)
            ->map(fn ($nin) => is_string($nin) ? trim($nin) : null)
            ->filter(fn ($nin) => filled($nin))
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($values): void {
            $values->chunk(500)->each(function (Collection $chunk): void {
                $ninValues = $chunk->all();

                DB::table('enrollees')
                    ->whereIn('nin', $ninValues)
                    ->update(['has_duplicate_nin' => 0]);

                $duplicates = DB::table('enrollees')
                    ->select('nin')
                    ->whereIn('nin', $ninValues)
                    ->whereNotNull('nin')
                    ->where('nin', '!=', '')
                    ->groupBy('nin')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('nin');

                if ($duplicates->isNotEmpty()) {
                    DB::table('enrollees')
                        ->whereIn('nin', $duplicates->all())
                        ->update([
                            'has_duplicate_nin' => 1,
                            'is_possible_duplicate' => 1,
                        ]);
                }
            });
        }, 3);
    }

    public function duplicateNinSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('enrollees')
            ->select('nin as matched_nin')
            ->whereNotNull('nin')
            ->where('nin', '!=', '')
            ->groupBy('nin')
            ->havingRaw('COUNT(*) > 1');
    }

    public function uniqueNinSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('enrollees')
            ->select('nin')
            ->whereNotNull('nin')
            ->where('nin', '!=', '')
            ->groupBy('nin')
            ->havingRaw('COUNT(*) = 1');
    }
}
