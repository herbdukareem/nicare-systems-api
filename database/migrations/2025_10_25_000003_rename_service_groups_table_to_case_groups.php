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
        Schema::rename('service_groups', 'case_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('case_groups', 'service_groups');
    }
};

