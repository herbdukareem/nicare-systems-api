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
        Schema::table('drugs', function (Blueprint $table) {
            $table->text('pack_description')
                  ->nullable()
                  ->after('drug_presentation')
                  ->comment('Description of the drug pack (e.g., "Pack of 10 tablets", "Box of 5 ampoules")');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('pack_description');
        });
    }
};

