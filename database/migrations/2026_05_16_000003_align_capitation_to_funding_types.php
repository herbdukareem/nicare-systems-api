<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funding_types') && !Schema::hasColumn('funding_types', 'capitation_rate')) {
            Schema::table('funding_types', function (Blueprint $table): void {
                $table->decimal('capitation_rate', 14, 2)->default(0)->after('description');
            });
        }

        if (Schema::hasTable('capitations') && !Schema::hasColumn('capitations', 'funding_type_id')) {
            Schema::table('capitations', function (Blueprint $table): void {
                $table->foreignId('funding_type_id')
                    ->nullable()
                    ->after('year')
                    ->constrained('funding_types')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('capitation_details') && Schema::hasColumn('capitation_details', 'benefactor_id')) {
            $this->dropForeignIdIfExists('capitation_details', 'benefactor_id');
        }

        if (Schema::hasTable('premium_plans') && Schema::hasColumn('premium_plans', 'capitation_rate')) {
            DB::statement('ALTER TABLE premium_plans DROP COLUMN capitation_rate');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('premium_plans') && !Schema::hasColumn('premium_plans', 'capitation_rate')) {
            Schema::table('premium_plans', function (Blueprint $table): void {
                $table->decimal('capitation_rate', 14, 2)->default(0)->after('amount');
            });
        }

        if (Schema::hasTable('capitation_details') && !Schema::hasColumn('capitation_details', 'benefactor_id')) {
            Schema::table('capitation_details', function (Blueprint $table): void {
                $table->foreignId('benefactor_id')
                    ->nullable()
                    ->after('funding_type_id')
                    ->constrained('benefactors')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('capitations') && Schema::hasColumn('capitations', 'funding_type_id')) {
            Schema::table('capitations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('funding_type_id');
            });
        }

        if (Schema::hasTable('funding_types') && Schema::hasColumn('funding_types', 'capitation_rate')) {
            Schema::table('funding_types', function (Blueprint $table): void {
                $table->dropColumn('capitation_rate');
            });
        }
    }

    private function dropForeignIdIfExists(string $tableName, string $column): void
    {
        $database = DB::getDatabaseName();
        $foreign = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        Schema::table($tableName, function (Blueprint $table) use ($column, $foreign): void {
            if ($foreign) {
                $table->dropForeign($foreign);
            }

            $table->dropColumn($column);
        });
    }
};
