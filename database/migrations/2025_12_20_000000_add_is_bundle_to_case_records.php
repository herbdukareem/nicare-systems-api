<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add is_bundle field to case_records table to indicate whether
     * a case is a bundle or FFS service.
     */
    public function up(): void
    {
        Schema::table('case_records', function (Blueprint $table) {
            $table->boolean('is_bundle')
                  ->default(false)
                  ->after('status')
                  ->comment('Whether this case is a bundle (true) or FFS service (false)');
            
            $table->decimal('bundle_price', 10, 2)
                  ->nullable()
                  ->after('is_bundle')
                  ->comment('Fixed price for bundle (if is_bundle is true)');
            
            $table->string('diagnosis_icd10', 20)
                  ->nullable()
                  ->after('bundle_price')
                  ->comment('ICD-10 diagnosis code for bundle (if is_bundle is true)');
            
            $table->index('is_bundle');
            $table->index('diagnosis_icd10');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_records', function (Blueprint $table) {
            $table->dropIndex(['is_bundle']);
            $table->dropIndex(['diagnosis_icd10']);
            $table->dropColumn(['is_bundle', 'bundle_price', 'diagnosis_icd10']);
        });
    }
};

