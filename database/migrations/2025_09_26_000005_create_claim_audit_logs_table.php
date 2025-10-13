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
        Schema::create('claim_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            
            // Audit Information
            $table->string('action'); // created, updated, submitted, approved, rejected, etc.
            $table->string('field_changed')->nullable(); // specific field that was changed
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->text('comments')->nullable();
            
            // User Information
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_role');
            $table->string('user_name');
            
            // System Information
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('performed_at');
            
            // Foreign Keys
            $table->foreign('claim_id')->references('id')->on('claims')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            
            // Indexes
            $table->index(['claim_id']);
            $table->index(['action']);
            $table->index(['performed_at']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_audit_logs');
    }
};
