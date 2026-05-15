<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollees', 'insurance_programme_id')) {
                $table->foreignId('insurance_programme_id')->nullable()->after('sector_id')->constrained('insurance_programmes')->nullOnDelete();
            }

            if (!Schema::hasColumn('enrollees', 'enrollee_category_id')) {
                $table->foreignId('enrollee_category_id')->nullable()->after('insurance_programme_id')->constrained('enrollee_categories')->nullOnDelete();
            }

            if (!Schema::hasColumn('enrollees', 'principal_enrollee_id')) {
                $table->foreignId('principal_enrollee_id')->nullable()->after('enrollee_category_id')->constrained('enrollees')->nullOnDelete();
            }

            if (!Schema::hasColumn('enrollees', 'coverage_start_date')) {
                $table->date('coverage_start_date')->nullable()->after('approval_date');
            }

            if (!Schema::hasColumn('enrollees', 'coverage_end_date')) {
                $table->date('coverage_end_date')->nullable()->after('coverage_start_date');
            }
        });

        $this->dropForeignOnlyIfExists('enrollees', 'premium_id');

        if (Schema::hasTable('premium_plans') && Schema::hasColumn('enrollees', 'premium_id')) {
            Schema::table('enrollees', function (Blueprint $table) {
                $table->foreign('premium_id')->references('id')->on('premium_plans')->nullOnDelete();
            });
        }

        Schema::table('premium_pins', function (Blueprint $table) {
            if (Schema::hasTable('insurance_programmes') && !Schema::hasColumn('premium_pins', 'insurance_programme_id')) {
                $table->foreignId('insurance_programme_id')->nullable()->after('premium_plan_id')->constrained('insurance_programmes')->nullOnDelete();
            }
            if (Schema::hasTable('benefit_packages') && !Schema::hasColumn('premium_pins', 'benefit_package_id')) {
                $table->foreignId('benefit_package_id')->nullable()->after('insurance_programme_id')->constrained('benefit_packages')->nullOnDelete();
            }
            if (Schema::hasTable('lgas') && !Schema::hasColumn('premium_pins', 'lga_id')) {
                $table->foreignId('lga_id')->nullable()->after('benefit_package_id')->constrained('lgas')->nullOnDelete();
            }
            if (Schema::hasTable('wards') && !Schema::hasColumn('premium_pins', 'ward_id')) {
                $table->foreignId('ward_id')->nullable()->after('lga_id')->constrained('wards')->nullOnDelete();
            }
            if (!Schema::hasColumn('premium_pins', 'userable_type')) {
                $table->nullableMorphs('userable');
            }
        });
    }

    public function down(): void
    {
        $this->dropForeignIdIfExists('premium_pins', 'insurance_programme_id');
        $this->dropForeignIdIfExists('premium_pins', 'benefit_package_id');
        $this->dropForeignIdIfExists('premium_pins', 'lga_id');
        $this->dropForeignIdIfExists('premium_pins', 'ward_id');

        if (Schema::hasTable('premium_pins') && Schema::hasColumn('premium_pins', 'userable_type')) {
            Schema::table('premium_pins', function (Blueprint $table) {
                $table->dropMorphs('userable');
            });
        }

        $this->dropForeignIdIfExists('enrollees', 'insurance_programme_id');
        $this->dropForeignIdIfExists('enrollees', 'enrollee_category_id');
        $this->dropForeignIdIfExists('enrollees', 'principal_enrollee_id');
        $this->dropForeignOnlyIfExists('enrollees', 'premium_id');

        if (Schema::hasTable('premiums') && Schema::hasColumn('enrollees', 'premium_id')) {
            Schema::table('enrollees', function (Blueprint $table) {
                $table->foreign('premium_id')->references('id')->on('premiums')->nullOnDelete();
            });
        }

        Schema::table('enrollees', function (Blueprint $table) {
            foreach (['coverage_end_date', 'coverage_start_date'] as $column) {
                if (Schema::hasColumn('enrollees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
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

    private function dropForeignOnlyIfExists(string $tableName, string $column): void
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

        if (!$foreign) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($foreign) {
            $table->dropForeign($foreign);
        });
    }
};
