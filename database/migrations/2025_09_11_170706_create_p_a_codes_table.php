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
        Schema::create('p_a_codes', function (Blueprint $table) {
            $table->id();
            $table->string('pa_code')->unique();
            $table->string('utn')->unique(); // Unique Tracking Number

            // Related referral
            $table->foreignId('referral_id')->constrained('referrals');

            // Enrollee and Facility Information
            $table->string('nicare_number');
            $table->string('enrollee_name');
            $table->string('facility_name');
            $table->string('facility_nicare_code');

            // Service Details
            $table->string('service_type');
            $table->text('service_description');
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->text('conditions')->nullable();

            // Validity and Status
            $table->enum('status', ['pending', 'active', 'used', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            // Usage Tracking
            $table->integer('usage_count')->default(0);
            $table->integer('max_usage')->default(1);
            $table->text('usage_notes')->nullable();

            // Approval Details
            $table->foreignId('issued_by')->constrained('users');
            $table->text('issuer_comments')->nullable();

            // Claim Tracking
            $table->string('claim_reference')->nullable();
            $table->timestamp('claim_submitted_at')->nullable();
            $table->enum('claim_status', ['pending', 'approved', 'rejected'])->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['pa_code']);
            $table->index(['utn']);
            $table->index(['nicare_number']);
            $table->index(['status']);
            $table->index(['expires_at']);
            $table->index(['issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p_a_codes');
    }
};
