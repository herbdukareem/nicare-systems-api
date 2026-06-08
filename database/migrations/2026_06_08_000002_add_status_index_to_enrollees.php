<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $table->index('status', 'enrollees_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $table->dropIndex('enrollees_status_index');
        });
    }
};
