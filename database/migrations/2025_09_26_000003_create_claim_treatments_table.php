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
        Schema::create('claim_treatments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            
            // Service Information
            $table->date('service_date');
            $table->enum('service_type', [
                'professional_service',
                'hospital_stay',
                'medication',
                'consumable',
                'laboratory',
                'radiology',
                'other'
            ]);
            $table->string('service_code');
            $table->text('service_description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('approved_benefit_fee', 10, 2)->nullable();
            
            // Validation Status
            $table->boolean('doctor_validated')->default(false);
            $table->timestamp('doctor_validated_at')->nullable();
            $table->unsignedBigInteger('doctor_validated_by')->nullable();
            $table->text('doctor_validation_comments')->nullable();
            
            $table->boolean('pharmacist_validated')->default(false);
            $table->timestamp('pharmacist_validated_at')->nullable();
            $table->unsignedBigInteger('pharmacist_validated_by')->nullable();
            $table->text('pharmacist_validation_comments')->nullable();
            
            // Tariff Validation
            $table->boolean('tariff_validated')->default(false);
            $table->decimal('tariff_amount', 10, 2)->nullable();
            $table->text('tariff_validation_notes')->nullable();
            
            // Audit
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('claim_id')->references('id')->on('claims')->onDelete('cascade');
            $table->foreign('doctor_validated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('pharmacist_validated_by')->references('id')->on('users')->nullOnDelete();
            
            // Indexes
            $table->index(['claim_id']);
            $table->index(['service_date']);
            $table->index(['service_type']);
            $table->index(['service_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_treatments');
    }
};
