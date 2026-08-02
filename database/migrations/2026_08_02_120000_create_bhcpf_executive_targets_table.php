<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bhcpf_executive_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lga_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ward_count')->default(0);
            $table->unsignedInteger('current_enrollee_count')->default(0);
            $table->unsignedInteger('poverty_index')->default(0);
            $table->unsignedInteger('proposed_enrolments')->default(0);
            $table->unsignedInteger('final_target')->default(0);
            $table->unsignedInteger('plwd_target')->default(0);
            $table->unsignedInteger('under_5_target')->default(0);
            $table->unsignedInteger('female_reproductive_target')->default(0);
            $table->unsignedInteger('elderly_target')->default(0);
            $table->unsignedInteger('others_target')->default(0);
            $table->unsignedInteger('proposed_per_ward')->default(0);
            $table->unsignedInteger('plwd_per_ward')->default(0);
            $table->unsignedInteger('under_5_per_ward')->default(0);
            $table->unsignedInteger('female_reproductive_per_ward')->default(0);
            $table->unsignedInteger('elderly_per_ward')->default(0);
            $table->unsignedInteger('others_per_ward')->default(0);
            $table->timestamps();

            $table->unique('lga_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bhcpf_executive_targets');
    }
};
