<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create claim_status_history table to track status changes
     */
    public function up(): void
    {
        Schema::create('claim_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->onDelete('cascade');
            
            $table->string('old_status');
            $table->string('new_status');
            
            $table->foreignId('changed_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('changed_at')->useCurrent();
            
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            
            $table->index(['claim_id']);
            $table->index(['new_status']);
            $table->index(['changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_status_history');
    }
};

