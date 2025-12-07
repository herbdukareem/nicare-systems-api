<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add missing columns to pa_codes table for approval tracking
     */
    public function up(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            // Add approval tracking
            if (!Schema::hasColumn('pa_codes', 'approval_date')) {
                $table->timestamp('approval_date')->nullable();
            }

            if (!Schema::hasColumn('pa_codes', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('pa_codes', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            // Add type column if not exists
            if (!Schema::hasColumn('pa_codes', 'type')) {
                $table->string('type')->default('BUNDLE')->comment('BUNDLE or FFS_TOP_UP');
            }
        });
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $columns = ['approval_date', 'approved_by', 'rejection_reason', 'type'];
        foreach ($columns as $column) {
            if (Schema::hasColumn('pa_codes', $column)) {
                DB::statement("ALTER TABLE pa_codes DROP COLUMN `$column`");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};

