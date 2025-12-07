<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create audit_logs table to track all changes
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            $table->string('entity_type')->comment('Claim, Admission, PACode, etc.');
            $table->unsignedBigInteger('entity_id');
            $table->string('action')->comment('CREATE, UPDATE, DELETE, APPROVE, REJECT');
            
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

