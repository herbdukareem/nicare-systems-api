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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // Type of security event
            $table->ipAddress('ip_address'); // IP address of the request
            $table->text('user_agent')->nullable(); // User agent string
            $table->text('url'); // Full URL of the request
            $table->string('method'); // HTTP method
            $table->json('details')->nullable(); // Additional details about the event
            $table->enum('severity', ['low', 'medium', 'high'])->default('low'); // Severity level
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Associated user if any
            $table->timestamp('resolved_at')->nullable(); // When the issue was resolved
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null'); // Who resolved it
            $table->timestamps();

            // Indexes for better performance
            $table->index(['type', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
