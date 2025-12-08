<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, delete any records with NULL case_record_id
        DB::table('bundle_components')->whereNull('case_record_id')->delete();
        
        // Then make the column NOT NULL
        Schema::table('bundle_components', function (Blueprint $table) {
            $table->foreignId('case_record_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bundle_components', function (Blueprint $table) {
            $table->foreignId('case_record_id')->nullable()->change();
        });
    }
};

