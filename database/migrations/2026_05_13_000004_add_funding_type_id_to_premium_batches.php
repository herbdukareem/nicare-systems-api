<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_batches', 'funding_type_id')) {
                $table->foreignId('funding_type_id')
                    ->nullable()
                    ->after('benefactor_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        Schema::table('subsidy_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('subsidy_batches', 'funding_type_id')) {
                $table->foreignId('funding_type_id')
                    ->nullable()
                    ->after('benefactor_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subsidy_batches', function (Blueprint $table) {
            if (Schema::hasColumn('subsidy_batches', 'funding_type_id')) {
                $table->dropConstrainedForeignId('funding_type_id');
            }
        });

        Schema::table('payroll_batches', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_batches', 'funding_type_id')) {
                $table->dropConstrainedForeignId('funding_type_id');
            }
        });
    }
};
