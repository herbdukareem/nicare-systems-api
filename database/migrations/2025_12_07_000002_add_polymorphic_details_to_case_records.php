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
        Schema::table('case_records', function (Blueprint $table) {
            // Add polymorphic relationship columns
            $table->unsignedBigInteger('detail_id')->nullable()->after('service_description')
                ->comment('ID of the related detail record (polymorphic)');
            $table->string('detail_type')->nullable()->after('detail_id')
                ->comment('Type of detail model (e.g., App\Models\DrugDetail)');
            
            // Add index for polymorphic relationship
            $table->index(['detail_type', 'detail_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_records', function (Blueprint $table) {
            $table->dropIndex(['detail_type', 'detail_id']);
            $table->dropColumn(['detail_id', 'detail_type']);
        });
    }
};

