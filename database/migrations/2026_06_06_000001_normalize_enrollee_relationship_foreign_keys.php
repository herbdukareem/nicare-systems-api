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

        foreach ($this->definitions() as $definition) {
            if (!Schema::hasColumn('enrollees', $definition['column']) || !Schema::hasTable($definition['references'])) {
                continue;
            }

            if ($definition['nullable']) {
                $this->nullOutOrphans($definition['column'], $definition['references']);
            }

            $currentConstraint = $this->currentConstraint('enrollees', $definition['column']);

            if ($currentConstraint
                && $currentConstraint['referenced_table'] === $definition['references']
                && strtoupper($currentConstraint['delete_rule']) === $definition['delete_rule']) {
                continue;
            }

            if ($currentConstraint) {
                Schema::table('enrollees', function (Blueprint $table) use ($currentConstraint): void {
                    $table->dropForeign($currentConstraint['constraint_name']);
                });
            }

            Schema::table('enrollees', function (Blueprint $table) use ($definition): void {
                $foreign = $table->foreign($definition['column'])
                    ->references('id')
                    ->on($definition['references']);

                if ($definition['delete_rule'] === 'SET NULL') {
                    $foreign->nullOnDelete();
                    return;
                }

                $foreign->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op to avoid destructive rollback of live foreign keys.
    }

    /**
     * @return array<int, array{column: string, references: string, nullable: bool, delete_rule: string}>
     */
    private function definitions(): array
    {
        return [
            ['column' => 'facility_id', 'references' => 'facilities', 'nullable' => false, 'delete_rule' => 'RESTRICT'],
            ['column' => 'lga_id', 'references' => 'lgas', 'nullable' => false, 'delete_rule' => 'RESTRICT'],
            ['column' => 'ward_id', 'references' => 'wards', 'nullable' => false, 'delete_rule' => 'RESTRICT'],
            ['column' => 'funding_type_id', 'references' => 'funding_types', 'nullable' => true, 'delete_rule' => 'SET NULL'],
            ['column' => 'benefactor_id', 'references' => 'benefactors', 'nullable' => true, 'delete_rule' => 'SET NULL'],
            ['column' => 'benefit_package_id', 'references' => 'benefit_packages', 'nullable' => true, 'delete_rule' => 'SET NULL'],
            ['column' => 'premium_plan_id', 'references' => 'premium_plans', 'nullable' => true, 'delete_rule' => 'SET NULL'],
            ['column' => 'enrollment_phase_id', 'references' => 'enrollment_phases', 'nullable' => true, 'delete_rule' => 'SET NULL'],
        ];
    }

    private function nullOutOrphans(string $column, string $referencedTable): void
    {
        DB::table('enrollees')
            ->whereNotNull($column)
            ->whereNotExists(function ($query) use ($column, $referencedTable) {
                $query->select(DB::raw(1))
                    ->from($referencedTable)
                    ->whereColumn("{$referencedTable}.id", "enrollees.{$column}");
            })
            ->update([$column => null]);
    }

    /**
     * @return array{constraint_name: string, referenced_table: string, delete_rule: string}|null
     */
    private function currentConstraint(string $table, string $column): ?array
    {
        $database = DB::getDatabaseName();

        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE as kcu')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as rc', function ($join) use ($database) {
                $join->on('kcu.CONSTRAINT_NAME', '=', 'rc.CONSTRAINT_NAME')
                    ->on('kcu.TABLE_NAME', '=', 'rc.TABLE_NAME')
                    ->where('rc.CONSTRAINT_SCHEMA', '=', $database);
            })
            ->where('kcu.TABLE_SCHEMA', $database)
            ->where('kcu.TABLE_NAME', $table)
            ->where('kcu.COLUMN_NAME', $column)
            ->whereNotNull('kcu.REFERENCED_TABLE_NAME')
            ->select([
                'kcu.CONSTRAINT_NAME as constraint_name',
                'kcu.REFERENCED_TABLE_NAME as referenced_table',
                'rc.DELETE_RULE as delete_rule',
            ])
            ->first();

        return $constraint ? (array) $constraint : null;
    }
};
