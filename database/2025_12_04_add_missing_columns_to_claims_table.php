<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add missing columns to claims table
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            // Add admission relationship
            if (!Schema::hasColumn('claims', 'admission_id')) {
                $table->foreignId('admission_id')->nullable()->constrained('admissions')->onDelete('set null');
            }

            // Note: bundle_amount, ffs_amount, total_amount_claimed already added in previous migration

            // Add approval tracking
            if (!Schema::hasColumn('claims', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (!Schema::hasColumn('claims', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('claims', 'approval_comments')) {
                $table->text('approval_comments')->nullable();
            }

            // Add rejection tracking
            if (!Schema::hasColumn('claims', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (!Schema::hasColumn('claims', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('claims', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            // Add submission tracking
            if (!Schema::hasColumn('claims', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }

            if (!Schema::hasColumn('claims', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            }

            // Add payment tracking
            if (!Schema::hasColumn('claims', 'payment_status')) {
                $table->string('payment_status')->default('NOT_PROCESSED')->comment('NOT_PROCESSED, PROCESSED, PAID');
            }

            if (!Schema::hasColumn('claims', 'payment_processed_at')) {
                $table->timestamp('payment_processed_at')->nullable();
            }

            if (!Schema::hasColumn('claims', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->unique();
            }

            // Add claim date
            if (!Schema::hasColumn('claims', 'claim_date')) {
                $table->timestamp('claim_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop columns using raw SQL to avoid foreign key issues
        $columns = [
            'admission_id',
            'approved_at',
            'approved_by',
            'approval_comments',
            'rejected_at',
            'rejected_by',
            'rejection_reason',
            'submitted_at',
            'submitted_by',
            'payment_status',
            'payment_processed_at',
            'payment_reference',
            'claim_date',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('claims', $column)) {
                DB::statement("ALTER TABLE claims DROP COLUMN `$column`");
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};