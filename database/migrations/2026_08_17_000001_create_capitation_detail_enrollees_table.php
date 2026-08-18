<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitation_detail_enrollees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('capitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capitation_detail_id')->constrained('capitation_details')->cascadeOnDelete();
            $table->foreignId('enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('funding_type_id')->nullable()->constrained('funding_types')->nullOnDelete();
            $table->string('enrollee_number')->nullable();
            $table->string('legacy_id')->nullable();
            $table->string('full_name');
            $table->string('nin', 32)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('facility_name')->nullable();
            $table->string('facility_code')->nullable();
            $table->string('funding_type_name')->nullable();
            $table->string('lga_name')->nullable();
            $table->string('ward_name')->nullable();
            $table->date('coverage_start_date')->nullable();
            $table->date('coverage_end_date')->nullable();
            $table->date('capitation_start_date')->nullable();
            $table->unsignedTinyInteger('snapshot_status')->nullable();
            $table->string('duplicate_nin_policy', 20)->default('exclude');
            $table->boolean('has_duplicate_nin')->default(false);
            $table->timestamp('captured_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['capitation_detail_id', 'enrollee_id'], 'capitation_detail_enrollees_detail_enrollee_unique');
            $table->index(['capitation_id', 'funding_type_id'], 'capitation_detail_enrollees_capitation_funding_index');
            $table->index(['capitation_id', 'facility_id'], 'capitation_detail_enrollees_capitation_facility_index');
            $table->index('enrollee_number');
            $table->index('nin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitation_detail_enrollees');
    }
};
