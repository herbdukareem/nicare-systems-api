<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premiums', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 16)->unique(); // Encrypted PIN for users
            $table->string('pin_raw', 16); // Raw PIN for internal use
            $table->string('serial_no', 20)->unique(); // Serial number
            $table->enum('pin_type', ['individual', 'family', 'group'])->default('individual');
            $table->enum('pin_category', ['formal', 'informal', 'vulnerable', 'retiree'])->default('formal');
            $table->enum('benefit_type', ['basic', 'standard', 'premium'])->default('basic');
            $table->decimal('amount', 10, 2);
            $table->timestamp('date_generated');
            $table->timestamp('date_used')->nullable();
            $table->timestamp('date_expired');
            $table->enum('status', ['available', 'used', 'expired', 'suspended'])->default('available');
            $table->foreignId('used_by')->nullable()->constrained('users'); // Agent who used it
            $table->string('agent_reg_number')->nullable(); // Agent registration number
            $table->foreignId('lga_id')->nullable()->constrained(); // Set when used
            $table->foreignId('ward_id')->nullable()->constrained(); // Set when used
            $table->string('payment_id')->nullable(); // For bulk payment tracking
            $table->string('request_id')->nullable(); // For generation request tracking
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            $table->index(['status', 'date_expired']);
            $table->index(['pin_type', 'pin_category']);
            $table->index(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premiums');
    }
};