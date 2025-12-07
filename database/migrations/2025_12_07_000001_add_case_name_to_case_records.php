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
            $table->string('case_name')->after('id')->nullable()->comment('Name of the case/service');
            $table->index('case_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_records', function (Blueprint $table) {
            $table->dropIndex(['case_name']);
            $table->dropColumn('case_name');
        });
    }
};

