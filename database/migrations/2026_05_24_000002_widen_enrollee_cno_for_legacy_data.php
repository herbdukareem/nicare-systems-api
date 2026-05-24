<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $table->string('cno', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $table->string('cno', 10)->nullable()->change();
        });
    }
};
