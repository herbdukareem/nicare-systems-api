<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bundles', function (Blueprint $table) {
            // Add case_record_id to link bundle to a case record
            $table->foreignId('case_record_id')->nullable()->after('id')->constrained('case_records')->onDelete('restrict');
            
            // Make code and name nullable since they'll come from case_record
            $table->string('name')->nullable()->change();
            $table->string('code', 50)->nullable()->change();
            
            // Make diagnosis_icd10 nullable (it already is, but being explicit)
            // description is required
            $table->text('description')->nullable(false)->change();
        });
        
        // Remove item_type from bundle_components since it's not needed
        // The item type can be inferred from the case_record's detail_type
        Schema::table('bundle_components', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }

    public function down(): void
    {
        Schema::table('service_bundles', function (Blueprint $table) {
            $table->dropForeign(['case_record_id']);
            $table->dropColumn('case_record_id');
            
            $table->string('name')->nullable(false)->change();
            $table->string('code', 50)->nullable(false)->change();
            $table->text('description')->nullable()->change();
        });
        
        Schema::table('bundle_components', function (Blueprint $table) {
            $table->string('item_type')->after('max_quantity')->comment('LAB, DRUG, CONSULTATION, etc.');
        });
    }
};

