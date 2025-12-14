<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Batch payment table
        Schema::create('claim_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('batch_month', 7); // YYYY-MM format
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->onDelete('set null');
            $table->integer('total_claims')->default(0);
            $table->decimal('total_bundle_amount', 14, 2)->default(0);
            $table->decimal('total_ffs_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status')->default('PENDING')->comment('PENDING, PROCESSING, PAID, FAILED');
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable()->comment('BANK_TRANSFER, CHEQUE');
            $table->text('bank_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['batch_month', 'facility_id']);
            $table->index(['status']);
        });

        // Link claims to payment batches
        Schema::table('claims', function (Blueprint $table) {
            $table->foreignId('payment_batch_id')->nullable()->after('payment_status')
                  ->constrained('claim_payment_batches')->onDelete('set null');
            $table->decimal('approved_amount', 14, 2)->nullable()->after('total_amount_claimed');
            $table->foreignId('reviewed_by')->nullable()->after('approved_by')
                  ->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable()->after('approved_at');
        });

        // Add feedback event tracking columns
        Schema::table('feedback_records', function (Blueprint $table) {
            $table->string('event_type')->nullable()->after('feedback_type')
                  ->comment('UTN_VALIDATED, FUP_REQUESTED, FUP_APPROVED, ADMISSION, DISCHARGE, CLAIM_SUBMITTED');
            $table->boolean('is_system_generated')->default(false)->after('event_type');
            $table->string('referral_status_before')->nullable()->after('is_system_generated');
            $table->string('referral_status_after')->nullable()->after('referral_status_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_records', function (Blueprint $table) {
            $table->dropColumn(['event_type', 'is_system_generated', 'referral_status_before', 'referral_status_after']);
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['payment_batch_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['payment_batch_id', 'approved_amount', 'reviewed_by', 'reviewed_at']);
        });

        Schema::dropIfExists('claim_payment_batches');
    }
};
