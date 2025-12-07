<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->onDelete('cascade');
            $table->foreignId('case_record_id')->constrained('case_records')->onDelete('restrict'); // Link to the service/tariff item

            $table->foreignId('pa_code_id')->nullable()->constrained('pa_codes')->onDelete('set null'); // MANDATORY PA LINKAGE

            $table->string('tariff_type', 20)->comment('BUNDLE or FFS');
            $table->string('service_description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->enum('reporting_type', ['IN_BUNDLE', 'FFS_TOP_UP', 'FFS_STANDALONE'])
                  ->comment('Used by the facility to report service status.');
                  
            $table->string('reported_diagnosis_code')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_lines');
    }
};