<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollees', 'password')) {
                $table->string('password')->nullable()->after('nin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            if (Schema::hasColumn('enrollees', 'password')) {
                $table->dropColumn('password');
            }
        });
    }
};
