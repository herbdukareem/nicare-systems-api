<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollee_duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->foreignId('matched_enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->enum('match_type', ['nin_match', 'name_dob_match', 'manual_flag']);
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('resolved')->default(false);
            $table->enum('resolution', ['confirmed_duplicate', 'confirmed_unique', 'merged'])->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollee_duplicate_flags');
    }
};
