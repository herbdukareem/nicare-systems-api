<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIdIfExists('enrollee_categories', 'insurance_sub_programme_id');
        $this->dropForeignIdIfExists('premium_plans', 'insurance_sub_programme_id');
        $this->dropForeignIdIfExists('premium_plans', 'enrollee_category_id');
        $this->dropForeignIdIfExists('payroll_batches', 'insurance_sub_programme_id');
        $this->dropForeignIdIfExists('subsidy_batches', 'insurance_sub_programme_id');

        Schema::dropIfExists('insurance_sub_programmes');
    }

    public function down(): void
    {
        if (!Schema::hasTable('insurance_sub_programmes')) {
            Schema::create('insurance_sub_programmes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('insurance_programme_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        $this->addSubProgrammeForeignId('enrollee_categories');
        $this->addSubProgrammeForeignId('premium_plans');

        if (Schema::hasTable('premium_plans') && !Schema::hasColumn('premium_plans', 'enrollee_category_id')) {
            Schema::table('premium_plans', function (Blueprint $table) {
                $table->foreignId('enrollee_category_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        $this->addSubProgrammeForeignId('payroll_batches');
        $this->addSubProgrammeForeignId('subsidy_batches');
    }

    private function dropForeignIdIfExists(string $tableName, string $column): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
            $table->dropColumn($column);
        });
    }

    private function addSubProgrammeForeignId(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'insurance_sub_programme_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreignId('insurance_sub_programme_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
