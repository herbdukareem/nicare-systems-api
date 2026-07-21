<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('premium_plans', 'payment_split_profile_code')) {
                $table->string('payment_split_profile_code', 80)->nullable()->after('payment_gateway');
            }
        });
    }

    public function down(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (Schema::hasColumn('premium_plans', 'payment_split_profile_code')) {
                $table->dropColumn('payment_split_profile_code');
            }
        });
    }
};
