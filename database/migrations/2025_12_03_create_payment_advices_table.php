<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create payment_advices table to store payment information
     */
    public function up(): void
    {
        Schema::create('payment_advices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->onDelete('cascade');
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('restrict');
            
            $table->decimal('payment_amount', 12, 2);
            $table->string('status')->default('PENDING')->comment('PENDING, PROCESSED, PAID, FAILED');
            $table->string('reference_number')->unique();
            $table->string('payment_method')->nullable()->comment('BANK_TRANSFER, CHEQUE, etc.');
            
            $table->text('bank_details')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamp('payment_date')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            $table->index(['claim_id']);
            $table->index(['facility_id']);
            $table->index(['status']);
            $table->index(['reference_number']);
            $table->index(['payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_advices');
    }
};

