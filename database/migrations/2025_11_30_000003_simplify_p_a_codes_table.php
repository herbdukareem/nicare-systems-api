<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Simplify PACode table - add pa_type (bundle/ffs), admission_id link
     */
    public function up(): void
    {
        Schema::table('p_a_codes', function (Blueprint $table) {
            // Add PA type to distinguish bundle vs FFS authorizations
            $table->enum('pa_type', ['bundle', 'ffs'])->default('bundle')->after('referral_id');
            
            // Link PA to admission (for FFS PAs issued during admission)
            $table->foreignId('admission_id')->nullable()->after('pa_type')->constrained('admissions');
            
            // Add index for admission lookup
            $table->index(['admission_id', 'pa_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p_a_codes', function (Blueprint $table) {
            $table->dropForeign(['admission_id']);
            $table->dropIndex(['admission_id', 'pa_type', 'status']);
            $table->dropColumn(['pa_type', 'admission_id']);
        });
    }
};

