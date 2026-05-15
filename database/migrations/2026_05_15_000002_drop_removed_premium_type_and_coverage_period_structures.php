<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['claims', 'coverage_period_id'],
            ['benefactor_enrollees', 'coverage_period_id'],
            ['payroll_batch_enrollees', 'coverage_period_id'],
            ['subsidy_batch_enrollees', 'coverage_period_id'],
            ['premium_plans', 'premium_type_id'],
            ['premium_pins', 'premium_type_id'],
            ['premiums', 'premium_type_id'],
        ] as [$table, $column]) {
            $this->dropForeignIdIfExists($table, $column);
        }

        Schema::dropIfExists('coverage_periods');
        Schema::dropIfExists('premium_types');
    }

    public function down(): void
    {
        //
    }

    private function dropForeignIdIfExists(string $tableName, string $column): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, $column)) {
            return;
        }

        $database = DB::getDatabaseName();
        $foreign = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        Schema::table($tableName, function (Blueprint $table) use ($column, $foreign) {
            if ($foreign) {
                $table->dropForeign($foreign);
            }

            $table->dropColumn($column);
        });
    }
};
