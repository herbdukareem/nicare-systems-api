<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollees')) {
            return;
        }

        Schema::table('enrollees', function (Blueprint $table): void {
            $this->indexIfMissing($table, 'enrollees', ['status', 'coverage_start_date', 'coverage_end_date'], 'enrollees_dashboard_coverage_idx');
            $this->indexIfMissing($table, 'enrollees', ['facility_id', 'status', 'coverage_start_date', 'coverage_end_date'], 'enrollees_dashboard_facility_coverage_idx');
            $this->indexIfMissing($table, 'enrollees', ['insurance_programme_id', 'status'], 'enrollees_dashboard_programme_status_idx');
            $this->indexIfMissing($table, 'enrollees', ['approval_date'], 'enrollees_dashboard_approval_date_idx');
            $this->indexIfMissing($table, 'enrollees', ['coverage_end_date'], 'enrollees_dashboard_coverage_end_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollees')) {
            return;
        }

        Schema::table('enrollees', function (Blueprint $table): void {
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_dashboard_coverage_end_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_dashboard_approval_date_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_dashboard_programme_status_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_dashboard_facility_coverage_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_dashboard_coverage_idx');
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function indexIfMissing(Blueprint $table, string $tableName, array $columns, string $indexName): void
    {
        if (!$this->indexExists($tableName, $indexName)) {
            $table->index($columns, $indexName);
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            $table->dropIndex($indexName);
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$tableName}')");

            return collect($indexes)->contains(fn (object $index): bool => ($index->name ?? null) === $indexName);
        }

        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);

        return $indexes !== [];
    }
};
