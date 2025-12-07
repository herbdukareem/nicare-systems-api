<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            // Basic columns
            $table->id();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('restrict');
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('restrict');
            $table->foreignId('admission_id')->nullable()->constrained('admissions')->onDelete('set null');
            
            // Claim identification
            $table->string('claim_number', 50)->unique();
            
            // Status tracking
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT, SUBMITTED, REVIEWING, APPROVED, REJECTED');
            $table->string('payment_status', 20)->default('NOT_PROCESSED')->comment('NOT_PROCESSED, PROCESSED, PAID');
            
            // Financial columns
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('bundle_amount', 12, 2)->default(0.00);
            $table->decimal('ffs_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount_claimed', 12, 2)->default(0.00);
            
            // Date columns
            $table->timestamp('service_date');
            $table->timestamp('claim_date')->nullable();
            $table->timestamp('submission_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('payment_processed_at')->nullable();
            
            // User references
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Payment reference
            $table->string('payment_reference')->nullable()->unique();
            
            // Text fields
            $table->text('approval_comments')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['status', 'payment_status']);
            $table->index('enrollee_id');
            $table->index('facility_id');
            $table->index('admission_id');
            $table->index('claim_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks to safely drop the table
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('claims');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};