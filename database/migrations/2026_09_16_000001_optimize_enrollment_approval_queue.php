<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $this->indexIfMissing($table, 'enrollees', ['status', 'enrollment_date', 'created_at', 'id'], 'enrollees_approval_queue_sort_idx');
            $this->indexIfMissing($table, 'enrollees', ['status', 'facility_id', 'enrollment_date', 'id'], 'enrollees_approval_facility_sort_idx');
            $this->indexIfMissing($table, 'enrollees', ['status', 'benefactor_id', 'enrollment_date', 'id'], 'enrollees_approval_benefactor_sort_idx');
            $this->indexIfMissing($table, 'enrollees', ['status', 'funding_type_id', 'enrollment_date', 'id'], 'enrollees_approval_funding_sort_idx');
            $this->indexIfMissing($table, 'enrollees', ['status', 'enrollment_phase_id', 'enrollment_date', 'id'], 'enrollees_approval_phase_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_approval_phase_sort_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_approval_funding_sort_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_approval_benefactor_sort_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_approval_facility_sort_idx');
            $this->dropIndexIfExists($table, 'enrollees', 'enrollees_approval_queue_sort_idx');
        });
    }

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
