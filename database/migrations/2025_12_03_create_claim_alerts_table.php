<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create claim_alerts table to store validation alerts
     */
    public function up(): void
    {
        Schema::create('claim_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->onDelete('cascade');
            
            $table->string('alert_type')->comment('CRITICAL, WARNING, INFO');
            $table->string('alert_code')->comment('DOUBLE_BUNDLE, UNAUTHORIZED_FFS_TOP_UP, etc.');
            $table->text('message');
            $table->string('action')->nullable()->comment('REJECT_CLAIM, REJECT_FFS_LINES, RESOLVE_ALERT');
            $table->string('severity')->default('WARNING')->comment('CRITICAL, WARNING, INFO');
            
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['claim_id']);
            $table->index(['alert_code']);
            $table->index(['severity']);
            $table->index(['resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_alerts');
    }
};

