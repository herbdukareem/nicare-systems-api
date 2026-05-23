<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enrollees')) {
            Schema::table('enrollees', function (Blueprint $table): void {
                $this->indexIfMissing($table, 'enrollees', ['enrollee_id'], 'enrollees_enrollee_id_idx');
                $this->indexIfMissing($table, 'enrollees', ['legacy_enrollee_id'], 'enrollees_legacy_enrollee_id_idx');
                $this->indexIfMissing($table, 'enrollees', ['nin'], 'enrollees_nin_idx');
                $this->indexIfMissing($table, 'enrollees', ['phone', 'first_name', 'last_name', 'date_of_birth'], 'enrollees_phone_name_dob_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('enrollees')) {
            Schema::table('enrollees', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'enrollees', 'enrollees_phone_name_dob_idx');
                $this->dropIndexIfExists($table, 'enrollees', 'enrollees_nin_idx');
                $this->dropIndexIfExists($table, 'enrollees', 'enrollees_legacy_enrollee_id_idx');
                $this->dropIndexIfExists($table, 'enrollees', 'enrollees_enrollee_id_idx');
            });
        }
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
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$tableName}')");

            return collect($indexes)->contains(fn (object $index): bool => ($index->name ?? null) === $indexName);
        }

        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);

        return $indexes !== [];
    }
};
